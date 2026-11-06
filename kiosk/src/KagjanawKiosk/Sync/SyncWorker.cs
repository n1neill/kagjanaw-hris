using KagjanawKiosk.Matching;

namespace KagjanawKiosk.Sync;

/// <summary>What the clock-in screen shows about the kiosk's connection.</summary>
public sealed record KioskStatus(
    bool IsOnline,
    bool IsKioskRejected,
    string? Problem,
    int QueuedPunches,
    int RejectedPunches,
    int EnrolledFingers,
    TimeSpan? ClockDrift,
    DateTimeOffset? LastContact,
    string? DeviceName)
{
    public static readonly TimeSpan AllowedClockDrift = TimeSpan.FromMinutes(2);

    public bool HasClockDrift => ClockDrift is { } drift && drift.Duration() > AllowedClockDrift;
}

/// <summary>
/// Keeps the kiosk in step with the HRIS in the background: checks in every
/// 30 seconds (and learns the server time), sends queued punches, and
/// refreshes the enrolled fingers every 5 minutes or when asked.
/// </summary>
public sealed class SyncWorker : IAsyncDisposable
{
    public static readonly TimeSpan CheckInInterval = TimeSpan.FromSeconds(30);
    public static readonly TimeSpan TemplateRefreshInterval = TimeSpan.FromMinutes(5);
    private const int BatchSize = 500;

    private readonly HrisClient _client;
    private readonly PunchQueue _queue;
    private readonly TemplateCache _cache;
    private readonly TemplateMatcher _matcher;
    private readonly TimeProvider _time;
    private readonly KioskLog _log;
    private readonly SemaphoreSlim _wake = new(0, 1);
    private readonly CancellationTokenSource _stopping = new();

    private Task? _loop;
    private string? _templatesETag;
    private DateTimeOffset? _templatesRefreshedAt;
    private volatile bool _templatesRequested = true;

    public SyncWorker(HrisClient client, PunchQueue queue, TemplateCache cache, TemplateMatcher matcher, TimeProvider time, KioskLog log)
    {
        _client = client;
        _queue = queue;
        _cache = cache;
        _matcher = matcher;
        _time = time;
        _log = log;
        Status = new KioskStatus(false, false, null, queue.Count(), queue.RejectedCount(), 0, null, null, null);
    }

    /// <summary>Raised on a background thread whenever the status is refreshed.</summary>
    public event Action<KioskStatus>? StatusChanged;

    public KioskStatus Status { get; private set; }

    /// <summary>Loads the templates saved at the last refresh, so matching works before the HRIS answers.</summary>
    public void LoadCachedTemplates()
    {
        var cached = _cache.Load();

        if (cached is null)
        {
            return;
        }

        _matcher.Load(cached.Fingerprints);
        _templatesETag = cached.ETag;
        Publish(Status with { EnrolledFingers = _matcher.Count });
        _log.Info($"Loaded {_matcher.Count} cached fingerprint templates.");
    }

    public void Start() => _loop ??= Task.Run(() => LoopAsync(_stopping.Token));

    /// <summary>Send queued punches now instead of at the next check-in.</summary>
    public void SyncSoon()
    {
        try
        {
            _wake.Release();
        }
        catch (SemaphoreFullException)
        {
            // A run is already pending.
        }
    }

    /// <summary>Fetch the enrolled fingers now (after an enrollment).</summary>
    public void RefreshTemplatesSoon()
    {
        _templatesRequested = true;
        SyncSoon();
    }

    /// <summary>One check-in, punch upload and (if due) template refresh. Never throws for HRIS problems.</summary>
    internal async Task RunOnceAsync(CancellationToken cancellationToken)
    {
        var status = Status;

        try
        {
            var heartbeat = await _client.HeartbeatAsync(cancellationToken);
            status = status with
            {
                IsOnline = true,
                IsKioskRejected = false,
                Problem = null,
                ClockDrift = _time.GetUtcNow() - heartbeat.ServerTime,
                LastContact = _time.GetUtcNow(),
                DeviceName = heartbeat.Device.Name,
            };

            await SendQueuedPunchesAsync(cancellationToken);

            if (_templatesRequested || _templatesRefreshedAt is null || _time.GetUtcNow() - _templatesRefreshedAt >= TemplateRefreshInterval)
            {
                await RefreshTemplatesAsync(cancellationToken);
            }
        }
        catch (HrisApiException exception) when (exception.IsKioskRejected)
        {
            status = status with { IsOnline = false, IsKioskRejected = true, Problem = exception.Message };
            _log.Warning($"Kiosk rejected by the HRIS: {exception.Message}");
        }
        catch (HrisApiException exception) when (exception.IsUnreachable)
        {
            status = status with { IsOnline = false, IsKioskRejected = false, Problem = exception.Message };
        }
        catch (HrisApiException exception)
        {
            status = status with { Problem = exception.Message };
            _log.Error("HRIS request failed", exception);
        }

        Publish(status with
        {
            QueuedPunches = _queue.Count(),
            RejectedPunches = _queue.RejectedCount(),
            EnrolledFingers = _matcher.Count,
        });
    }

    public async ValueTask DisposeAsync()
    {
        await _stopping.CancelAsync();

        if (_loop is not null)
        {
            try
            {
                await _loop;
            }
            catch (OperationCanceledException)
            {
            }
        }

        _stopping.Dispose();
        _wake.Dispose();
    }

    private async Task LoopAsync(CancellationToken cancellationToken)
    {
        while (!cancellationToken.IsCancellationRequested)
        {
            try
            {
                await RunOnceAsync(cancellationToken);
            }
            catch (OperationCanceledException)
            {
                return;
            }
            catch (Exception exception)
            {
                // Anything unexpected (e.g. the queue file) is logged; the loop keeps going.
                _log.Error("Sync run failed", exception);
            }

            try
            {
                await _wake.WaitAsync(CheckInInterval, cancellationToken);
            }
            catch (OperationCanceledException)
            {
                return;
            }
        }
    }

    private async Task SendQueuedPunchesAsync(CancellationToken cancellationToken)
    {
        while (true)
        {
            var batch = _queue.Peek(BatchSize);

            if (batch.Count == 0)
            {
                return;
            }

            try
            {
                var receipt = await _client.SendPunchesAsync(batch, cancellationToken);
                _queue.Remove(batch);
                _log.Info($"Sent {receipt.Received} punches ({receipt.Inserted} new, {receipt.Duplicates} already stored, {receipt.Flagged} flagged).");
            }
            catch (HrisApiException exception) when (exception.IsValidationError)
            {
                await SendOneByOneAsync(batch, cancellationToken);
            }
        }
    }

    /// <summary>After a refused batch, find the invalid punches so the rest still get through.</summary>
    private async Task SendOneByOneAsync(IReadOnlyList<QueuedPunch> batch, CancellationToken cancellationToken)
    {
        foreach (var punch in batch)
        {
            try
            {
                await _client.SendPunchesAsync([punch], cancellationToken);
                _queue.Remove([punch]);
            }
            catch (HrisApiException exception) when (exception.IsValidationError)
            {
                _queue.Reject([punch], exception.Message, _time.GetLocalNow());
                _log.Warning($"Punch #{punch.Id} rejected by the HRIS: {exception.Message}");
            }
        }
    }

    private async Task RefreshTemplatesAsync(CancellationToken cancellationToken)
    {
        _templatesRequested = false;
        var feed = await _client.GetTemplatesAsync(_templatesETag, cancellationToken);
        _templatesRefreshedAt = _time.GetUtcNow();

        if (feed is null)
        {
            return;
        }

        var skipped = _matcher.Load(feed.Fingerprints);
        _cache.Save(feed);
        _templatesETag = feed.ETag;
        _log.Info($"Loaded {_matcher.Count} fingerprint templates from the HRIS{(skipped > 0 ? $"; {skipped} could not be read" : "")}.");
    }

    private void Publish(KioskStatus status)
    {
        Status = status;
        StatusChanged?.Invoke(status);
    }
}

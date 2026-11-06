using System.Net;
using KagjanawKiosk.Matching;
using KagjanawKiosk.Sync;

namespace KagjanawKiosk.Tests;

public class SyncWorkerTests : IDisposable
{
    private static readonly TimeSpan Manila = TimeSpan.FromHours(8);
    private const string Heartbeat = """{"device":{"id":1,"name":"Lobby Kiosk","location":null},"server_time":"2026-11-06T08:00:00+08:00"}""";

    private readonly TemporaryFolder _folder = new();
    private readonly FixedTime _time = new(new DateTimeOffset(2026, 11, 6, 8, 0, 30, Manila));

    public void Dispose() => _folder.Dispose();

    private (SyncWorker Worker, PunchQueue Queue, TemplateMatcher Matcher, TemplateCache Cache) Create(StubHttpHandler handler)
    {
        var queue = new PunchQueue(_folder.File("queue.db"));
        var matcher = new TemplateMatcher(40);
        var cache = new TemplateCache(_folder.File("templates.dat"));
        var worker = new SyncWorker(new HrisClient("http://hris.local", "token", handler), queue, cache, matcher, _time, new KioskLog(_folder.Path, _time));

        return (worker, queue, matcher, cache);
    }

    private static string TemplatesJson(params (int EmployeeId, int Seed)[] fingers) =>
        "{\"data\":[" + string.Join(",", fingers.Select(finger =>
            $"{{\"employee_id\":{finger.EmployeeId},\"biometric_user_id\":\"10{finger.EmployeeId:00}\",\"employee_name\":\"Employee {finger.EmployeeId}\",\"finger\":\"right-index\",\"template\":\"{TemplateMatcher.Serialize(TemplateMatcher.Extract(SyntheticFingerprint.Create(finger.Seed)))}\"}}")) + "]}";

    [Fact]
    public async Task Sends_queued_punches_and_loads_the_enrolled_fingers()
    {
        var templates = TemplatesJson((1, 1), (2, 2));
        var handler = new StubHttpHandler((request, _) => request.RequestUri!.AbsolutePath switch
        {
            "/api/kiosk/v1/heartbeat" => StubHttpHandler.Json(HttpStatusCode.OK, Heartbeat),
            "/api/kiosk/v1/punches" => StubHttpHandler.Json(HttpStatusCode.OK, """{"received":2,"inserted":2,"duplicates":0,"flagged":0}"""),
            "/api/kiosk/v1/templates" => StubHttpHandler.Json(HttpStatusCode.OK, templates),
            _ => new HttpResponseMessage(HttpStatusCode.NotFound),
        });
        var (worker, queue, matcher, cache) = Create(handler);
        queue.Enqueue("1001", new DateTimeOffset(2026, 11, 6, 7, 58, 0, Manila));
        queue.Enqueue("1002", new DateTimeOffset(2026, 11, 6, 7, 59, 0, Manila));

        await worker.RunOnceAsync(CancellationToken.None);

        Assert.Equal(0, queue.Count());
        Assert.Equal(2, matcher.Count);
        Assert.Equal(2, cache.Load()!.Fingerprints.Count);
        Assert.True(worker.Status.IsOnline);
        Assert.Equal("Lobby Kiosk", worker.Status.DeviceName);
        Assert.Equal(TimeSpan.FromSeconds(30), worker.Status.ClockDrift);
        Assert.False(worker.Status.HasClockDrift);
    }

    [Fact]
    public async Task Keeps_punches_queued_while_the_hris_is_unreachable()
    {
        var handler = new StubHttpHandler((_, _) => throw new HttpRequestException("Connection refused"));
        var (worker, queue, _, _) = Create(handler);
        queue.Enqueue("1001", new DateTimeOffset(2026, 11, 6, 7, 58, 0, Manila));

        await worker.RunOnceAsync(CancellationToken.None);

        Assert.Equal(1, queue.Count());
        Assert.False(worker.Status.IsOnline);
        Assert.False(worker.Status.IsKioskRejected);
        Assert.Equal(1, worker.Status.QueuedPunches);
    }

    [Fact]
    public async Task Sets_aside_only_the_punch_the_hris_refuses()
    {
        var handler = new StubHttpHandler((request, body) => request.RequestUri!.AbsolutePath switch
        {
            "/api/kiosk/v1/heartbeat" => StubHttpHandler.Json(HttpStatusCode.OK, Heartbeat),
            "/api/kiosk/v1/punches" when body!.Contains("\"BAD\"") => StubHttpHandler.Json(HttpStatusCode.UnprocessableEntity, """{"message":"Invalid.","errors":{"punches.0.biometric_user_id":["The punches.0.biometric_user_id field must not be greater than 20 characters."]}}"""),
            "/api/kiosk/v1/punches" => StubHttpHandler.Json(HttpStatusCode.OK, """{"received":1,"inserted":1,"duplicates":0,"flagged":0}"""),
            _ => new HttpResponseMessage(HttpStatusCode.NotModified),
        });
        var (worker, queue, _, _) = Create(handler);
        queue.Enqueue("1001", new DateTimeOffset(2026, 11, 6, 7, 58, 0, Manila));
        queue.Enqueue("BAD", new DateTimeOffset(2026, 11, 6, 7, 59, 0, Manila));

        await worker.RunOnceAsync(CancellationToken.None);

        Assert.Equal(0, queue.Count());
        Assert.Equal(1, queue.RejectedCount());
        Assert.Equal(1, worker.Status.RejectedPunches);
    }

    [Fact]
    public async Task Reports_a_kiosk_the_hris_no_longer_accepts()
    {
        var handler = new StubHttpHandler((_, _) => StubHttpHandler.Json(HttpStatusCode.Forbidden, """{"message":"This kiosk is disabled in the HRIS."}"""));
        var (worker, _, _, _) = Create(handler);

        await worker.RunOnceAsync(CancellationToken.None);

        Assert.True(worker.Status.IsKioskRejected);
        Assert.Equal("This kiosk is disabled in the HRIS.", worker.Status.Problem);
    }

    [Fact]
    public async Task Flags_a_kiosk_clock_more_than_two_minutes_off()
    {
        _time.Now = new DateTimeOffset(2026, 11, 6, 8, 5, 0, Manila);
        var handler = new StubHttpHandler((request, _) => request.RequestUri!.AbsolutePath == "/api/kiosk/v1/heartbeat"
            ? StubHttpHandler.Json(HttpStatusCode.OK, Heartbeat)
            : new HttpResponseMessage(HttpStatusCode.NotModified));
        var (worker, _, _, _) = Create(handler);

        await worker.RunOnceAsync(CancellationToken.None);

        Assert.True(worker.Status.HasClockDrift);
    }

    [Fact]
    public async Task Matches_from_the_cached_list_after_a_restart_without_the_hris()
    {
        var templates = TemplatesJson((1, 1));
        var online = new StubHttpHandler((request, _) => request.RequestUri!.AbsolutePath switch
        {
            "/api/kiosk/v1/heartbeat" => StubHttpHandler.Json(HttpStatusCode.OK, Heartbeat),
            _ => StubHttpHandler.Json(HttpStatusCode.OK, templates),
        });
        await Create(online).Worker.RunOnceAsync(CancellationToken.None);

        var (restarted, _, matcher, _) = Create(new StubHttpHandler((_, _) => throw new HttpRequestException("offline")));
        restarted.LoadCachedTemplates();

        Assert.Equal(1, matcher.Count);
        Assert.Equal(1, matcher.Identify(TemplateMatcher.Extract(SyntheticFingerprint.Create(1)))?.Fingerprint.EmployeeId);
    }
}

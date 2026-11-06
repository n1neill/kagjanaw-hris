using KagjanawKiosk.Configuration;
using KagjanawKiosk.Matching;
using KagjanawKiosk.Scanning;
using KagjanawKiosk.Sync;

namespace KagjanawKiosk;

/// <summary>Everything the kiosk's screens share, wired up once at start-up.</summary>
public sealed class KioskApp : IAsyncDisposable
{
    private KioskApp(KioskPaths paths, KioskSettings settings, string token, KioskLog log, IFingerprintScanner scanner)
    {
        Paths = paths;
        Settings = settings;
        Log = log;
        Scanner = scanner;
        Matcher = new TemplateMatcher(settings.MatchThreshold);
        Queue = new PunchQueue(paths.QueueDatabase);
        Client = new HrisClient(settings.HrisBaseUrl, token);
        Worker = new SyncWorker(Client, Queue, new TemplateCache(paths.TemplateCacheFile), Matcher, Time, log);
    }

    public KioskPaths Paths { get; }

    public KioskSettings Settings { get; }

    public KioskLog Log { get; }

    public TimeProvider Time => TimeProvider.System;

    public IFingerprintScanner Scanner { get; }

    public TemplateMatcher Matcher { get; }

    public PunchQueue Queue { get; }

    public HrisClient Client { get; }

    public SyncWorker Worker { get; }

    /// <exception cref="ScannerUnavailableException">The configured reader cannot be opened.</exception>
    public static KioskApp Start(KioskPaths paths, KioskSettings settings, string token, KioskLog log)
    {
        var app = new KioskApp(paths, settings, token, log, ScannerFactory.Create(settings, log));
        app.Worker.LoadCachedTemplates();
        app.Worker.Start();
        log.Info($"Kiosk started: HRIS {settings.HrisBaseUrl}, {app.Scanner.Description}, match threshold {settings.MatchThreshold}.");

        return app;
    }

    public async ValueTask DisposeAsync()
    {
        await Worker.DisposeAsync();
        Client.Dispose();
        Scanner.Dispose();
        Log.Info("Kiosk stopped.");
    }
}

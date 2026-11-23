using KagjanawKiosk.Configuration;
using KagjanawKiosk.Forms;
using KagjanawKiosk.Scanning;
using KagjanawKiosk.Sync;

namespace KagjanawKiosk;

internal static class Program
{
    /// <summary>Set when the settings were changed, so the kiosk starts again with them.</summary>
    public static bool RestartRequested { get; set; }

    /// <summary>
    /// Starts the kiosk. Switches: --windowed (a normal window, for development)
    /// and --setup (open the settings first).
    /// </summary>
    [STAThread]
    private static void Main(string[] args)
    {
        using var singleInstance = new Mutex(initiallyOwned: true, @"Local\HrisFingerprintKiosk", out var isFirstInstance);

        if (!isFirstInstance)
        {
            MessageBox.Show("The fingerprint kiosk is already running.", "HRIS Fingerprint Kiosk", MessageBoxButtons.OK, MessageBoxIcon.Information);

            return;
        }

        ApplicationConfiguration.Initialize();

        var paths = KioskPaths.ForCurrentUser();
        var log = new KioskLog(paths.LogDirectory, TimeProvider.System);
        var forceSetup = args.Contains("--setup", StringComparer.OrdinalIgnoreCase);
        string? setupReason = null;
        var connectionVerified = false;

        Application.ThreadException += (_, e) => log.Error("Unhandled UI error", e.Exception);

        while (true)
        {
            var settings = KioskSettings.Load(paths);
            settings.FullScreen = !args.Contains("--windowed", StringComparer.OrdinalIgnoreCase);
            var token = KioskSettings.LoadToken(paths);

            if (forceSetup || setupReason is not null || !settings.IsComplete(token))
            {
                using var setup = new SetupForm(paths, settings, token, setupReason);

                if (setup.ShowDialog() != DialogResult.OK)
                {
                    return;
                }

                forceSetup = false;
                setupReason = null;
                connectionVerified = true;
                continue;
            }

            if (!connectionVerified)
            {
                setupReason = CheckConnection(settings, token!, log);

                if (setupReason is not null)
                {
                    continue;
                }
            }

            connectionVerified = false;
            KioskApp app;

            try
            {
                app = KioskApp.Start(paths, settings, token!, log);
            }
            catch (ScannerUnavailableException exception)
            {
                log.Error("Scanner could not be opened", exception);
                setupReason = exception.Message;
                continue;
            }

            RestartRequested = false;

            using (var clockIn = new ClockInForm(app))
            {
                Application.Run(clockIn);
            }

            // Off the UI thread: awaiting there while blocking it would deadlock.
            Task.Run(() => app.DisposeAsync().AsTask()).GetAwaiter().GetResult();

            if (!RestartRequested)
            {
                return;
            }

            // A restart follows saved settings, which the settings screen just tested.
            connectionVerified = true;
        }
    }

    /// <returns>Why the kiosk cannot connect to the HRIS, or null when it can.</returns>
    private static string? CheckConnection(KioskSettings settings, string token, KioskLog log)
    {
        using var client = new HrisClient(settings.HrisBaseUrl, token);

        // Off the UI thread, as with DisposeAsync below.
        var problem = Task.Run(() => ConnectionCheck.FindProblemAsync(client)).GetAwaiter().GetResult();

        if (problem is not null)
        {
            log.Warning($"Opening the settings first: {problem}");
        }

        return problem;
    }
}

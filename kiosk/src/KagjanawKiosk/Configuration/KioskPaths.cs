namespace KagjanawKiosk.Configuration;

/// <summary>
/// Where the kiosk keeps its files: %LOCALAPPDATA%\KagjanawKiosk for the
/// Windows account the kiosk runs under. Secrets and the template cache in
/// this folder are encrypted with that account's Windows key (DPAPI).
/// </summary>
public sealed class KioskPaths
{
    public KioskPaths(string root)
    {
        Root = root;
        Directory.CreateDirectory(Root);
        Directory.CreateDirectory(LogDirectory);
    }

    /// <summary>The data folder, or the one named by the KAGJANAW_KIOSK_HOME environment variable (for testing).</summary>
    public static KioskPaths ForCurrentUser() =>
        new(Environment.GetEnvironmentVariable("KAGJANAW_KIOSK_HOME") is { Length: > 0 } home
            ? home
            : Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "KagjanawKiosk"));

    public string Root { get; }

    public string SettingsFile => Path.Combine(Root, "settings.json");

    public string TokenFile => Path.Combine(Root, "kiosk-token.dat");

    public string TemplateCacheFile => Path.Combine(Root, "templates.dat");

    public string QueueDatabase => Path.Combine(Root, "punch-queue.db");

    public string LogDirectory => Path.Combine(Root, "logs");

    public string DefaultScanInbox => Path.Combine(Root, "scan-inbox");
}

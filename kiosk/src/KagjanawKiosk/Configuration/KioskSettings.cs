using System.Text;
using System.Text.Json;

namespace KagjanawKiosk.Configuration;

/// <summary>Which fingerprint reader the kiosk uses.</summary>
public enum ScannerKind
{
    /// <summary>No hardware: fingerprint images dropped into a folder count as scans (testing and demos).</summary>
    Folder,

    /// <summary>A DigitalPersona U.are.U reader through the vendor SDK.</summary>
    DigitalPersona,
}

/// <summary>
/// The kiosk's settings (settings.json) plus its token, which is kept
/// separately in a DPAPI-encrypted file.
/// </summary>
public sealed class KioskSettings
{
    /// <summary>SourceAFIS score for a match; 40 is the recommended threshold (about 0.01% false matches).</summary>
    public const double DefaultMatchThreshold = 40;

    private static readonly JsonSerializerOptions JsonOptions = new() { WriteIndented = true };

    /// <summary>The HRIS address, e.g. http://192.168.1.10 or https://kagjanaw-hris.test.</summary>
    public string HrisBaseUrl { get; set; } = "";

    public ScannerKind Scanner { get; set; } = ScannerKind.Folder;

    /// <summary>The folder watched by the folder scanner.</summary>
    public string ScanInboxFolder { get; set; } = "";

    /// <summary>Resolution of the reader's images. The U.are.U 4500 captures at 512 dpi; most sample databases are 500 dpi.</summary>
    public int ScannerDpi { get; set; } = 500;

    public double MatchThreshold { get; set; } = DefaultMatchThreshold;

    /// <summary>Full screen and always on top. Turned off with the --windowed switch for development.</summary>
    public bool FullScreen { get; set; } = true;

    public bool IsComplete(string? token) =>
        Uri.TryCreate(HrisBaseUrl, UriKind.Absolute, out var uri)
        && (uri.Scheme == Uri.UriSchemeHttp || uri.Scheme == Uri.UriSchemeHttps)
        && !string.IsNullOrWhiteSpace(token);

    public static KioskSettings Load(KioskPaths paths)
    {
        KioskSettings settings;

        try
        {
            settings = File.Exists(paths.SettingsFile)
                ? JsonSerializer.Deserialize<KioskSettings>(File.ReadAllText(paths.SettingsFile), JsonOptions) ?? new KioskSettings()
                : new KioskSettings();
        }
        catch (JsonException)
        {
            settings = new KioskSettings();
        }

        if (string.IsNullOrWhiteSpace(settings.ScanInboxFolder))
        {
            settings.ScanInboxFolder = paths.DefaultScanInbox;
        }

        return settings;
    }

    public void Save(KioskPaths paths) =>
        File.WriteAllText(paths.SettingsFile, JsonSerializer.Serialize(this, JsonOptions));

    public static string? LoadToken(KioskPaths paths)
    {
        var bytes = ProtectedFile.Read(paths.TokenFile);

        return bytes is null ? null : Encoding.UTF8.GetString(bytes);
    }

    public static void SaveToken(KioskPaths paths, string token) =>
        ProtectedFile.Write(paths.TokenFile, Encoding.UTF8.GetBytes(token.Trim()));
}

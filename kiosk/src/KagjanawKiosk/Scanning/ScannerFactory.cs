using KagjanawKiosk.Configuration;

namespace KagjanawKiosk.Scanning;

public static class ScannerFactory
{
    /// <exception cref="ScannerUnavailableException">The configured reader cannot be used on this PC.</exception>
    public static IFingerprintScanner Create(KioskSettings settings, KioskLog? log = null) => settings.Scanner switch
    {
        ScannerKind.Folder => new FolderScanner(settings.ScanInboxFolder, settings.ScannerDpi),
        ScannerKind.DigitalPersona => new DigitalPersonaScanner(settings.ScannerDpi, log),
        _ => throw new ScannerUnavailableException($"Unknown scanner {settings.Scanner}."),
    };
}

using KagjanawKiosk.Configuration;

namespace KagjanawKiosk.Scanning;

public static class ScannerFactory
{
    /// <exception cref="ScannerUnavailableException">The configured reader cannot be used on this PC.</exception>
    public static IFingerprintScanner Create(KioskSettings settings) => settings.Scanner switch
    {
        ScannerKind.Folder => new FolderScanner(settings.ScanInboxFolder, settings.ScannerDpi),
        ScannerKind.DigitalPersona => throw new ScannerUnavailableException(
            "The DigitalPersona reader needs the DigitalPersona SDK, which is not installed in this build yet. Use the folder scanner for now."),
        _ => throw new ScannerUnavailableException($"Unknown scanner {settings.Scanner}."),
    };
}

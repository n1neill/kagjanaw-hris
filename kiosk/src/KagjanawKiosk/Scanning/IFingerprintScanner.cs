namespace KagjanawKiosk.Scanning;

/// <summary>
/// One fingerprint image from the reader: 8-bit grayscale, black ridges on a
/// white background, rows from top-left to bottom-right.
/// </summary>
public sealed record FingerprintCapture(int Width, int Height, byte[] Pixels, int Dpi);

/// <summary>
/// A fingerprint reader. The kiosk only needs images from it: matching is
/// done by SourceAFIS, so any reader (or SDK) that yields an image will do.
/// </summary>
public interface IFingerprintScanner : IDisposable
{
    /// <summary>Shown on the settings screen and in the log.</summary>
    string Description { get; }

    /// <summary>Waits until a finger is placed on the reader and returns its image.</summary>
    /// <exception cref="ScannerUnavailableException">The reader is unplugged or not working.</exception>
    /// <exception cref="OperationCanceledException">Waiting was cancelled.</exception>
    Task<FingerprintCapture> CaptureAsync(CancellationToken cancellationToken);
}

public sealed class ScannerUnavailableException(string message, Exception? inner = null) : Exception(message, inner);

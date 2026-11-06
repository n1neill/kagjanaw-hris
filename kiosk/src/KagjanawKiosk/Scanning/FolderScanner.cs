using System.Drawing;
using System.Drawing.Imaging;
using System.Runtime.InteropServices;

namespace KagjanawKiosk.Scanning;

/// <summary>
/// A stand-in reader for testing without hardware. Copying a fingerprint
/// image (PNG, BMP, JPEG or TIFF) into the inbox folder counts as placing a
/// finger; the file is then moved to the "used" subfolder so it can be
/// copied in again.
/// </summary>
public sealed class FolderScanner : IFingerprintScanner
{
    private static readonly string[] ImageExtensions = [".png", ".bmp", ".jpg", ".jpeg", ".tif", ".tiff"];
    private static readonly TimeSpan PollInterval = TimeSpan.FromMilliseconds(250);

    private readonly string _inbox;
    private readonly string _usedFolder;
    private readonly int _dpi;

    public FolderScanner(string inbox, int dpi)
    {
        _inbox = inbox;
        _usedFolder = Path.Combine(inbox, "used");
        _dpi = dpi;
        Directory.CreateDirectory(_usedFolder);
    }

    public string Description => $"Folder scanner (test images in {_inbox})";

    public async Task<FingerprintCapture> CaptureAsync(CancellationToken cancellationToken)
    {
        while (true)
        {
            cancellationToken.ThrowIfCancellationRequested();

            var file = NextImage();

            if (file is not null && TryTake(file, out var capture))
            {
                return capture;
            }

            await Task.Delay(PollInterval, cancellationToken);
        }
    }

    public void Dispose()
    {
    }

    private string? NextImage()
    {
        if (!Directory.Exists(_inbox))
        {
            throw new ScannerUnavailableException($"The scan folder {_inbox} does not exist.");
        }

        return new DirectoryInfo(_inbox)
            .EnumerateFiles()
            .Where(file => ImageExtensions.Contains(file.Extension, StringComparer.OrdinalIgnoreCase))
            .OrderBy(file => file.LastWriteTimeUtc)
            .FirstOrDefault()
            ?.FullName;
    }

    /// <summary>Decodes the image, then moves it out of the inbox. A file still being copied is left for the next poll.</summary>
    private bool TryTake(string file, out FingerprintCapture capture)
    {
        try
        {
            capture = Decode(file, _dpi);
        }
        catch (IOException)
        {
            capture = null!;

            return false;
        }
        catch (ArgumentException)
        {
            // Not an image GDI+ can read: set it aside so it does not block the inbox.
            Move(file, Path.Combine(_usedFolder, "unreadable-" + Path.GetFileName(file)));
            capture = null!;

            return false;
        }

        Move(file, Path.Combine(_usedFolder, $"{DateTime.Now:yyyyMMdd-HHmmss-fff}-{Path.GetFileName(file)}"));

        return true;
    }

    private static void Move(string from, string to)
    {
        try
        {
            File.Move(from, to, overwrite: true);
        }
        catch (IOException)
        {
            File.Delete(from);
        }
    }

    /// <summary>Converts any image GDI+ can open into 8-bit grayscale pixels.</summary>
    internal static FingerprintCapture Decode(string file, int dpi)
    {
        using var stream = new FileStream(file, FileMode.Open, FileAccess.Read, FileShare.Read);
        using var bitmap = new Bitmap(stream);

        var area = new Rectangle(0, 0, bitmap.Width, bitmap.Height);
        var data = bitmap.LockBits(area, ImageLockMode.ReadOnly, PixelFormat.Format24bppRgb);

        try
        {
            var stride = Math.Abs(data.Stride);
            var row = new byte[stride];
            var pixels = new byte[bitmap.Width * bitmap.Height];

            for (var y = 0; y < bitmap.Height; y++)
            {
                Marshal.Copy(data.Scan0 + (y * data.Stride), row, 0, stride);

                for (var x = 0; x < bitmap.Width; x++)
                {
                    var blue = row[x * 3];
                    var green = row[(x * 3) + 1];
                    var red = row[(x * 3) + 2];
                    pixels[(y * bitmap.Width) + x] = (byte)(((red * 299) + (green * 587) + (blue * 114)) / 1000);
                }
            }

            return new FingerprintCapture(bitmap.Width, bitmap.Height, pixels, dpi);
        }
        finally
        {
            bitmap.UnlockBits(data);
        }
    }
}

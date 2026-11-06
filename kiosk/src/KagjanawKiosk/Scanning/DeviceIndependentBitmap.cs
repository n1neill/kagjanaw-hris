using System.Buffers.Binary;

namespace KagjanawKiosk.Scanning;

/// <summary>
/// Reads the Windows device-independent bitmap (BITMAPINFOHEADER, palette,
/// pixel rows) that the DigitalPersona runtime makes from a scan, and turns
/// it into the kiosk's 8-bit grayscale capture.
/// </summary>
internal static class DeviceIndependentBitmap
{
    private const int FileHeaderSize = 14;
    private const int InfoHeaderSize = 40;
    private const int UncompressedRgb = 0;
    private const double MetresPerInch = 0.0254;

    /// <param name="fallbackDpi">Used when the bitmap does not state a plausible resolution.</param>
    /// <exception cref="FormatException">Not an uncompressed 8, 24 or 32-bit bitmap.</exception>
    public static FingerprintCapture ToCapture(ReadOnlySpan<byte> bitmap, int fallbackDpi)
    {
        if (bitmap.Length >= FileHeaderSize && bitmap[0] == 'B' && bitmap[1] == 'M')
        {
            bitmap = bitmap[FileHeaderSize..];
        }

        if (bitmap.Length < InfoHeaderSize)
        {
            throw new FormatException("The bitmap is too short.");
        }

        var headerSize = BinaryPrimitives.ReadInt32LittleEndian(bitmap);
        var width = BinaryPrimitives.ReadInt32LittleEndian(bitmap[4..]);
        var height = BinaryPrimitives.ReadInt32LittleEndian(bitmap[8..]);
        var bitsPerPixel = BinaryPrimitives.ReadUInt16LittleEndian(bitmap[14..]);
        var compression = BinaryPrimitives.ReadInt32LittleEndian(bitmap[16..]);
        var pixelsPerMetre = BinaryPrimitives.ReadInt32LittleEndian(bitmap[24..]);
        var coloursUsed = BinaryPrimitives.ReadInt32LittleEndian(bitmap[32..]);

        var bottomUp = height > 0;
        height = Math.Abs(height);

        if (headerSize < InfoHeaderSize || width <= 0 || height == 0 || width > 4096 || height > 4096)
        {
            throw new FormatException($"Unexpected bitmap header ({width}×{height}, header {headerSize} bytes).");
        }

        if (compression != UncompressedRgb || bitsPerPixel is not (8 or 24 or 32))
        {
            throw new FormatException($"Unsupported bitmap: {bitsPerPixel} bits per pixel, compression {compression}.");
        }

        var paletteSize = bitsPerPixel == 8 ? (coloursUsed is > 0 and <= 256 ? coloursUsed : 256) : 0;
        var paletteStart = headerSize;
        var pixelStart = paletteStart + (paletteSize * 4);
        var stride = ((width * bitsPerPixel) + 31) / 32 * 4;

        if (bitmap.Length < pixelStart + (stride * height))
        {
            throw new FormatException("The bitmap's pixel data is incomplete.");
        }

        var grays = new byte[paletteSize];

        for (var index = 0; index < paletteSize; index++)
        {
            var entry = bitmap.Slice(paletteStart + (index * 4), 3);
            grays[index] = Luminance(red: entry[2], green: entry[1], blue: entry[0]);
        }

        var pixels = new byte[width * height];

        for (var y = 0; y < height; y++)
        {
            var row = bitmap.Slice(pixelStart + ((bottomUp ? height - 1 - y : y) * stride), stride);
            var target = pixels.AsSpan(y * width, width);

            for (var x = 0; x < width; x++)
            {
                target[x] = bitsPerPixel switch
                {
                    8 => row[x] < paletteSize ? grays[row[x]] : row[x],
                    24 => Luminance(red: row[(x * 3) + 2], green: row[(x * 3) + 1], blue: row[x * 3]),
                    _ => Luminance(red: row[(x * 4) + 2], green: row[(x * 4) + 1], blue: row[x * 4]),
                };
            }
        }

        var dpi = (int)Math.Round(pixelsPerMetre * MetresPerInch);

        return new FingerprintCapture(width, height, pixels, dpi is >= 250 and <= 1500 ? dpi : fallbackDpi);
    }

    private static byte Luminance(byte red, byte green, byte blue) =>
        (byte)(((red * 299) + (green * 587) + (blue * 114)) / 1000);
}

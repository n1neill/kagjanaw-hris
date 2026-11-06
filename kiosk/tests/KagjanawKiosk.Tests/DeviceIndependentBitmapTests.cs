using System.Buffers.Binary;
using KagjanawKiosk.Scanning;

namespace KagjanawKiosk.Tests;

public class DeviceIndependentBitmapTests
{
    [Fact]
    public void Reads_a_bottom_up_8_bit_bitmap_through_its_palette_and_resolution()
    {
        // 3×2, rows stored bottom row first and padded to 4 bytes; the palette inverts the indexes.
        var palette = Enumerable.Range(0, 256).Select(index => (byte)(255 - index)).ToArray();
        byte[][] storedRows = [[30, 40, 50], [0, 10, 20]];

        var capture = DeviceIndependentBitmap.ToCapture(Bitmap(3, 2, 8, palette, storedRows, pixelsPerMetre: 20157), fallbackDpi: 500);

        Assert.Equal((3, 2, 512), (capture.Width, capture.Height, capture.Dpi));
        Assert.Equal(new byte[] { 255, 245, 235, 225, 215, 205 }, capture.Pixels);
    }

    [Fact]
    public void Reads_a_top_down_24_bit_bitmap_and_uses_the_fallback_resolution_when_none_is_stated()
    {
        byte[][] storedRows = [[0, 0, 0, 255, 255, 255], [0, 0, 255, 255, 0, 0]];

        var capture = DeviceIndependentBitmap.ToCapture(Bitmap(2, -2, 24, null, storedRows, pixelsPerMetre: 0), fallbackDpi: 512);

        Assert.Equal((2, 2, 512), (capture.Width, capture.Height, capture.Dpi));
        Assert.Equal(new byte[] { 0, 255, 76, 29 }, capture.Pixels);
    }

    [Fact]
    public void Skips_a_bitmap_file_header()
    {
        var info = Bitmap(1, 1, 8, Enumerable.Range(0, 256).Select(index => (byte)index).ToArray(), [[7]], pixelsPerMetre: 0);
        var file = new byte[14 + info.Length];
        file[0] = (byte)'B';
        file[1] = (byte)'M';
        info.CopyTo(file, 14);

        Assert.Equal(7, DeviceIndependentBitmap.ToCapture(file, 500).Pixels.Single());
    }

    [Fact]
    public void Rejects_truncated_or_compressed_bitmaps()
    {
        var bitmap = Bitmap(4, 4, 8, new byte[256], [[1, 2, 3, 4], [1, 2, 3, 4], [1, 2, 3, 4], [1, 2, 3, 4]], pixelsPerMetre: 0);

        Assert.Throws<FormatException>(() => DeviceIndependentBitmap.ToCapture(bitmap.AsSpan(0, bitmap.Length - 1), 500));

        BinaryPrimitives.WriteInt32LittleEndian(bitmap.AsSpan(16), 1);
        Assert.Throws<FormatException>(() => DeviceIndependentBitmap.ToCapture(bitmap, 500));
    }

    /// <summary>Builds a BITMAPINFOHEADER bitmap; <paramref name="rows"/> are in storage order.</summary>
    private static byte[] Bitmap(int width, int height, int bitsPerPixel, byte[]? grayPalette, byte[][] rows, int pixelsPerMetre)
    {
        var stride = ((width * bitsPerPixel) + 31) / 32 * 4;
        var paletteBytes = (grayPalette?.Length ?? 0) * 4;
        var bitmap = new byte[40 + paletteBytes + (stride * rows.Length)];
        var header = bitmap.AsSpan();

        BinaryPrimitives.WriteInt32LittleEndian(header, 40);
        BinaryPrimitives.WriteInt32LittleEndian(header[4..], width);
        BinaryPrimitives.WriteInt32LittleEndian(header[8..], height);
        BinaryPrimitives.WriteUInt16LittleEndian(header[12..], 1);
        BinaryPrimitives.WriteUInt16LittleEndian(header[14..], (ushort)bitsPerPixel);
        BinaryPrimitives.WriteInt32LittleEndian(header[24..], pixelsPerMetre);
        BinaryPrimitives.WriteInt32LittleEndian(header[28..], pixelsPerMetre);
        BinaryPrimitives.WriteInt32LittleEndian(header[32..], grayPalette?.Length ?? 0);

        for (var index = 0; index < (grayPalette?.Length ?? 0); index++)
        {
            bitmap[40 + (index * 4)] = bitmap[41 + (index * 4)] = bitmap[42 + (index * 4)] = grayPalette![index];
        }

        for (var row = 0; row < rows.Length; row++)
        {
            rows[row].CopyTo(bitmap, 40 + paletteBytes + (row * stride));
        }

        return bitmap;
    }
}

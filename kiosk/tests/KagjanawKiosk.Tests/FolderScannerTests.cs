using System.Drawing;
using System.Drawing.Imaging;
using KagjanawKiosk.Scanning;

namespace KagjanawKiosk.Tests;

public class FolderScannerTests : IDisposable
{
    private readonly TemporaryFolder _folder = new();

    public void Dispose() => _folder.Dispose();

    [Fact]
    public async Task Treats_an_image_dropped_in_the_folder_as_a_scan()
    {
        using var scanner = new FolderScanner(_folder.Path, dpi: 512);
        using (var bitmap = new Bitmap(4, 2))
        {
            bitmap.SetPixel(0, 0, Color.Black);
            bitmap.SetPixel(1, 0, Color.White);
            bitmap.Save(_folder.File("finger.png"), ImageFormat.Png);
        }

        var capture = await scanner.CaptureAsync(new CancellationTokenSource(TimeSpan.FromSeconds(5)).Token);

        Assert.Equal((4, 2, 512), (capture.Width, capture.Height, capture.Dpi));
        Assert.Equal(0, capture.Pixels[0]);
        Assert.Equal(255, capture.Pixels[1]);
        Assert.False(File.Exists(_folder.File("finger.png")));
        Assert.Single(Directory.GetFiles(Path.Combine(_folder.Path, "used")));
    }

    [Fact]
    public async Task Waits_until_cancelled_when_no_image_arrives()
    {
        using var scanner = new FolderScanner(_folder.Path, dpi: 500);

        await Assert.ThrowsAnyAsync<OperationCanceledException>(() => scanner.CaptureAsync(new CancellationTokenSource(TimeSpan.FromMilliseconds(400)).Token));
    }

    [Fact]
    public async Task Sets_aside_a_file_that_is_not_an_image()
    {
        using var scanner = new FolderScanner(_folder.Path, dpi: 500);
        await File.WriteAllTextAsync(_folder.File("notes.png"), "not really a png");

        await Assert.ThrowsAnyAsync<OperationCanceledException>(() => scanner.CaptureAsync(new CancellationTokenSource(TimeSpan.FromMilliseconds(600)).Token));

        Assert.True(File.Exists(Path.Combine(_folder.Path, "used", "unreadable-notes.png")));
    }
}

using System.Net;
using System.Text;
using KagjanawKiosk.Scanning;

namespace KagjanawKiosk.Tests;

/// <summary>A scratch folder deleted after the test.</summary>
internal sealed class TemporaryFolder : IDisposable
{
    public string Path { get; } = System.IO.Path.Combine(System.IO.Path.GetTempPath(), "kiosk-tests", Guid.NewGuid().ToString("N"));

    public TemporaryFolder() => Directory.CreateDirectory(Path);

    public string File(string name) => System.IO.Path.Combine(Path, name);

    public void Dispose()
    {
        try
        {
            Directory.Delete(Path, recursive: true);
        }
        catch (IOException)
        {
        }
    }
}

/// <summary>Answers HTTP requests from a script and remembers what was sent.</summary>
internal sealed class StubHttpHandler(Func<HttpRequestMessage, string?, HttpResponseMessage> respond) : HttpMessageHandler
{
    public List<(HttpRequestMessage Request, string? Body)> Requests { get; } = [];

    protected override async Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
    {
        var body = request.Content is null ? null : await request.Content.ReadAsStringAsync(cancellationToken);
        Requests.Add((request, body));

        return respond(request, body);
    }

    public static HttpResponseMessage Json(HttpStatusCode status, string json) =>
        new(status) { Content = new StringContent(json, Encoding.UTF8, "application/json") };
}

internal sealed class FixedTime(DateTimeOffset now) : TimeProvider
{
    public DateTimeOffset Now { get; set; } = now;

    public override DateTimeOffset GetUtcNow() => Now.ToUniversalTime();

    public override TimeZoneInfo LocalTimeZone => TimeZoneInfo.CreateCustomTimeZone("Manila", TimeSpan.FromHours(8), "Manila", "Manila");
}

/// <summary>
/// Fingerprint-like images: curved ridges with breaks, so SourceAFIS finds
/// minutiae. Different seeds give different "fingers".
/// </summary>
internal static class SyntheticFingerprint
{
    public static FingerprintCapture Create(int seed, int width = 320, int height = 400)
    {
        var random = new Random(seed);
        var pixels = new byte[width * height];
        var coreX = width * (0.4 + (random.NextDouble() * 0.2));
        var coreY = height * (0.4 + (random.NextDouble() * 0.2));
        var warp = 4 + (random.NextDouble() * 6);
        var breaks = Enumerable.Range(0, 40)
            .Select(_ => (X: random.Next(20, width - 20), Y: random.Next(20, height - 20), R: random.Next(3, 6)))
            .ToArray();

        for (var y = 0; y < height; y++)
        {
            for (var x = 0; x < width; x++)
            {
                var dx = x - coreX;
                var dy = (y - coreY) * 0.8;
                var radius = Math.Sqrt((dx * dx) + (dy * dy)) + (warp * Math.Sin(x / 17.0)) + (warp * Math.Cos(y / 23.0));
                var ridge = Math.Cos(2 * Math.PI * radius / 9.0) > 0;

                foreach (var (bx, by, br) in breaks)
                {
                    if (((x - bx) * (x - bx)) + ((y - by) * (y - by)) < br * br)
                    {
                        ridge = !ridge;
                    }
                }

                pixels[(y * width) + x] = ridge ? (byte)30 : (byte)230;
            }
        }

        return new FingerprintCapture(width, height, pixels, 500);
    }
}

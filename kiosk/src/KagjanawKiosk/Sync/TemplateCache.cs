using System.Text.Json;
using KagjanawKiosk.Configuration;

namespace KagjanawKiosk.Sync;

/// <summary>
/// The last template list received from the HRIS, kept on disk (encrypted
/// with DPAPI) so the kiosk can identify employees right after a restart,
/// even while the HRIS is unreachable.
/// </summary>
public sealed class TemplateCache(string file)
{
    public TemplateFeed? Load()
    {
        var bytes = ProtectedFile.Read(file);

        if (bytes is null)
        {
            return null;
        }

        try
        {
            return JsonSerializer.Deserialize<TemplateFeed>(bytes, HrisClient.Json);
        }
        catch (JsonException)
        {
            return null;
        }
    }

    public void Save(TemplateFeed feed) =>
        ProtectedFile.Write(file, JsonSerializer.SerializeToUtf8Bytes(feed, HrisClient.Json));

    /// <summary>Forgets the list, e.g. when the kiosk is connected to a different HRIS.</summary>
    public void Clear()
    {
        if (File.Exists(file))
        {
            File.Delete(file);
        }
    }
}

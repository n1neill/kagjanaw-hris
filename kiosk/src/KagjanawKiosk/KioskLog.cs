namespace KagjanawKiosk;

/// <summary>
/// A plain daily log file in the kiosk's logs folder. Never given
/// fingerprint data, tokens or passwords.
/// </summary>
public sealed class KioskLog(string directory, TimeProvider time)
{
    private readonly Lock _lock = new();

    public void Info(string message) => Write("INFO", message);

    public void Warning(string message) => Write("WARN", message);

    public void Error(string message, Exception? exception = null) =>
        Write("ERROR", exception is null ? message : $"{message}: {exception.GetType().Name}: {exception.Message}");

    private void Write(string level, string message)
    {
        var now = time.GetLocalNow();
        var line = $"{now:yyyy-MM-dd HH:mm:ss} {level} {message}{Environment.NewLine}";

        try
        {
            lock (_lock)
            {
                File.AppendAllText(Path.Combine(directory, $"kiosk-{now:yyyyMMdd}.log"), line);
            }
        }
        catch (IOException)
        {
            // Logging must never stop a clock-in.
        }
    }
}

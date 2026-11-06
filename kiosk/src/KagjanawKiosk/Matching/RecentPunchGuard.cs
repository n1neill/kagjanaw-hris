namespace KagjanawKiosk.Matching;

/// <summary>
/// Ignores an employee scanning again within a minute of their last punch
/// (a nervous second tap), so it is not sent as a new punch. The HRIS also
/// merges taps within a few minutes; this only keeps the kiosk's screen honest.
/// </summary>
public sealed class RecentPunchGuard(TimeSpan window)
{
    private readonly Dictionary<string, DateTimeOffset> _lastPunches = [];

    /// <summary>When the employee last punched, if that was within the window.</summary>
    public DateTimeOffset? RecentPunch(string biometricUserId, DateTimeOffset now)
    {
        if (_lastPunches.TryGetValue(biometricUserId, out var last) && now - last < window && now >= last)
        {
            return last;
        }

        return null;
    }

    public void Record(string biometricUserId, DateTimeOffset at)
    {
        _lastPunches[biometricUserId] = at;

        foreach (var stale in _lastPunches.Where(entry => at - entry.Value >= window).Select(entry => entry.Key).ToList())
        {
            _lastPunches.Remove(stale);
        }
    }
}

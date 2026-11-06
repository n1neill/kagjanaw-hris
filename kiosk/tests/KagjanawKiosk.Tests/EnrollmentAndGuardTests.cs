using KagjanawKiosk.Matching;

namespace KagjanawKiosk.Tests;

public class EnrollmentScansTests
{
    /// <summary>Scores between scans named "a", "b", "c".</summary>
    private static Func<string, string, double> Scores(double ab, double ac, double bc) => (first, second) =>
        (first + second) switch
        {
            "ab" or "ba" => ab,
            "ac" or "ca" => ac,
            "bc" or "cb" => bc,
            _ => throw new InvalidOperationException(),
        };

    [Fact]
    public void Accepts_consistent_scans_and_keeps_the_one_matching_the_others_best()
    {
        var verdict = EnrollmentScans.Evaluate(["a", "b", "c"], Scores(ab: 60, ac: 90, bc: 70), threshold: 40);

        Assert.True(verdict.IsConsistent);
        Assert.Equal(2, verdict.BestIndex);
        Assert.Equal(60, verdict.WeakestScore);
        Assert.Equal(60, verdict.Quality);
    }

    [Fact]
    public void Rejects_scans_when_any_pair_does_not_match()
    {
        var verdict = EnrollmentScans.Evaluate(["a", "b", "c"], Scores(ab: 80, ac: 85, bc: 12), threshold: 40);

        Assert.False(verdict.IsConsistent);
        Assert.Equal(12, verdict.WeakestScore);
    }

    [Fact]
    public void Caps_quality_at_100()
    {
        var verdict = EnrollmentScans.Evaluate(["a", "b", "c"], Scores(ab: 300, ac: 250, bc: 400), threshold: 40);

        Assert.Equal(100, verdict.Quality);
    }
}

public class RecentPunchGuardTests
{
    private static readonly DateTimeOffset EightAm = new(2026, 11, 6, 8, 0, 0, TimeSpan.FromHours(8));

    [Fact]
    public void Reports_a_repeat_scan_within_the_window()
    {
        var guard = new RecentPunchGuard(TimeSpan.FromMinutes(1));
        guard.Record("1024", EightAm);

        Assert.Equal(EightAm, guard.RecentPunch("1024", EightAm.AddSeconds(40)));
        Assert.Null(guard.RecentPunch("2048", EightAm.AddSeconds(40)));
    }

    [Fact]
    public void Allows_a_new_punch_once_the_window_has_passed()
    {
        var guard = new RecentPunchGuard(TimeSpan.FromMinutes(1));
        guard.Record("1024", EightAm);

        Assert.Null(guard.RecentPunch("1024", EightAm.AddMinutes(1)));
    }
}

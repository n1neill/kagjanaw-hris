namespace KagjanawKiosk.Matching;

/// <summary>The verdict on the scans of one finger taken during enrollment.</summary>
/// <param name="IsConsistent">Every pair of scans matched each other, so they are the same finger placed well.</param>
/// <param name="BestIndex">The scan that matches the others best; it becomes the enrolled template.</param>
/// <param name="WeakestScore">The lowest score between any two scans.</param>
public sealed record EnrollmentVerdict(bool IsConsistent, int BestIndex, double WeakestScore)
{
    /// <summary>The consistency of the scans on the HRIS's 0-100 quality scale.</summary>
    public int Quality => (int)Math.Clamp(Math.Round(WeakestScore), 0, 100);
}

public static class EnrollmentScans
{
    public const int Required = 3;

    /// <summary>
    /// Compares every pair of scans. If any pair scores below the threshold
    /// the finger was placed differently (or a different finger was used),
    /// and HR should start over.
    /// </summary>
    public static EnrollmentVerdict Evaluate<T>(IReadOnlyList<T> scans, Func<T, T, double> score, double threshold)
    {
        if (scans.Count < 2)
        {
            throw new ArgumentException("At least two scans are needed.", nameof(scans));
        }

        var totals = new double[scans.Count];
        var weakest = double.MaxValue;

        for (var i = 0; i < scans.Count; i++)
        {
            for (var j = i + 1; j < scans.Count; j++)
            {
                var pairScore = score(scans[i], scans[j]);
                totals[i] += pairScore;
                totals[j] += pairScore;
                weakest = Math.Min(weakest, pairScore);
            }
        }

        var bestIndex = Array.IndexOf(totals, totals.Max());

        return new EnrollmentVerdict(weakest >= threshold, bestIndex, weakest);
    }
}

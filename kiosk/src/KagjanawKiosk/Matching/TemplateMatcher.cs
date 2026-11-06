using KagjanawKiosk.Scanning;
using SourceAFIS;

namespace KagjanawKiosk.Matching;

/// <summary>An enrolled finger as sent by the HRIS template feed.</summary>
public sealed record EnrolledFingerprint(int EmployeeId, string BiometricUserId, string EmployeeName, string Finger, string Template);

/// <summary>The enrolled finger a scan matched, and how strongly.</summary>
public sealed record FingerprintMatch(EnrolledFingerprint Fingerprint, double Score);

/// <summary>
/// 1:N identification with SourceAFIS against the enrolled fingers held in
/// memory. Loading a new list swaps it in atomically, so matching can carry
/// on while the HRIS sends an update.
/// </summary>
public sealed class TemplateMatcher(double threshold)
{
    private IReadOnlyList<(EnrolledFingerprint Fingerprint, FingerprintTemplate Template)> _candidates = [];

    public double Threshold => threshold;

    public int Count => _candidates.Count;

    /// <summary>Loads the enrolled fingers, skipping any template that cannot be read.</summary>
    /// <returns>How many templates were skipped.</returns>
    public int Load(IEnumerable<EnrolledFingerprint> fingerprints)
    {
        var candidates = new List<(EnrolledFingerprint, FingerprintTemplate)>();
        var skipped = 0;

        foreach (var fingerprint in fingerprints)
        {
            try
            {
                candidates.Add((fingerprint, new FingerprintTemplate(Convert.FromBase64String(fingerprint.Template))));
            }
            catch (Exception)
            {
                // Bad base64 or CBOR (SourceAFIS throws several exception types): skip this
                // one finger rather than lose every employee's.
                skipped++;
            }
        }

        _candidates = candidates;

        return skipped;
    }

    /// <summary>Extracts the template of a scan. Slow (tens of milliseconds); call it off the UI thread.</summary>
    public static FingerprintTemplate Extract(FingerprintCapture capture) =>
        new(new FingerprintImage(capture.Width, capture.Height, capture.Pixels, new FingerprintImageOptions { Dpi = capture.Dpi }));

    public static string Serialize(FingerprintTemplate template) => Convert.ToBase64String(template.ToByteArray());

    /// <summary>The best-scoring enrolled finger at or above the threshold, if any.</summary>
    /// <param name="excludeEmployeeId">Leave this employee's fingers out (when checking a new enrollment for duplicates).</param>
    public FingerprintMatch? Identify(FingerprintTemplate probe, int? excludeEmployeeId = null)
    {
        var matcher = new FingerprintMatcher(probe);
        FingerprintMatch? best = null;

        foreach (var (fingerprint, template) in _candidates)
        {
            if (fingerprint.EmployeeId == excludeEmployeeId)
            {
                continue;
            }

            var score = matcher.Match(template);

            if (score >= threshold && (best is null || score > best.Score))
            {
                best = new FingerprintMatch(fingerprint, score);
            }
        }

        return best;
    }

    public static double Score(FingerprintTemplate probe, FingerprintTemplate candidate) =>
        new FingerprintMatcher(probe).Match(candidate);
}

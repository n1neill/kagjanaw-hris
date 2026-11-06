using KagjanawKiosk.Matching;

namespace KagjanawKiosk.Tests;

public class TemplateMatcherTests
{
    private static EnrolledFingerprint Enrolled(int employeeId, int seed) => new(
        employeeId,
        $"10{employeeId:00}",
        $"Employee {employeeId}",
        "right-index",
        TemplateMatcher.Serialize(TemplateMatcher.Extract(SyntheticFingerprint.Create(seed))));

    [Fact]
    public void Extracts_templates_from_raw_pixels_without_the_image_decoding_library()
    {
        // ImageSharp is deliberately left out of the build; extraction must not need it.
        var template = TemplateMatcher.Extract(SyntheticFingerprint.Create(1));

        Assert.NotEmpty(template.ToByteArray());
    }

    [Fact]
    public void Identifies_the_enrolled_finger_a_scan_matches()
    {
        var matcher = new TemplateMatcher(40);
        matcher.Load([Enrolled(1, seed: 1), Enrolled(2, seed: 2), Enrolled(3, seed: 3)]);

        var match = matcher.Identify(TemplateMatcher.Extract(SyntheticFingerprint.Create(2)));

        Assert.NotNull(match);
        Assert.Equal(2, match.Fingerprint.EmployeeId);
        Assert.True(match.Score >= 40, $"score {match.Score}");
    }

    [Fact]
    public void Reports_no_match_below_the_threshold()
    {
        var matcher = new TemplateMatcher(40);
        matcher.Load([Enrolled(1, seed: 1), Enrolled(2, seed: 2)]);

        Assert.Null(matcher.Identify(TemplateMatcher.Extract(SyntheticFingerprint.Create(99))));
    }

    [Fact]
    public void Leaves_out_the_excluded_employee_when_checking_for_duplicates()
    {
        var matcher = new TemplateMatcher(40);
        matcher.Load([Enrolled(1, seed: 1)]);

        Assert.Null(matcher.Identify(TemplateMatcher.Extract(SyntheticFingerprint.Create(1)), excludeEmployeeId: 1));
    }

    [Fact]
    public void Skips_templates_it_cannot_read()
    {
        var matcher = new TemplateMatcher(40);

        var skipped = matcher.Load([Enrolled(1, seed: 1), new EnrolledFingerprint(2, "1002", "Broken", "left-index", "bm90IGEgdGVtcGxhdGU=")]);

        Assert.Equal(1, skipped);
        Assert.Equal(1, matcher.Count);
    }
}

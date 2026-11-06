namespace KagjanawKiosk.Forms;

/// <summary>Colours and fonts shared by the kiosk's screens (the HRIS's emerald theme).</summary>
internal static class KioskTheme
{
    public static readonly Color Background = Color.FromArgb(6, 78, 59);
    public static readonly Color Surface = Color.FromArgb(4, 47, 46);
    public static readonly Color Text = Color.White;
    public static readonly Color MutedText = Color.FromArgb(167, 243, 208);
    public static readonly Color Success = Color.FromArgb(16, 185, 129);
    public static readonly Color Failure = Color.FromArgb(220, 38, 38);
    public static readonly Color Warning = Color.FromArgb(245, 158, 11);

    public static Font Font(float size, FontStyle style = FontStyle.Regular) => new("Segoe UI", size, style, GraphicsUnit.Point);

    /// <summary>The finger names the HRIS uses (App\Enums\Finger), in the order HR usually enrolls them.</summary>
    public static readonly IReadOnlyList<(string Value, string Label)> Fingers =
    [
        ("right-index", "Right index finger"),
        ("left-index", "Left index finger"),
        ("right-thumb", "Right thumb"),
        ("left-thumb", "Left thumb"),
        ("right-middle", "Right middle finger"),
        ("left-middle", "Left middle finger"),
        ("right-ring", "Right ring finger"),
        ("left-ring", "Left ring finger"),
        ("right-little", "Right little finger"),
        ("left-little", "Left little finger"),
    ];

    public static string FingerLabel(string value) =>
        Fingers.FirstOrDefault(finger => finger.Value == value).Label ?? value;
}

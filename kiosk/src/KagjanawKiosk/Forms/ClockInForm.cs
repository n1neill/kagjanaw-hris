using KagjanawKiosk.Configuration;
using KagjanawKiosk.Matching;
using KagjanawKiosk.Scanning;
using KagjanawKiosk.Sync;

namespace KagjanawKiosk.Forms;

/// <summary>
/// The kiosk's main screen: a clock and the result of each scan. Employees
/// just place a finger; the HRIS works out whether it is a time-in or out.
///
/// HR shortcuts: Ctrl+Shift+E enroll fingerprints, Ctrl+Shift+S settings,
/// Ctrl+Shift+W leave/return to full screen, Ctrl+Shift+Q exit. Each asks for an HR sign-in first.
/// </summary>
internal sealed class ClockInForm : Form
{
    private const string IdlePrompt = "Place your finger on the reader";
    private static readonly TimeSpan ResultShownFor = TimeSpan.FromSeconds(4);

    private readonly KioskApp _app;
    private readonly RecentPunchGuard _recentPunches = new(TimeSpan.FromMinutes(1));

    private readonly Label _device = new() { AutoSize = true, ForeColor = KioskTheme.MutedText, Font = KioskTheme.Font(14) };
    private readonly Label _time = new() { AutoSize = true, ForeColor = KioskTheme.Text, Font = KioskTheme.Font(96, FontStyle.Bold) };
    private readonly Label _date = new() { AutoSize = true, ForeColor = KioskTheme.MutedText, Font = KioskTheme.Font(24) };
    private readonly Label _headline = new() { AutoSize = true, ForeColor = KioskTheme.Text, Font = KioskTheme.Font(36, FontStyle.Bold) };
    private readonly Label _detail = new() { AutoSize = true, ForeColor = KioskTheme.Text, Font = KioskTheme.Font(20) };
    private readonly Panel _result = new() { Dock = DockStyle.Fill, Padding = new Padding(24) };
    private readonly Label _status = new() { Dock = DockStyle.Fill, AutoEllipsis = true, ForeColor = KioskTheme.MutedText, Font = KioskTheme.Font(12), TextAlign = ContentAlignment.MiddleLeft, Padding = new Padding(16, 0, 16, 0) };
    private readonly System.Windows.Forms.Timer _clock = new() { Interval = 1000 };

    private CancellationTokenSource? _capture;
    private Task? _captureLoop;
    private DateTimeOffset _resetResultAt = DateTimeOffset.MaxValue;
    private bool _allowClose;
    private bool _isFullScreen;

    public ClockInForm(KioskApp app)
    {
        _app = app;

        Text = "HRIS Fingerprint Kiosk";
        BackColor = KioskTheme.Background;
        KeyPreview = true;
        StartPosition = FormStartPosition.CenterScreen;
        MinimumSize = new Size(900, 640);

        Size = new Size(1100, 760);
        _isFullScreen = app.Settings.FullScreen;
        ApplyScreenMode();

        var center = new TableLayoutPanel { Dock = DockStyle.Fill, ColumnCount = 1, RowCount = 5 };
        center.RowStyles.Add(new RowStyle(SizeType.Percent, 12));
        center.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        center.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        center.RowStyles.Add(new RowStyle(SizeType.Percent, 88));
        center.RowStyles.Add(new RowStyle(SizeType.Absolute, 44));

        foreach (var label in new[] { _time, _date, _headline, _detail })
        {
            label.Anchor = AnchorStyles.None;
        }

        var resultText = new FlowLayoutPanel { FlowDirection = FlowDirection.TopDown, AutoSize = true, WrapContents = false, Anchor = AnchorStyles.None, BackColor = Color.Transparent };
        resultText.Controls.AddRange([_headline, _detail]);
        var resultCenter = new TableLayoutPanel { Dock = DockStyle.Fill, ColumnCount = 1, RowCount = 1, BackColor = Color.Transparent };
        resultCenter.Controls.Add(resultText);
        _result.Controls.Add(resultCenter);
        _result.Margin = new Padding(48, 24, 48, 24);

        var header = new FlowLayoutPanel { Dock = DockStyle.Fill, Padding = new Padding(16, 16, 16, 0) };
        header.Controls.Add(_device);

        center.Controls.Add(header, 0, 0);
        center.Controls.Add(_time, 0, 1);
        center.Controls.Add(_date, 0, 2);
        center.Controls.Add(_result, 0, 3);
        center.Controls.Add(_status, 0, 4);
        _status.BackColor = KioskTheme.Surface;
        Controls.Add(center);

        _clock.Tick += (_, _) => Tick();
        app.Worker.StatusChanged += status => BeginInvokeIfAlive(() => ShowStatus(status));

        ShowIdle();
        ShowStatus(app.Worker.Status);
        Tick();
    }

    protected override void OnShown(EventArgs e)
    {
        base.OnShown(e);
        _clock.Start();
        StartCapture();
    }

    protected override async void OnKeyDown(KeyEventArgs e)
    {
        base.OnKeyDown(e);

        if (!e.Control || !e.Shift)
        {
            return;
        }

        switch (e.KeyCode)
        {
            case Keys.E:
                e.Handled = true;
                await OpenEnrollmentAsync();
                break;
            case Keys.S:
                e.Handled = true;
                await OpenSettingsAsync();
                break;
            case Keys.Q:
                e.Handled = true;
                await ExitAsync();
                break;
            case Keys.W:
                e.Handled = true;
                await ToggleWindowedAsync();
                break;
        }
    }

    /// <summary>
    /// Leaves full screen so HR can reach other apps (it asks for a sign-in),
    /// or returns to full screen. Only lasts until the kiosk is restarted.
    /// </summary>
    private async Task ToggleWindowedAsync()
    {
        if (_isFullScreen)
        {
            var session = SignIn("Sign in as HR to leave full screen.");

            if (session is null)
            {
                return;
            }

            await EndSessionAsync(session);
        }

        _isFullScreen = !_isFullScreen;
        ApplyScreenMode();
    }

    private void ApplyScreenMode()
    {
        if (_isFullScreen)
        {
            WindowState = FormWindowState.Normal;
            FormBorderStyle = FormBorderStyle.None;
            WindowState = FormWindowState.Maximized;
            TopMost = true;

            return;
        }

        TopMost = false;
        FormBorderStyle = FormBorderStyle.Sizable;
        WindowState = FormWindowState.Normal;
    }

    protected override void OnFormClosing(FormClosingEventArgs e)
    {
        // Full screen, Alt+F4 must not close the kiosk; HR uses Ctrl+Shift+Q.
        if (_isFullScreen && !_allowClose && e.CloseReason == CloseReason.UserClosing)
        {
            e.Cancel = true;

            return;
        }

        _capture?.Cancel();
        base.OnFormClosing(e);
    }

    private void StartCapture()
    {
        _capture = new CancellationTokenSource();
        _captureLoop = CaptureLoopAsync(_capture.Token);
    }

    private async Task StopCaptureAsync()
    {
        if (_capture is null)
        {
            return;
        }

        await _capture.CancelAsync();

        try
        {
            await (_captureLoop ?? Task.CompletedTask);
        }
        catch (OperationCanceledException)
        {
        }

        _capture.Dispose();
        _capture = null;
    }

    private async Task CaptureLoopAsync(CancellationToken cancellationToken)
    {
        while (!cancellationToken.IsCancellationRequested)
        {
            FingerprintCapture capture;

            try
            {
                capture = await _app.Scanner.CaptureAsync(cancellationToken);
            }
            catch (OperationCanceledException)
            {
                return;
            }
            catch (ScannerUnavailableException exception)
            {
                ShowResult("Fingerprint reader not ready", exception.Message, KioskTheme.Failure);
                _app.Log.Error("Reader unavailable", exception);
                await Task.Delay(TimeSpan.FromSeconds(5), cancellationToken).ContinueWith(_ => { }, TaskScheduler.Default);
                continue;
            }

            ShowResult("Checking…", "", KioskTheme.Surface);
            await IdentifyAsync(capture);
        }
    }

    private async Task IdentifyAsync(FingerprintCapture capture)
    {
        FingerprintMatch? match;

        try
        {
            match = await Task.Run(() => _app.Matcher.Identify(TemplateMatcher.Extract(capture)));
        }
        catch (Exception exception)
        {
            // Whatever went wrong with this scan, the kiosk keeps listening for the next one.
            _app.Log.Warning($"Scan could not be read: {exception.GetType().Name}: {exception.Message}");
            ShowResult("Could not read the fingerprint", "Lift your finger and place it flat on the reader again.", KioskTheme.Failure);
            System.Media.SystemSounds.Hand.Play();

            return;
        }

        if (match is null)
        {
            var reason = _app.Matcher.Count == 0
                ? "No fingerprints are enrolled on this kiosk yet. Please see HR."
                : "Try again, or see HR if this keeps happening.";
            ShowResult("Fingerprint not recognized", reason, KioskTheme.Failure);
            System.Media.SystemSounds.Hand.Play();

            return;
        }

        var now = _app.Time.GetLocalNow();
        var employee = match.Fingerprint;

        if (_recentPunches.RecentPunch(employee.BiometricUserId, now) is { } earlier)
        {
            ShowResult(employee.EmployeeName, $"Already recorded at {earlier:h:mm tt}", KioskTheme.Success);

            return;
        }

        try
        {
            _app.Queue.Enqueue(employee.BiometricUserId, now);
        }
        catch (Exception exception)
        {
            _app.Log.Error("Punch could not be saved", exception);
            ShowResult("Punch not saved", "Please write down your time and tell HR.", KioskTheme.Failure);
            System.Media.SystemSounds.Hand.Play();

            return;
        }

        _recentPunches.Record(employee.BiometricUserId, now);
        _app.Log.Info($"Punch queued for biometric ID {employee.BiometricUserId} (score {match.Score:F0}).");
        ShowResult(employee.EmployeeName, $"Recorded at {now:h:mm:ss tt}", KioskTheme.Success);
        System.Media.SystemSounds.Asterisk.Play();
        _app.Worker.SyncSoon();
    }

    private async Task OpenEnrollmentAsync()
    {
        var session = SignIn("Sign in as HR to enroll fingerprints.");

        if (session is null)
        {
            return;
        }

        await StopCaptureAsync();

        try
        {
            using var enrollment = new EnrollmentForm(_app, session) { TopMost = TopMost };
            enrollment.ShowDialog(this);
        }
        finally
        {
            await EndSessionAsync(session);
            ShowIdle();
            StartCapture();
        }
    }

    private async Task OpenSettingsAsync()
    {
        // A rejected token cannot sign anyone in, so settings open without HR then.
        var needsSignIn = _isFullScreen && !_app.Worker.Status.IsKioskRejected;

        if (needsSignIn)
        {
            var session = SignIn("Sign in as HR to change the kiosk settings.");

            if (session is null)
            {
                return;
            }

            await EndSessionAsync(session);
        }

        await StopCaptureAsync();

        using var setup = new SetupForm(_app.Paths, _app.Settings, KioskSettings.LoadToken(_app.Paths));

        if (setup.ShowDialog(this) == DialogResult.OK)
        {
            _allowClose = true;
            Program.RestartRequested = true;
            Close();

            return;
        }

        StartCapture();
    }

    private async Task ExitAsync()
    {
        if (_isFullScreen && !_app.Worker.Status.IsKioskRejected)
        {
            var session = SignIn("Sign in as HR to close the kiosk.");

            if (session is null)
            {
                return;
            }

            await EndSessionAsync(session);
        }

        _allowClose = true;
        Close();
    }

    private EnrollmentSession? SignIn(string purpose)
    {
        using var form = new SignInForm(_app.Client, purpose);

        return form.ShowDialog(this) == DialogResult.OK ? form.Session : null;
    }

    private async Task EndSessionAsync(EnrollmentSession session)
    {
        try
        {
            await _app.Client.EndEnrollmentAsync(session);
        }
        catch (HrisApiException)
        {
            // The session expires on its own.
        }
    }

    private void Tick()
    {
        var now = _app.Time.GetLocalNow();
        _time.Text = now.ToString("h:mm:ss tt");
        _date.Text = now.ToString("dddd, MMMM d, yyyy");

        if (now >= _resetResultAt)
        {
            ShowIdle();
        }
    }

    private void ShowIdle()
    {
        _resetResultAt = DateTimeOffset.MaxValue;
        _result.BackColor = KioskTheme.Surface;
        _headline.Text = IdlePrompt;
        _detail.Text = "Hold it still until your name appears.";
    }

    private void ShowResult(string headline, string detail, Color color)
    {
        _result.BackColor = color;
        _headline.Text = headline;
        _detail.Text = detail;
        _resetResultAt = _app.Time.GetLocalNow() + ResultShownFor;
    }

    private void ShowStatus(KioskStatus status)
    {
        _device.Text = status.DeviceName ?? "Fingerprint kiosk";

        var parts = new List<string>
        {
            status.IsOnline ? "● Connected to the HRIS" : status.IsKioskRejected ? "● Kiosk not accepted by the HRIS" : "● Offline: punches are saved and sent later",
            $"{status.EnrolledFingers} fingerprint{(status.EnrolledFingers == 1 ? "" : "s")} loaded",
        };

        if (status.QueuedPunches > 0)
        {
            parts.Add($"{status.QueuedPunches} punch{(status.QueuedPunches == 1 ? "" : "es")} waiting to send");
        }

        if (status.RejectedPunches > 0)
        {
            parts.Add($"{status.RejectedPunches} refused by the HRIS (see log)");
        }

        if (status.HasClockDrift)
        {
            parts.Add($"⚠ This PC's clock is {status.ClockDrift!.Value.Duration().TotalMinutes:F0} min off the HRIS: fix the Windows time");
        }

        if (!status.IsOnline && status.Problem is not null)
        {
            parts.Add(status.Problem);
        }

        _status.Text = string.Join("   ·   ", parts);
        _status.ForeColor = status.IsOnline && !status.HasClockDrift ? KioskTheme.MutedText : KioskTheme.Warning;
    }

    private void BeginInvokeIfAlive(Action action)
    {
        if (IsHandleCreated && !IsDisposed)
        {
            BeginInvoke(action);
        }
    }
}

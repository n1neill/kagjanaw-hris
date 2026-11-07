using System.Net;
using KagjanawKiosk.Matching;
using KagjanawKiosk.Scanning;
using KagjanawKiosk.Sync;
using SourceAFIS;

namespace KagjanawKiosk.Forms;

/// <summary>
/// HR enrolls an employee's fingers: pick the employee and finger, then scan
/// it three times. The scans must match each other, and the finger must not
/// already belong to another employee. Up to two fingers per employee.
/// </summary>
internal sealed class EnrollmentForm : Form
{
    private const int MaxFingers = 2;

    private readonly KioskApp _app;
    private readonly EnrollmentSession _session;
    private IReadOnlyList<EnrollableEmployee> _employees = [];
    private CancellationTokenSource? _scanning;

    private readonly TextBox _search = new() { Dock = DockStyle.Top, PlaceholderText = "Search by name, employee no. or office" };
    private readonly ListView _list = new() { Dock = DockStyle.Fill, View = View.Details, FullRowSelect = true, MultiSelect = false, HideSelection = false };
    private readonly Label _employee = new() { AutoSize = true, Font = KioskTheme.Font(16, FontStyle.Bold), MaximumSize = new Size(460, 0) };
    private readonly Label _enrolled = new() { AutoSize = true, MaximumSize = new Size(460, 0) };
    private readonly ComboBox _finger = new() { Width = 280, DropDownStyle = ComboBoxStyle.DropDownList };
    private readonly Button _scan = new() { Text = "Scan finger", AutoSize = true, Font = KioskTheme.Font(12, FontStyle.Bold) };
    private readonly Button _cancelScan = new() { Text = "Cancel scan", AutoSize = true, Enabled = false };
    private readonly Label _progress = new() { AutoSize = true, Font = KioskTheme.Font(14), MaximumSize = new Size(460, 0) };

    public EnrollmentForm(KioskApp app, EnrollmentSession session)
    {
        _app = app;
        _session = session;

        Text = $"Enroll fingerprints, signed in as {session.User.Name}";
        StartPosition = FormStartPosition.CenterScreen;
        Size = new Size(1080, 680);
        MinimumSize = new Size(900, 560);
        Font = KioskTheme.Font(10.5f);
        TopMost = app.Settings.FullScreen;
        ShowInTaskbar = false;

        _list.Columns.Add("Employee", 260);
        _list.Columns.Add("Employee no.", 110);
        _list.Columns.Add("Office", 170);
        _list.Columns.Add("Fingers", 70);
        _list.SelectedIndexChanged += (_, _) => ShowSelected();
        _search.TextChanged += (_, _) => Fill();

        foreach (var (_, label) in KioskTheme.Fingers)
        {
            _finger.Items.Add(label);
        }

        _scan.Click += async (_, _) => await EnrollSelectedAsync();
        _cancelScan.Click += (_, _) => _scanning?.Cancel();

        var signOut = new Button { Text = "Done (sign out)", AutoSize = true, DialogResult = DialogResult.OK };
        CancelButton = signOut;

        var left = new Panel { Dock = DockStyle.Fill, Padding = new Padding(12) };
        left.Controls.Add(_list);
        left.Controls.Add(_search);

        var right = new FlowLayoutPanel { Dock = DockStyle.Fill, FlowDirection = FlowDirection.TopDown, WrapContents = false, Padding = new Padding(12), AutoScroll = true };
        var buttons = new FlowLayoutPanel { AutoSize = true, Margin = new Padding(0, 12, 0, 12) };
        buttons.Controls.AddRange([_scan, _cancelScan]);
        right.Controls.AddRange([
            _employee,
            _enrolled,
            new Label { Text = "Finger to enroll", AutoSize = true, Margin = new Padding(0, 16, 0, 0) },
            _finger,
            buttons,
            _progress,
            new Label
            {
                AutoSize = true,
                MaximumSize = new Size(460, 0),
                ForeColor = SystemColors.GrayText,
                Margin = new Padding(0, 24, 0, 12),
                Text = "Each finger is scanned three times. Press it flat in the middle of the reader and lift it between scans. "
                    + "Only the fingerprint template is sent to the HRIS, never an image. Remove fingers in the HRIS, on the employee's record.",
            },
            signOut,
        ]);

        // A fixed-width form column beside a flexible list; a SplitContainer's distance
        // is unreliable before the form has its final size and left the form hidden.
        var split = new TableLayoutPanel { Dock = DockStyle.Fill, ColumnCount = 2, RowCount = 1 };
        split.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
        split.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 500));
        split.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
        split.Controls.Add(left, 0, 0);
        split.Controls.Add(right, 1, 0);
        Controls.Add(split);

        ShowSelected();
    }

    protected override async void OnShown(EventArgs e)
    {
        base.OnShown(e);
        await LoadEmployeesAsync();
    }

    protected override void OnFormClosing(FormClosingEventArgs e)
    {
        _scanning?.Cancel();
        base.OnFormClosing(e);
    }

    private async Task LoadEmployeesAsync()
    {
        _progress.Text = "Loading employees…";

        try
        {
            _employees = await _app.Client.GetEmployeesAsync(_session);
            _progress.Text = _employees.Count == 0
                ? "No employees in service have a biometric user ID yet. Set one on the employee's record in the HRIS first."
                : "";
            Fill();
        }
        catch (HrisApiException exception)
        {
            HandleError(exception);
        }
    }

    private void Fill()
    {
        var search = _search.Text.Trim();
        var selectedId = Selected?.Id;

        _list.BeginUpdate();
        _list.Items.Clear();

        foreach (var employee in _employees.Where(employee => search.Length == 0
            || employee.Name.Contains(search, StringComparison.OrdinalIgnoreCase)
            || employee.EmployeeNumber.Contains(search, StringComparison.OrdinalIgnoreCase)
            || (employee.Department?.Contains(search, StringComparison.OrdinalIgnoreCase) ?? false)))
        {
            var item = new ListViewItem([employee.Name, employee.EmployeeNumber, employee.Department ?? "", $"{employee.EnrolledFingers.Count} of {MaxFingers}"]) { Tag = employee };
            _list.Items.Add(item);
            item.Selected = employee.Id == selectedId;
        }

        _list.EndUpdate();
        ShowSelected();
    }

    private EnrollableEmployee? Selected => _list.SelectedItems.Count == 1 ? (EnrollableEmployee)_list.SelectedItems[0].Tag! : null;

    private void ShowSelected()
    {
        var employee = Selected;
        _scan.Enabled = employee is not null && _scanning is null;
        _finger.Enabled = employee is not null && _scanning is null;

        if (employee is null)
        {
            _employee.Text = "Select an employee";
            _enrolled.Text = "";

            return;
        }

        _employee.Text = employee.Name;
        _enrolled.Text = employee.EnrolledFingers.Count == 0
            ? "No fingers enrolled yet."
            : "Enrolled: " + string.Join(", ", employee.EnrolledFingers.Select(KioskTheme.FingerLabel));

        // Suggest the first usual finger not yet enrolled.
        var suggestion = KioskTheme.Fingers.Select((finger, index) => (finger.Value, index))
            .FirstOrDefault(finger => !employee.EnrolledFingers.Contains(finger.Value)).index;
        _finger.SelectedIndex = suggestion;
    }

    private async Task EnrollSelectedAsync()
    {
        if (Selected is not { } employee || _finger.SelectedIndex < 0)
        {
            return;
        }

        var finger = KioskTheme.Fingers[_finger.SelectedIndex];

        if (employee.EnrolledFingers.Count >= MaxFingers && !employee.EnrolledFingers.Contains(finger.Value))
        {
            _progress.Text = $"{employee.Name} already has {MaxFingers} fingers enrolled. Re-enroll one of them, or remove one in the HRIS first.";

            return;
        }

        if (employee.EnrolledFingers.Contains(finger.Value)
            && MessageBox.Show(this, $"Replace the enrolled {finger.Label.ToLowerInvariant()} of {employee.Name}?", Text, MessageBoxButtons.YesNo, MessageBoxIcon.Question) != DialogResult.Yes)
        {
            return;
        }

        _scanning = new CancellationTokenSource();
        _cancelScan.Enabled = true;
        ShowSelected();

        try
        {
            var scans = new List<FingerprintTemplate>();

            for (var scan = 1; scan <= EnrollmentScans.Required; scan++)
            {
                _progress.ForeColor = SystemColors.ControlText;
                _progress.Text = $"Scan {scan} of {EnrollmentScans.Required}: place the {finger.Label.ToLowerInvariant()} on the reader.";
                var capture = await _app.Scanner.CaptureAsync(_scanning.Token);
                scans.Add(await Task.Run(() => TemplateMatcher.Extract(capture)));
            }

            _progress.Text = "Checking the scans…";
            var verdict = await Task.Run(() => EnrollmentScans.Evaluate(scans, TemplateMatcher.Score, _app.Matcher.Threshold));

            if (!verdict.IsConsistent)
            {
                ShowProblem($"The three scans did not match each other (weakest score {verdict.WeakestScore:F0}, needs {_app.Matcher.Threshold:F0}). Scan again, pressing the same finger flat each time.");

                return;
            }

            var best = scans[verdict.BestIndex];

            if (await Task.Run(() => _app.Matcher.Identify(best, excludeEmployeeId: employee.Id)) is { } duplicate)
            {
                ShowProblem($"This finger is already enrolled for {duplicate.Fingerprint.EmployeeName} ({KioskTheme.FingerLabel(duplicate.Fingerprint.Finger).ToLowerInvariant()}). Each finger can belong to one employee only.");

                return;
            }

            _progress.Text = "Saving to the HRIS…";
            var receipt = await _app.Client.EnrollFingerAsync(_session, employee.Id, finger.Value, TemplateMatcher.Serialize(best), verdict.Quality, _scanning.Token);

            _employees = _employees.Select(candidate => candidate.Id == employee.Id ? candidate with { EnrolledFingers = receipt.EnrolledFingers } : candidate).ToList();
            _app.Worker.RefreshTemplatesSoon();
            _app.Log.Info($"Enrolled {finger.Value} for employee #{employee.Id} (consistency {verdict.WeakestScore:F0}).");
            _progress.ForeColor = Color.DarkGreen;
            _progress.Text = $"✔ {finger.Label} enrolled for {employee.Name}. The kiosk recognizes it within a few seconds.";
            System.Media.SystemSounds.Asterisk.Play();
            Fill();
        }
        catch (OperationCanceledException)
        {
            _progress.ForeColor = SystemColors.ControlText;
            _progress.Text = "Scan cancelled.";
        }
        catch (ScannerUnavailableException exception)
        {
            ShowProblem(exception.Message);
        }
        catch (HrisApiException exception)
        {
            HandleError(exception);
        }
        catch (Exception exception)
        {
            ShowProblem("A scan could not be read. Clean the reader and try again.");
            _app.Log.Warning($"Enrollment scan unreadable: {exception.GetType().Name}: {exception.Message}");
        }
        finally
        {
            _scanning.Dispose();
            _scanning = null;
            _cancelScan.Enabled = false;
            ShowSelected();
        }
    }

    private void HandleError(HrisApiException exception)
    {
        if (exception.StatusCode == HttpStatusCode.Unauthorized)
        {
            MessageBox.Show(this, exception.Message, Text, MessageBoxButtons.OK, MessageBoxIcon.Warning);
            DialogResult = DialogResult.Cancel;

            return;
        }

        ShowProblem(exception.Message);
    }

    private void ShowProblem(string message)
    {
        _progress.ForeColor = Color.DarkRed;
        _progress.Text = message;
        System.Media.SystemSounds.Hand.Play();
    }
}

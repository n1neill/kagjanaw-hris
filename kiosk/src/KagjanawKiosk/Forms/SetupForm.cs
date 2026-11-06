using KagjanawKiosk.Configuration;
using KagjanawKiosk.Sync;

namespace KagjanawKiosk.Forms;

/// <summary>
/// Connects the kiosk to the HRIS: the HRIS address, the kiosk token issued
/// under Admin → Biometric Devices, and the fingerprint reader. Saved only
/// after the HRIS accepts the token.
/// </summary>
internal sealed class SetupForm : Form
{
    private readonly KioskPaths _paths;
    private readonly KioskSettings _settings;
    private readonly bool _hasToken;

    private readonly TextBox _url = new() { Width = 420 };
    private readonly TextBox _token = new() { Width = 420, UseSystemPasswordChar = true };
    private readonly ComboBox _scanner = new() { Width = 420, DropDownStyle = ComboBoxStyle.DropDownList };
    private readonly TextBox _inbox = new() { Width = 330 };
    private readonly NumericUpDown _dpi = new() { Width = 100, Minimum = 300, Maximum = 1000 };
    private readonly Label _message = new() { AutoSize = true, MaximumSize = new Size(520, 0) };
    private readonly Button _save = new() { Text = "Test connection and save", AutoSize = true };

    public SetupForm(KioskPaths paths, KioskSettings settings, string? existingToken, string? reason = null)
    {
        _paths = paths;
        _settings = settings;
        _hasToken = !string.IsNullOrWhiteSpace(existingToken);

        Text = "Fingerprint kiosk settings";
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        AutoSize = true;
        AutoSizeMode = AutoSizeMode.GrowAndShrink;
        Padding = new Padding(16);
        Font = KioskTheme.Font(10);
        TopMost = true;

        _url.Text = settings.HrisBaseUrl;
        _token.PlaceholderText = _hasToken ? "Leave blank to keep the current token" : "Paste the token from the HRIS";
        _scanner.Items.AddRange(["Folder of test images (no reader)", "DigitalPersona U.are.U reader"]);
        _scanner.SelectedIndex = settings.Scanner == ScannerKind.DigitalPersona ? 1 : 0;
        _inbox.Text = settings.ScanInboxFolder;
        _dpi.Value = Math.Clamp(settings.ScannerDpi, 300, 1000);

        var browse = new Button { Text = "Browse…", AutoSize = true };
        browse.Click += (_, _) => BrowseForInbox();
        _scanner.SelectedIndexChanged += (_, _) => _inbox.Enabled = browse.Enabled = _scanner.SelectedIndex == 0;
        _inbox.Enabled = browse.Enabled = _scanner.SelectedIndex == 0;

        var cancel = new Button { Text = "Cancel", AutoSize = true, DialogResult = DialogResult.Cancel };
        _save.Click += async (_, _) => await SaveAsync();
        AcceptButton = _save;
        CancelButton = cancel;

        var layout = new TableLayoutPanel { ColumnCount = 2, AutoSize = true, Dock = DockStyle.Fill };
        layout.ColumnStyles.Add(new ColumnStyle(SizeType.AutoSize));
        layout.ColumnStyles.Add(new ColumnStyle(SizeType.AutoSize));

        if (reason is not null)
        {
            var reasonLabel = new Label { Text = reason, AutoSize = true, MaximumSize = new Size(560, 0), ForeColor = Color.DarkRed, Margin = new Padding(0, 0, 0, 12) };
            layout.Controls.Add(reasonLabel);
            layout.SetColumnSpan(reasonLabel, 2);
        }

        AddRow(layout, "HRIS address", _url, "e.g. http://192.168.1.10 or https://kagjanaw-hris.test");
        AddRow(layout, "Kiosk token", _token, "Admin → Biometric Devices → this kiosk → Issue Kiosk Token");
        AddRow(layout, "Fingerprint reader", _scanner, null);

        var inboxRow = new FlowLayoutPanel { AutoSize = true, WrapContents = false, Margin = Padding.Empty };
        inboxRow.Controls.AddRange([_inbox, browse]);
        AddRow(layout, "Test image folder", inboxRow, "Copying an image into this folder counts as a scan.");
        AddRow(layout, "Image resolution (dpi)", _dpi, "For test images (500 for most sample sets). The DigitalPersona reader reports its own.");

        var buttons = new FlowLayoutPanel { AutoSize = true, FlowDirection = FlowDirection.LeftToRight, Margin = new Padding(0, 12, 0, 0) };
        buttons.Controls.AddRange([_save, cancel]);
        layout.Controls.Add(_message);
        layout.SetColumnSpan(_message, 2);
        layout.Controls.Add(buttons);
        layout.SetColumnSpan(buttons, 2);

        Controls.Add(layout);
    }

    private static void AddRow(TableLayoutPanel layout, string label, Control input, string? hint)
    {
        layout.Controls.Add(new Label { Text = label, AutoSize = true, Anchor = AnchorStyles.Left, Margin = new Padding(0, 8, 12, 0) });

        if (hint is null)
        {
            layout.Controls.Add(input);

            return;
        }

        var stack = new FlowLayoutPanel { FlowDirection = FlowDirection.TopDown, AutoSize = true, WrapContents = false, Margin = new Padding(0, 4, 0, 4) };
        stack.Controls.Add(input);
        stack.Controls.Add(new Label { Text = hint, AutoSize = true, ForeColor = SystemColors.GrayText, Font = KioskTheme.Font(8.5f) });
        layout.Controls.Add(stack);
    }

    private void BrowseForInbox()
    {
        using var dialog = new FolderBrowserDialog { SelectedPath = _inbox.Text, Description = "Folder to watch for fingerprint images" };

        if (dialog.ShowDialog(this) == DialogResult.OK)
        {
            _inbox.Text = dialog.SelectedPath;
        }
    }

    private async Task SaveAsync()
    {
        var url = _url.Text.Trim().TrimEnd('/');
        var token = _token.Text.Trim();

        if (token.Length == 0 && _hasToken)
        {
            token = KioskSettings.LoadToken(_paths) ?? "";
        }

        var candidate = new KioskSettings
        {
            HrisBaseUrl = url,
            Scanner = _scanner.SelectedIndex == 1 ? ScannerKind.DigitalPersona : ScannerKind.Folder,
            ScanInboxFolder = _inbox.Text.Trim(),
            ScannerDpi = (int)_dpi.Value,
            MatchThreshold = _settings.MatchThreshold,
            FullScreen = _settings.FullScreen,
        };

        if (!candidate.IsComplete(token))
        {
            ShowMessage("Enter the HRIS address (starting with http:// or https://) and the kiosk token.", isError: true);

            return;
        }

        _save.Enabled = false;
        ShowMessage("Contacting the HRIS…", isError: false);

        try
        {
            using var client = new HrisClient(url, token);
            var heartbeat = await client.HeartbeatAsync();

            if (candidate.Scanner == ScannerKind.Folder)
            {
                Directory.CreateDirectory(candidate.ScanInboxFolder);
            }

            if (!string.Equals(url, _settings.HrisBaseUrl, StringComparison.OrdinalIgnoreCase))
            {
                new TemplateCache(_paths.TemplateCacheFile).Clear();
            }

            candidate.Save(_paths);
            KioskSettings.SaveToken(_paths, token);
            MessageBox.Show(this, $"Connected as \"{heartbeat.Device.Name}\". The kiosk will now start.", Text, MessageBoxButtons.OK, MessageBoxIcon.Information);
            DialogResult = DialogResult.OK;
        }
        catch (HrisApiException exception)
        {
            ShowMessage(exception.Message, isError: true);
        }
        catch (IOException exception)
        {
            ShowMessage($"The test image folder cannot be created: {exception.Message}", isError: true);
        }
        finally
        {
            _save.Enabled = true;
        }
    }

    private void ShowMessage(string message, bool isError)
    {
        _message.Text = message;
        _message.ForeColor = isError ? Color.DarkRed : SystemColors.ControlText;
    }
}

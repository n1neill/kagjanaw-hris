using KagjanawKiosk.Sync;

namespace KagjanawKiosk.Forms;

/// <summary>
/// An HR officer signs in with their HRIS username and password. The HRIS
/// checks they may enroll fingerprints, audits the attempt and returns a
/// short session used for enrollment (and to unlock settings or exit).
/// </summary>
internal sealed class SignInForm : Form
{
    private readonly HrisClient _client;
    private readonly TextBox _username = new() { Width = 280 };
    private readonly TextBox _password = new() { Width = 280, UseSystemPasswordChar = true };
    private readonly Label _message = new() { AutoSize = true, MaximumSize = new Size(360, 0), ForeColor = Color.DarkRed };
    private readonly Button _signIn = new() { Text = "Sign in", AutoSize = true };

    public SignInForm(HrisClient client, string purpose)
    {
        _client = client;

        Text = "HR sign-in";
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        AutoSize = true;
        AutoSizeMode = AutoSizeMode.GrowAndShrink;
        Padding = new Padding(16);
        Font = KioskTheme.Font(11);
        TopMost = true;
        ShowInTaskbar = false;

        var cancel = new Button { Text = "Cancel", AutoSize = true, DialogResult = DialogResult.Cancel };
        _signIn.Click += async (_, _) => await SignInAsync();
        AcceptButton = _signIn;
        CancelButton = cancel;

        var buttons = new FlowLayoutPanel { AutoSize = true, Margin = new Padding(0, 12, 0, 0) };
        buttons.Controls.AddRange([_signIn, cancel]);

        var layout = new FlowLayoutPanel { FlowDirection = FlowDirection.TopDown, AutoSize = true, WrapContents = false, Dock = DockStyle.Fill };
        layout.Controls.Add(new Label { Text = purpose, AutoSize = true, MaximumSize = new Size(360, 0), Margin = new Padding(0, 0, 0, 12) });
        layout.Controls.Add(new Label { Text = "HRIS username", AutoSize = true });
        layout.Controls.Add(_username);
        layout.Controls.Add(new Label { Text = "Password", AutoSize = true, Margin = new Padding(0, 8, 0, 0) });
        layout.Controls.Add(_password);
        layout.Controls.Add(_message);
        layout.Controls.Add(buttons);
        Controls.Add(layout);
    }

    public EnrollmentSession? Session { get; private set; }

    private async Task SignInAsync()
    {
        if (_username.Text.Trim().Length == 0 || _password.Text.Length == 0)
        {
            _message.Text = "Enter your HRIS username and password.";

            return;
        }

        _signIn.Enabled = false;
        _message.Text = "";

        try
        {
            Session = await _client.StartEnrollmentAsync(_username.Text.Trim(), _password.Text);
            DialogResult = DialogResult.OK;
        }
        catch (HrisApiException exception)
        {
            _message.Text = exception.Message;
            _password.Clear();
            _password.Focus();
        }
        finally
        {
            _signIn.Enabled = true;
        }
    }
}

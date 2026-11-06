using System.Runtime.InteropServices;
using static KagjanawKiosk.Scanning.DigitalPersonaApi;

namespace KagjanawKiosk.Scanning;

/// <summary>
/// A DigitalPersona U.are.U reader (4000B, 4500, 5x00) through the One Touch
/// for Windows runtime. The runtime reports each finger placed on the reader
/// as a window message, so a background thread owns a hidden message-only
/// window and a message loop for it.
///
/// Acquisition runs for the scanner's whole life; a finger placed while
/// nothing is waiting in <see cref="CaptureAsync"/> (for example while the
/// previous scan is still being matched) is ignored rather than queued, so
/// the kiosk never acts on a stale scan.
/// </summary>
public sealed class DigitalPersonaScanner : IFingerprintScanner
{
    private const uint NotifyMessage = 0x0400 + 1; // WM_USER + 1
    private const uint StopMessage = 0x0400 + 2;
    private static readonly TimeSpan StartTimeout = TimeSpan.FromSeconds(15);

    private readonly int _fallbackDpi;
    private readonly KioskLog? _log;
    private readonly Thread _thread;
    private readonly Lock _gate = new();
    private TaskCompletionSource<FingerprintCapture>? _waiting;
    private MessageWindow? _window;
    private volatile bool _readerConnected;
    private bool _disposed;

    /// <param name="fallbackDpi">Resolution to assume if the runtime's image does not state one (512 for the U.are.U 4500).</param>
    /// <exception cref="ScannerUnavailableException">The runtime is missing, its service is not running, or acquisition could not start.</exception>
    public DigitalPersonaScanner(int fallbackDpi, KioskLog? log = null)
    {
        _fallbackDpi = fallbackDpi;
        _log = log;

        var started = new TaskCompletionSource(TaskCreationOptions.RunContinuationsAsynchronously);
        _thread = new Thread(() => Run(started)) { IsBackground = true, Name = "DigitalPersona reader" };
        _thread.SetApartmentState(ApartmentState.STA);
        _thread.Start();

        try
        {
            if (!started.Task.Wait(StartTimeout))
            {
                throw new ScannerUnavailableException("The DigitalPersona reader did not respond. Check that the Biometric Authentication Service (DpHost) is running.");
            }
        }
        catch (AggregateException exception) when (exception.InnerException is ScannerUnavailableException unavailable)
        {
            throw unavailable;
        }
    }

    public string Description { get; private set; } = "DigitalPersona reader";

    /// <summary>The priority the runtime granted: High (exclusive) when possible, otherwise Normal (foreground only).</summary>
    internal Priority GrantedPriority { get; private set; }

    public async Task<FingerprintCapture> CaptureAsync(CancellationToken cancellationToken)
    {
        ObjectDisposedException.ThrowIf(_disposed, this);

        if (!_readerConnected)
        {
            _readerConnected = ConnectedReaders().Count > 0;

            if (!_readerConnected)
            {
                throw new ScannerUnavailableException("No fingerprint reader is connected. Plug in the DigitalPersona reader.");
            }
        }

        var waiting = new TaskCompletionSource<FingerprintCapture>(TaskCreationOptions.RunContinuationsAsynchronously);

        lock (_gate)
        {
            // Only one caller waits at a time; a newer wait replaces an older one.
            _waiting?.TrySetCanceled();
            _waiting = waiting;
        }

        await using var registration = cancellationToken.Register(() => waiting.TrySetCanceled(cancellationToken));

        try
        {
            return await waiting.Task;
        }
        finally
        {
            lock (_gate)
            {
                if (_waiting == waiting)
                {
                    _waiting = null;
                }
            }
        }
    }

    public void Dispose()
    {
        if (_disposed)
        {
            return;
        }

        _disposed = true;

        if (_window is { Handle: var handle } && handle != IntPtr.Zero)
        {
            PostMessage(handle, StopMessage, IntPtr.Zero, IntPtr.Zero);
        }

        _thread.Join(TimeSpan.FromSeconds(5));

        lock (_gate)
        {
            _waiting?.TrySetCanceled();
            _waiting = null;
        }
    }

    /// <summary>The reader thread: initialise the runtime, start acquisition, then pump messages until disposed.</summary>
    private void Run(TaskCompletionSource started)
    {
        var initialised = false;
        uint operation = 0;
        var hasOperation = false;

        try
        {
            var result = DPFPInit();

            if (Failed(result))
            {
                throw new ScannerUnavailableException(result == ServiceNotRunning
                    ? "The DigitalPersona Biometric Authentication Service (DpHost) is not running. Start it in Windows Services."
                    : $"The DigitalPersona runtime could not start (error 0x{result:X8}).");
            }

            initialised = true;
            _window = new MessageWindow(this);
            (operation, GrantedPriority) = StartAcquisition(_window.Handle);
            hasOperation = true;

            var readers = ConnectedReaders();
            _readerConnected = readers.Count > 0;
            Description = readers.Count > 0 ? $"DigitalPersona {string.Join(", ", readers)}" : "DigitalPersona reader (not connected)";
            _log?.Info($"{Description}: acquisition started at {GrantedPriority} priority.");
            started.TrySetResult();

            PumpMessages();
        }
        catch (DllNotFoundException exception)
        {
            started.TrySetException(new ScannerUnavailableException(
                "The DigitalPersona One Touch runtime (DPFPApi.dll) is not installed on this PC. Install the DigitalPersona One Touch for Windows SDK or RTE (64-bit).", exception));
        }
        catch (ScannerUnavailableException exception)
        {
            started.TrySetException(exception);
        }
        catch (Exception exception)
        {
            _log?.Error("DigitalPersona reader thread failed", exception);
            started.TrySetException(new ScannerUnavailableException($"The DigitalPersona reader could not start: {exception.Message}", exception));
        }
        finally
        {
            if (hasOperation)
            {
                _ = DPFPStopAcquisition(operation);
                _ = DPFPDestroyAcquisition(operation);
            }

            _window?.DestroyHandle();

            if (initialised)
            {
                DPFPTerm();
            }

            lock (_gate)
            {
                _waiting?.TrySetException(new ScannerUnavailableException("The DigitalPersona reader stopped."));
                _waiting = null;
            }
        }
    }

    /// <summary>Asks for exclusive (High) access first, so a notification or another window taking focus cannot steal scans.</summary>
    private (uint Operation, Priority Priority) StartAcquisition(IntPtr window)
    {
        var lastResult = 0;

        foreach (var priority in new[] { Priority.High, Priority.Normal })
        {
            lastResult = DPFPCreateAcquisition(priority, Guid.Empty, SampleTypeImage, window, NotifyMessage, out var operation);

            if (!Failed(lastResult))
            {
                lastResult = DPFPStartAcquisition(operation);

                if (!Failed(lastResult))
                {
                    return (operation, priority);
                }

                _ = DPFPDestroyAcquisition(operation);
            }

            _log?.Info($"DigitalPersona {priority} priority not granted (0x{lastResult:X8}).");
        }

        throw new ScannerUnavailableException($"The DigitalPersona reader could not start capturing (error 0x{lastResult:X8}). Close any other fingerprint software on this PC.");
    }

    private static void PumpMessages()
    {
        while (GetMessage(out var message, IntPtr.Zero, 0, 0) > 0)
        {
            TranslateMessage(in message);
            DispatchMessage(in message);
        }
    }

    /// <summary>Runs on the reader thread for every notification from the runtime.</summary>
    private void OnNotification(Notification notification, IntPtr detail)
    {
        switch (notification)
        {
            case Notification.Completed:
                OnSample(detail);
                break;

            case Notification.Error:
                _log?.Warning($"DigitalPersona reader error 0x{detail.ToInt64():X8}.");
                Fail("The fingerprint reader reported an error. Lift your finger and try again.");
                break;

            case Notification.Disconnect:
                _readerConnected = false;
                _log?.Warning("DigitalPersona reader disconnected.");
                Fail("The fingerprint reader was unplugged. Plug it back in.");
                break;

            case Notification.Reconnect:
                _readerConnected = true;
                _log?.Info("DigitalPersona reader connected.");
                break;

            case Notification.SampleQuality when detail != IntPtr.Zero:
                _log?.Info($"DigitalPersona sample quality {detail.ToInt64()} (0 is good).");
                break;
        }
    }

    /// <summary>The sample (a DATA_BLOB) is only valid during this message, so it is converted here.</summary>
    private void OnSample(IntPtr sample)
    {
        TaskCompletionSource<FingerprintCapture>? waiting;

        lock (_gate)
        {
            waiting = _waiting;
        }

        if (waiting is null || sample == IntPtr.Zero)
        {
            return;
        }

        try
        {
            nuint size = 0;
            var result = DPFPConvertSampleToBitmap(sample, null, ref size);

            if (Failed(result) || size == 0)
            {
                throw new FormatException($"DPFPConvertSampleToBitmap failed (error 0x{result:X8}).");
            }

            var bitmap = new byte[(int)size];
            result = DPFPConvertSampleToBitmap(sample, bitmap, ref size);

            if (Failed(result))
            {
                throw new FormatException($"DPFPConvertSampleToBitmap failed (error 0x{result:X8}).");
            }

            waiting.TrySetResult(DeviceIndependentBitmap.ToCapture(bitmap.AsSpan(0, (int)size), _fallbackDpi));
        }
        catch (FormatException exception)
        {
            _log?.Warning($"DigitalPersona scan could not be converted: {exception.Message}");
            waiting.TrySetException(new ScannerUnavailableException("The fingerprint image could not be read. Lift your finger and try again.", exception));
        }
    }

    private void Fail(string message)
    {
        lock (_gate)
        {
            _waiting?.TrySetException(new ScannerUnavailableException(message));
        }
    }

    /// <summary>A hidden message-only window that receives the runtime's notifications.</summary>
    private sealed class MessageWindow : NativeWindow
    {
        private static readonly IntPtr MessageOnlyParent = new(-3); // HWND_MESSAGE
        private readonly DigitalPersonaScanner _scanner;

        public MessageWindow(DigitalPersonaScanner scanner)
        {
            _scanner = scanner;
            CreateHandle(new CreateParams { Caption = "HRIS kiosk reader", Parent = MessageOnlyParent });
        }

        protected override void WndProc(ref Message m)
        {
            if (m.Msg == NotifyMessage)
            {
                try
                {
                    _scanner.OnNotification((Notification)m.WParam.ToInt32(), m.LParam);
                }
                catch (Exception exception)
                {
                    // The reader thread must keep pumping messages whatever happens to one scan.
                    _scanner._log?.Error("DigitalPersona notification failed", exception);
                }

                return;
            }

            if (m.Msg == StopMessage)
            {
                PostQuitMessage(0);

                return;
            }

            base.WndProc(ref m);
        }
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct NativeMessage
    {
        public IntPtr Window;
        public uint Message;
        public IntPtr WParam;
        public IntPtr LParam;
        public uint Time;
        public int X;
        public int Y;
        public uint Private;
    }

    [DllImport("user32.dll")]
    private static extern int GetMessage(out NativeMessage message, IntPtr window, uint filterMin, uint filterMax);

    [DllImport("user32.dll")]
    private static extern bool TranslateMessage(in NativeMessage message);

    [DllImport("user32.dll")]
    private static extern IntPtr DispatchMessage(in NativeMessage message);

    [DllImport("user32.dll")]
    private static extern void PostQuitMessage(int exitCode);

    [DllImport("user32.dll")]
    private static extern bool PostMessage(IntPtr window, uint message, IntPtr wParam, IntPtr lParam);
}

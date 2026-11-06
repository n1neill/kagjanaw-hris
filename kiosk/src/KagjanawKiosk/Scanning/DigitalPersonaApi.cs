using System.Runtime.InteropServices;

namespace KagjanawKiosk.Scanning;

/// <summary>
/// The capture part of the DigitalPersona One Touch for Windows runtime
/// (DPFPApi.dll in System32, installed with the One Touch SDK or RTE).
/// Called directly so the kiosk builds without the SDK and ships none of its
/// files. Declarations follow DPDevClt.h; DPFPConvertSampleToBitmap is not in
/// the header but is declared the same way in the SDK's own C++ samples.
/// </summary>
internal static class DigitalPersonaApi
{
    private const string Library = "DPFPApi.dll";

    /// <summary>DP_SAMPLE_TYPE_IMAGE: the only sample type the API supports.</summary>
    public const uint SampleTypeImage = 4;

    /// <summary>0x800706B3, "RPC server is not listening": the DigitalPersona service is not running.</summary>
    public const int ServiceNotRunning = unchecked((int)0x800706B3);

    public enum Priority
    {
        /// <summary>Receives the reader's events exclusively, even when the kiosk is not the foreground window.</summary>
        High = 1,

        /// <summary>Receives events only while the kiosk is the foreground process.</summary>
        Normal = 2,
    }

    /// <summary>The wParam of a notification message (WN_* in DPDevClt.h).</summary>
    public enum Notification
    {
        Completed = 0,
        Error = 1,
        Disconnect = 2,
        Reconnect = 3,
        SampleQuality = 4,
        FingerTouched = 5,
        FingerGone = 6,
        ImageReady = 7,
        OperationStopped = 10,
    }

    [DllImport(Library)]
    public static extern int DPFPInit();

    [DllImport(Library)]
    public static extern void DPFPTerm();

    [DllImport(Library)]
    public static extern void DPFPBufferFree(IntPtr buffer);

    [DllImport(Library)]
    public static extern int DPFPCreateAcquisition(Priority priority, in Guid deviceUid, uint sampleType, IntPtr window, uint message, out uint operation);

    [DllImport(Library)]
    public static extern int DPFPStartAcquisition(uint operation);

    [DllImport(Library)]
    public static extern int DPFPStopAcquisition(uint operation);

    [DllImport(Library)]
    public static extern int DPFPDestroyAcquisition(uint operation);

    [DllImport(Library)]
    public static extern int DPFPEnumerateDevices(out uint count, out IntPtr deviceUids);

    [DllImport(Library)]
    public static extern int DPFPGetDeviceInfo(in Guid deviceUid, ref DeviceInfo info);

    /// <summary>Called once with a null buffer to get the size, then again to fill it.</summary>
    [DllImport(Library)]
    public static extern int DPFPConvertSampleToBitmap(IntPtr sample, [In, Out] byte[]? bitmap, ref nuint size);

    public static bool Failed(int result) => result < 0;

    /// <summary>The names of the connected readers (empty when none is plugged in).</summary>
    public static IReadOnlyList<string> ConnectedReaders()
    {
        if (Failed(DPFPEnumerateDevices(out var count, out var uids)))
        {
            return [];
        }

        try
        {
            var readers = new List<string>();

            for (var index = 0; index < count; index++)
            {
                var uid = Marshal.PtrToStructure<Guid>(uids + (index * Marshal.SizeOf<Guid>()));
                var info = new DeviceInfo();
                readers.Add(Failed(DPFPGetDeviceInfo(uid, ref info)) ? "DigitalPersona reader" : info.Hardware.Product.Trim());
            }

            return readers;
        }
        finally
        {
            if (uids != IntPtr.Zero)
            {
                DPFPBufferFree(uids);
            }
        }
    }

    [StructLayout(LayoutKind.Sequential)]
    public struct DeviceVersion
    {
        public uint Major;
        public uint Minor;
        public uint Build;
    }

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    public struct HardwareInfo
    {
        public uint LanguageId;

        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 128)]
        public string Vendor;

        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 128)]
        public string Product;

        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 128)]
        public string SerialNumber;

        public DeviceVersion HardwareRevision;
        public DeviceVersion FirmwareRevision;
    }

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    public struct DeviceInfo
    {
        public Guid DeviceUid;
        public int UidType;
        public int Modality;
        public int Technology;
        public HardwareInfo Hardware;
    }
}

using KagjanawKiosk.Matching;

namespace KagjanawKiosk.Sync;

/// <summary>A punch waiting to be sent: who, and when (with the kiosk's UTC offset).</summary>
public sealed record QueuedPunch(long Id, string BiometricUserId, DateTimeOffset LoggedAt, string VerifyType = "fingerprint");

public sealed record HeartbeatDevice(int Id, string Name, string? Location);

public sealed record Heartbeat(HeartbeatDevice Device, DateTimeOffset ServerTime);

public sealed record PunchReceipt(int Received, int Inserted, int Duplicates, int Flagged);

/// <summary>The enrolled fingers and the ETag that identifies this version of the list.</summary>
public sealed record TemplateFeed(string? ETag, IReadOnlyList<EnrolledFingerprint> Fingerprints);

public sealed record SessionUser(int Id, string Name);

/// <summary>An HR officer's sign-in at the kiosk, used to enroll fingerprints.</summary>
public sealed record EnrollmentSession(string Token, DateTimeOffset ExpiresAt, SessionUser User);

public sealed record EnrollableEmployee(
    int Id,
    string EmployeeNumber,
    string Name,
    string? Department,
    string BiometricUserId,
    IReadOnlyList<string> EnrolledFingers);

public sealed record EnrollmentReceipt(int EmployeeId, string Finger, IReadOnlyList<string> EnrolledFingers);

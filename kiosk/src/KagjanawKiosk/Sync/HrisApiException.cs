using System.Net;

namespace KagjanawKiosk.Sync;

/// <summary>
/// The HRIS refused a request (StatusCode set) or could not be reached
/// (StatusCode null). Message is the HRIS's own explanation when it gave one.
/// </summary>
public sealed class HrisApiException(string message, HttpStatusCode? statusCode = null, Exception? inner = null)
    : Exception(message, inner)
{
    public HttpStatusCode? StatusCode { get; } = statusCode;

    public bool IsUnreachable => StatusCode is null;

    /// <summary>The kiosk token is wrong, revoked, disabled or used from the wrong PC.</summary>
    public bool IsKioskRejected => StatusCode is HttpStatusCode.Unauthorized or HttpStatusCode.Forbidden;

    public bool IsValidationError => StatusCode is HttpStatusCode.UnprocessableEntity;
}

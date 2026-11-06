using System.Net;
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text.Json;
using System.Text.Json.Serialization;
using KagjanawKiosk.Matching;

namespace KagjanawKiosk.Sync;

/// <summary>
/// The HRIS kiosk API (/api/kiosk/v1). Every request carries the kiosk token;
/// enrollment requests also carry the HR officer's session token.
/// </summary>
public sealed class HrisClient : IDisposable
{
    public const string EnrollmentTokenHeader = "X-Enrollment-Token";

    internal static readonly JsonSerializerOptions Json = new(JsonSerializerDefaults.Web)
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull,
    };

    private readonly HttpClient _http;

    public HrisClient(string baseUrl, string kioskToken, HttpMessageHandler? handler = null)
    {
        _http = handler is null ? new HttpClient() : new HttpClient(handler, disposeHandler: false);
        _http.BaseAddress = new Uri(baseUrl.TrimEnd('/') + "/api/kiosk/v1/");
        _http.Timeout = TimeSpan.FromSeconds(15);
        _http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", kioskToken.Trim());
        _http.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
        _http.DefaultRequestHeaders.UserAgent.Add(new ProductInfoHeaderValue("HrisFingerprintKiosk", "1.0"));
    }

    public async Task<Heartbeat> HeartbeatAsync(CancellationToken cancellationToken = default)
    {
        using var response = await SendAsync(new HttpRequestMessage(HttpMethod.Get, "heartbeat"), cancellationToken);

        return await ReadAsync<Heartbeat>(response, cancellationToken);
    }

    /// <param name="knownETag">The ETag of the list the kiosk already has.</param>
    /// <returns>The new list, or null when the HRIS says the list has not changed.</returns>
    public async Task<TemplateFeed?> GetTemplatesAsync(string? knownETag, CancellationToken cancellationToken = default)
    {
        var request = new HttpRequestMessage(HttpMethod.Get, "templates");

        if (knownETag is not null && EntityTagHeaderValue.TryParse(knownETag, out var etag))
        {
            request.Headers.IfNoneMatch.Add(etag);
        }

        using var response = await SendAsync(request, cancellationToken);

        if (response.StatusCode == HttpStatusCode.NotModified)
        {
            return null;
        }

        var body = await ReadAsync<DataEnvelope<EnrolledFingerprint>>(response, cancellationToken);

        return new TemplateFeed(response.Headers.ETag?.ToString(), body.Data);
    }

    public async Task<PunchReceipt> SendPunchesAsync(IReadOnlyList<QueuedPunch> punches, CancellationToken cancellationToken = default)
    {
        var payload = new
        {
            punches = punches.Select(punch => new
            {
                biometric_user_id = punch.BiometricUserId,
                logged_at = punch.LoggedAt.ToString("yyyy-MM-dd'T'HH:mm:sszzz"),
                verify_type = punch.VerifyType,
            }),
        };

        using var response = await SendAsync(new HttpRequestMessage(HttpMethod.Post, "punches") { Content = JsonContent.Create(payload) }, cancellationToken);

        return await ReadAsync<PunchReceipt>(response, cancellationToken);
    }

    public async Task<EnrollmentSession> StartEnrollmentAsync(string username, string password, CancellationToken cancellationToken = default)
    {
        var request = new HttpRequestMessage(HttpMethod.Post, "enrollment-sessions")
        {
            Content = JsonContent.Create(new { username, password }),
        };

        using var response = await SendAsync(request, cancellationToken);

        return await ReadAsync<EnrollmentSession>(response, cancellationToken);
    }

    public async Task EndEnrollmentAsync(EnrollmentSession session, CancellationToken cancellationToken = default)
    {
        using var response = await SendAsync(WithSession(new HttpRequestMessage(HttpMethod.Delete, "enrollment-sessions/current"), session), cancellationToken);
    }

    public async Task<IReadOnlyList<EnrollableEmployee>> GetEmployeesAsync(EnrollmentSession session, CancellationToken cancellationToken = default)
    {
        using var response = await SendAsync(WithSession(new HttpRequestMessage(HttpMethod.Get, "employees"), session), cancellationToken);

        return (await ReadAsync<DataEnvelope<EnrollableEmployee>>(response, cancellationToken)).Data;
    }

    public async Task<EnrollmentReceipt> EnrollFingerAsync(EnrollmentSession session, int employeeId, string finger, string template, int? quality, CancellationToken cancellationToken = default)
    {
        var request = WithSession(new HttpRequestMessage(HttpMethod.Put, $"employees/{employeeId}/fingerprints/{Uri.EscapeDataString(finger)}")
        {
            Content = JsonContent.Create(new { template, quality }),
        }, session);

        using var response = await SendAsync(request, cancellationToken);

        return await ReadAsync<EnrollmentReceipt>(response, cancellationToken);
    }

    public void Dispose() => _http.Dispose();

    private static HttpRequestMessage WithSession(HttpRequestMessage request, EnrollmentSession session)
    {
        request.Headers.Add(EnrollmentTokenHeader, session.Token);

        return request;
    }

    /// <summary>Sends the request; throws HrisApiException for anything but 2xx or 304.</summary>
    private async Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
    {
        HttpResponseMessage response;

        try
        {
            response = await _http.SendAsync(request, cancellationToken);
        }
        catch (HttpRequestException exception)
        {
            throw new HrisApiException($"The HRIS at {_http.BaseAddress?.GetLeftPart(UriPartial.Authority)} could not be reached.", inner: exception);
        }
        catch (TaskCanceledException exception) when (!cancellationToken.IsCancellationRequested)
        {
            throw new HrisApiException("The HRIS did not answer in time.", inner: exception);
        }
        finally
        {
            request.Dispose();
        }

        if (response.IsSuccessStatusCode || response.StatusCode == HttpStatusCode.NotModified)
        {
            return response;
        }

        using (response)
        {
            throw new HrisApiException(await ErrorMessageAsync(response, cancellationToken), response.StatusCode);
        }
    }

    private static async Task<T> ReadAsync<T>(HttpResponseMessage response, CancellationToken cancellationToken)
    {
        try
        {
            return await response.Content.ReadFromJsonAsync<T>(Json, cancellationToken)
                ?? throw new HrisApiException("The HRIS sent an empty answer.", response.StatusCode);
        }
        catch (JsonException exception)
        {
            throw new HrisApiException("The HRIS sent an answer the kiosk does not understand. Is the address correct?", response.StatusCode, exception);
        }
    }

    /// <summary>The first validation error, else Laravel's message, else the status.</summary>
    private static async Task<string> ErrorMessageAsync(HttpResponseMessage response, CancellationToken cancellationToken)
    {
        try
        {
            var error = await response.Content.ReadFromJsonAsync<ErrorBody>(Json, cancellationToken);
            var firstValidationError = error?.Errors?.Values.SelectMany(messages => messages).FirstOrDefault();

            if (!string.IsNullOrWhiteSpace(firstValidationError ?? error?.Message))
            {
                return (firstValidationError ?? error!.Message)!;
            }
        }
        catch (Exception exception) when (exception is JsonException or NotSupportedException)
        {
            // Not a JSON error body (e.g. a proxy page); fall back to the status.
        }

        return $"The HRIS answered {(int)response.StatusCode} {response.ReasonPhrase}.";
    }

    private sealed record DataEnvelope<T>(IReadOnlyList<T> Data);

    private sealed record ErrorBody(string? Message, Dictionary<string, string[]>? Errors);
}

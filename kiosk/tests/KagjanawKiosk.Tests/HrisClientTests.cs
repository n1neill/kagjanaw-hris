using System.Net;
using System.Text.Json;
using KagjanawKiosk.Sync;

namespace KagjanawKiosk.Tests;

public class HrisClientTests
{
    private static readonly TimeSpan Manila = TimeSpan.FromHours(8);

    [Fact]
    public async Task Signs_requests_with_the_kiosk_token_and_sends_punches_in_the_api_format()
    {
        var handler = new StubHttpHandler((_, _) => StubHttpHandler.Json(HttpStatusCode.OK, """{"received":1,"inserted":1,"duplicates":0,"flagged":0}"""));
        using var client = new HrisClient("http://hris.local/", "secret-token", handler);

        var receipt = await client.SendPunchesAsync([new QueuedPunch(7, "1024", new DateTimeOffset(2026, 11, 6, 7, 58, 0, Manila))]);

        var (request, body) = Assert.Single(handler.Requests);
        Assert.Equal("http://hris.local/api/kiosk/v1/punches", request.RequestUri!.ToString());
        Assert.Equal("Bearer secret-token", request.Headers.Authorization!.ToString());
        var punch = JsonDocument.Parse(body!).RootElement.GetProperty("punches").EnumerateArray().Single();
        Assert.Equal("1024", punch.GetProperty("biometric_user_id").GetString());
        Assert.Equal("2026-11-06T07:58:00+08:00", punch.GetProperty("logged_at").GetString());
        Assert.Equal("fingerprint", punch.GetProperty("verify_type").GetString());
        Assert.Equal(new PunchReceipt(1, 1, 0, 0), receipt);
    }

    [Fact]
    public async Task Reads_the_template_feed_and_reports_an_unchanged_list_as_null()
    {
        var handler = new StubHttpHandler((request, _) =>
        {
            if (request.Headers.IfNoneMatch.Count > 0)
            {
                return new HttpResponseMessage(HttpStatusCode.NotModified);
            }

            var response = StubHttpHandler.Json(HttpStatusCode.OK, """
                {"data":[{"employee_id":5,"biometric_user_id":"1024","employee_name":"Juan Cruz","finger":"right-index","template":"QUJD"}]}
                """);
            response.Headers.ETag = new System.Net.Http.Headers.EntityTagHeaderValue("\"abc123\"");

            return response;
        });
        using var client = new HrisClient("http://hris.local", "token", handler);

        var feed = await client.GetTemplatesAsync(knownETag: null);
        var unchanged = await client.GetTemplatesAsync(feed!.ETag);

        Assert.Equal("\"abc123\"", feed.ETag);
        var fingerprint = Assert.Single(feed.Fingerprints);
        Assert.Equal((5, "1024", "Juan Cruz", "right-index"), (fingerprint.EmployeeId, fingerprint.BiometricUserId, fingerprint.EmployeeName, fingerprint.Finger));
        Assert.Null(unchanged);
        Assert.Equal("\"abc123\"", handler.Requests[1].Request.Headers.IfNoneMatch.Single().ToString());
    }

    [Fact]
    public async Task Sends_the_hr_session_token_with_enrollment_requests()
    {
        var handler = new StubHttpHandler((_, _) => StubHttpHandler.Json(HttpStatusCode.Created, """{"employee_id":5,"finger":"left-index","enrolled_fingers":["left-index"]}"""));
        using var client = new HrisClient("http://hris.local", "token", handler);
        var session = new EnrollmentSession("hr-session", DateTimeOffset.Now.AddMinutes(15), new SessionUser(1, "Maria Santos"));

        var receipt = await client.EnrollFingerAsync(session, 5, "left-index", "QUJD", 77);

        var (request, body) = Assert.Single(handler.Requests);
        Assert.Equal(HttpMethod.Put, request.Method);
        Assert.Equal("/api/kiosk/v1/employees/5/fingerprints/left-index", request.RequestUri!.AbsolutePath);
        Assert.Equal("hr-session", request.Headers.GetValues(HrisClient.EnrollmentTokenHeader).Single());
        Assert.Equal("""{"template":"QUJD","quality":77}""", body);
        Assert.Equal(["left-index"], receipt.EnrolledFingers);
    }

    [Fact]
    public async Task Explains_a_refusal_with_the_first_validation_message()
    {
        var handler = new StubHttpHandler((_, _) => StubHttpHandler.Json(HttpStatusCode.UnprocessableEntity, """
            {"message":"These credentials do not match our records.","errors":{"username":["These credentials do not match our records."]}}
            """));
        using var client = new HrisClient("http://hris.local", "token", handler);

        var exception = await Assert.ThrowsAsync<HrisApiException>(() => client.StartEnrollmentAsync("maria", "wrong"));

        Assert.True(exception.IsValidationError);
        Assert.Equal("These credentials do not match our records.", exception.Message);
    }

    [Fact]
    public async Task Reports_a_rejected_kiosk_token()
    {
        var handler = new StubHttpHandler((_, _) => StubHttpHandler.Json(HttpStatusCode.Unauthorized, """{"message":"The kiosk token is missing or no longer valid."}"""));
        using var client = new HrisClient("http://hris.local", "token", handler);

        var exception = await Assert.ThrowsAsync<HrisApiException>(() => client.HeartbeatAsync());

        Assert.True(exception.IsKioskRejected);
        Assert.Equal("The kiosk token is missing or no longer valid.", exception.Message);
    }

    [Fact]
    public async Task Reports_an_unreachable_hris()
    {
        var handler = new StubHttpHandler((_, _) => throw new HttpRequestException("No such host is known."));
        using var client = new HrisClient("http://hris.local", "token", handler);

        var exception = await Assert.ThrowsAsync<HrisApiException>(() => client.HeartbeatAsync());

        Assert.True(exception.IsUnreachable);
    }

    [Fact]
    public async Task Explains_a_non_json_answer()
    {
        var handler = new StubHttpHandler((_, _) => new HttpResponseMessage(HttpStatusCode.OK) { Content = new StringContent("<html>Welcome</html>") });
        using var client = new HrisClient("http://hris.local", "token", handler);

        var exception = await Assert.ThrowsAsync<HrisApiException>(() => client.HeartbeatAsync());

        Assert.Contains("Is the address correct?", exception.Message);
        Assert.IsType<JsonException>(exception.InnerException);
    }
}

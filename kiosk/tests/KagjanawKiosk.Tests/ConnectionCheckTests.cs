using System.Net;
using KagjanawKiosk.Sync;

namespace KagjanawKiosk.Tests;

public class ConnectionCheckTests
{
    [Fact]
    public async Task Finds_no_problem_when_the_hris_accepts_the_kiosk()
    {
        var handler = new StubHttpHandler((_, _) => StubHttpHandler.Json(HttpStatusCode.OK, """
            {"device":{"id":1,"name":"Lobby kiosk","location":null},"server_time":"2026-11-24T08:00:00+08:00"}
            """));
        using var client = new HrisClient("http://hris.local", "token", handler);

        Assert.Null(await ConnectionCheck.FindProblemAsync(client));
    }

    [Fact]
    public async Task Reports_a_kiosk_the_hris_rejects()
    {
        var handler = new StubHttpHandler((_, _) => StubHttpHandler.Json(HttpStatusCode.Unauthorized, """{"message":"Invalid kiosk token."}"""));
        using var client = new HrisClient("http://hris.local", "token", handler);

        var problem = await ConnectionCheck.FindProblemAsync(client);

        Assert.StartsWith("This kiosk is not connected to the HRIS: Invalid kiosk token.", problem);
    }

    [Fact]
    public async Task Reports_an_hris_that_is_offline()
    {
        var handler = new StubHttpHandler((_, _) => throw new HttpRequestException("No connection could be made."));
        using var client = new HrisClient("http://hris.local", "token", handler);

        var problem = await ConnectionCheck.FindProblemAsync(client);

        Assert.StartsWith("The HRIS is offline: The HRIS at http://hris.local could not be reached.", problem);
    }
}

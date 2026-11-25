using KagjanawKiosk.Configuration;

namespace KagjanawKiosk.Tests;

public class KioskSettingsTests
{
    [Fact]
    public void Keeps_the_saved_token_for_the_same_hris_address()
    {
        var settings = new KioskSettings { HrisBaseUrl = "https://hris.example" };

        Assert.Equal("saved-token", settings.ReusableToken("https://HRIS.example/", "saved-token"));
    }

    [Fact]
    public void Does_not_reuse_the_saved_token_for_another_hris_address()
    {
        var settings = new KioskSettings { HrisBaseUrl = "http://kagjanaw-hris.test" };

        Assert.Null(settings.ReusableToken("https://hris.example", "saved-token"));
    }
}

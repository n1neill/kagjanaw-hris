namespace KagjanawKiosk.Sync;

/// <summary>
/// Checks at start-up that the HRIS is online and accepts this kiosk, so the
/// settings screen opens first when it is not.
/// </summary>
public static class ConnectionCheck
{
    /// <returns>Why the kiosk cannot connect, shown on the settings screen; null when it is connected.</returns>
    public static async Task<string?> FindProblemAsync(HrisClient client, CancellationToken cancellationToken = default)
    {
        try
        {
            await client.HeartbeatAsync(cancellationToken);

            return null;
        }
        catch (HrisApiException exception) when (exception.IsKioskRejected)
        {
            return $"This kiosk is not connected to the HRIS: {exception.Message} Check the token and the device in Admin → Biometric Devices.";
        }
        catch (HrisApiException exception) when (exception.IsUnreachable)
        {
            return $"The HRIS is offline: {exception.Message} Check the network and the HRIS address, then test the connection again.";
        }
        catch (HrisApiException exception)
        {
            return $"The HRIS could not confirm this kiosk: {exception.Message}";
        }
    }
}

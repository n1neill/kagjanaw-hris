using System.Security.Cryptography;

namespace KagjanawKiosk.Configuration;

/// <summary>
/// Reads and writes files encrypted with Windows DPAPI for the current user,
/// so the kiosk token and the fingerprint templates are unreadable if the
/// files are copied off the kiosk PC.
/// </summary>
public static class ProtectedFile
{
    private static readonly byte[] Entropy = "KagjanawKiosk/v1"u8.ToArray();

    public static void Write(string path, byte[] plain)
    {
        var temporary = path + ".tmp";
        File.WriteAllBytes(temporary, ProtectedData.Protect(plain, Entropy, DataProtectionScope.CurrentUser));
        File.Move(temporary, path, overwrite: true);
    }

    /// <returns>The decrypted contents, or null when the file is missing or cannot be decrypted.</returns>
    public static byte[]? Read(string path)
    {
        if (!File.Exists(path))
        {
            return null;
        }

        try
        {
            return ProtectedData.Unprotect(File.ReadAllBytes(path), Entropy, DataProtectionScope.CurrentUser);
        }
        catch (CryptographicException)
        {
            return null;
        }
    }
}

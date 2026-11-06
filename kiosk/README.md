# HRIS Fingerprint Kiosk

A Windows app for a PC with a **DigitalPersona U.are.U** fingerprint reader. It stands in for the ZKTeco time clock until that device is available.

The U.are.U reader only captures images: it has no memory and no matcher. So this app does the rest:

1. **Captures** the fingerprint image from the reader.
2. **Matches** it against the enrolled fingers with [SourceAFIS](https://sourceafis.machinezoo.com/) (open source, 1:N).
3. **Queues** the punch locally (SQLite) and **sends** it to the HRIS kiosk API (`/api/kiosk/v1`).

The HRIS stores punches exactly like ZKTeco logs: duplicates are skipped, unknown IDs are flagged, and the DTR is rebuilt. The kiosk keeps working while the network is down and sends the queued punches when the HRIS is back.

## Requirements

- Windows 10/11, 64-bit
- [.NET 9 SDK](https://dotnet.microsoft.com/download/dotnet/9.0) to build (the .NET 9 Desktop Runtime is enough to run a published build)
- The HRIS reachable from the PC over the office LAN
- For the reader:
  - The **DigitalPersona One Touch for Windows** SDK or RTE (1.6.1, 64-bit). It installs `DPFPApi.dll` in System32 and the *Biometric Authentication Service* (`DpHost`).
  - The U.are.U reader plugged in.

  The kiosk calls `DPFPApi.dll` directly. It builds without the SDK, and it doesn't ship or reference any DigitalPersona files.

## Build and test

```powershell
cd kiosk
dotnet build
dotnet test
dotnet run --project src/KagjanawKiosk -- --windowed   # a normal window, for development
```

## Set up a kiosk

1. **HRIS:** in **Admin → Biometric Devices → New Device**:
   - Set the driver to **DigitalPersona U.are.U kiosk**.
   - Set the **IP address** to the kiosk PC's LAN address, or `127.0.0.1` if the HRIS runs on the same PC. The kiosk is only accepted from that address.
   - Open the device and click **Issue Kiosk Token**. Copy the token; it is shown once.
2. **Kiosk PC:** start the app. On the first run it asks for:
   - the HRIS address (e.g. `http://192.168.1.10`),
   - the token,
   - the reader: **DigitalPersona U.are.U reader**.

   It saves the settings only once the HRIS accepts the token. If the reader can't be opened, the settings screen reopens with the reason (see *Troubleshooting*).
3. **HRIS:** give each employee a **Biometric user ID** on their record. Only employees with one can be enrolled.
4. **Enroll:** at the kiosk, HR presses **Ctrl+Shift+E** and signs in with their HRIS username and password. Then they pick the employee and finger and scan it 3 times. Up to 2 fingers per employee.
   - The 3 scans must match each other.
   - A finger already enrolled for someone else is refused.
   - Enrolled fingers are listed on the employee's record in the HRIS, where HR can remove them.

### HR shortcuts at the kiosk

| Keys | Action |
|---|---|
| Ctrl+Shift+E | Enroll fingerprints (HR sign-in) |
| Ctrl+Shift+S | Settings (HR sign-in; no sign-in needed if the HRIS rejects the kiosk token) |
| Ctrl+Shift+Q | Close the kiosk (HR sign-in) |

In full-screen mode, Alt+F4 does not close the kiosk. Start with `--windowed` for development and `--setup` to open the settings first.

### Start automatically

Use Task Scheduler. Create a task that runs `KagjanawKiosk.exe`:
- **Trigger:** *At log on* of the kiosk's Windows account.
- **Setting:** *Run only when user is logged on*.

Windows should sign that account in automatically and keep its clock on Internet/domain time. The kiosk warns on screen when its clock is more than 2 minutes off the HRIS.

**Reader access.** The DigitalPersona runtime gives exclusive access to the reader only to elevated programs. Without it, the reader's scans go only to the program in the foreground.
- **Normal run:** the full-screen kiosk stays in front, so this works.
- **Safer:** tick *Run with highest privileges* in the task. The kiosk then gets the reader exclusively, and a pop-up from another program can't take its scans.

The log shows which access the kiosk got (`acquisition started at High/Normal priority`).

## Testing without the reader (folder scanner)

In settings, choose **Folder of test images**. The kiosk then watches the folder (by default `%LOCALAPPDATA%\KagjanawKiosk\scan-inbox`):
- Copying a PNG, BMP, JPEG or TIFF fingerprint image into the folder counts as one scan.
- The file is then moved to `used\`.

Real fingerprint images make the best test data, for example the [FVC2002](http://bias.csr.unibo.it/fvc2002/databases.asp) sample sets (DB1_B: 10 fingers × 8 impressions at 500 dpi). They are research data: keep them in `kiosk/samples/`, which is git-ignored, and don't commit them.
- **Enroll:** use impressions 1–3 of a finger.
- **Clock in:** use another impression of the same finger.
- **Expect "not recognized":** use a different finger.

## Where the kiosk keeps its files

`%LOCALAPPDATA%\KagjanawKiosk\` (set `KAGJANAW_KIOSK_HOME` to use another folder, e.g. for testing):

| File | Contents |
|---|---|
| `settings.json` | HRIS address, reader, resolution, match threshold |
| `kiosk-token.dat` | The kiosk token, encrypted with Windows DPAPI for this account |
| `templates.dat` | Last list of enrolled fingers from the HRIS (DPAPI-encrypted), so matching works offline |
| `punch-queue.db` | Punches not yet confirmed by the HRIS; punches the HRIS refused are kept in `rejected_punches` |
| `logs\kiosk-*.log` | Daily log (never contains fingerprints, tokens or passwords) |

## Privacy and security

- **Templates only:** only SourceAFIS templates (minutiae) leave the kiosk, never fingerprint images. A template cannot be turned back into a fingerprint image. The HRIS stores templates encrypted.
- **Locked to one PC:** the kiosk token works only from the registered IP address. Reissuing it in the HRIS locks out the old one.
- **Audited HR sign-ins:** HR sign-ins at the kiosk are throttled and audited, and every enrollment or removal is recorded in the HRIS audit log under the HR officer's name.
- **Match threshold:** 40 by default in `settings.json` (`MatchThreshold`), SourceAFIS's recommended value. It means roughly 1 false match in 10,000 comparisons.
- **ImageSharp left out:** SourceAFIS depends on ImageSharp 2.x, which has known vulnerabilities. ImageSharp is only used to decode encoded images, and the kiosk always gives SourceAFIS raw pixels. So the ImageSharp DLL is excluded from the build (see `KagjanawKiosk.csproj`); any attempt to decode through it fails instead of running vulnerable code.

## Troubleshooting

| On screen | What to do |
|---|---|
| *Kiosk not accepted by the HRIS* | The token was reissued, the device was deactivated, or the PC's IP address changed. Fix it in Admin → Biometric Devices, then press Ctrl+Shift+S. |
| *Offline: punches are saved and sent later* | Check the network and the HRIS address. Punches wait in `punch-queue.db`. |
| *This PC's clock is N min off* | Fix the Windows date and time; punches are stamped with the PC's clock. |
| *The DigitalPersona One Touch runtime (DPFPApi.dll) is not installed* | Install the One Touch for Windows SDK or RTE (64-bit), then start the kiosk again. |
| *The Biometric Authentication Service (DpHost) is not running* | Start it in Windows Services (`services.msc`) and set it to Automatic. |
| *No fingerprint reader is connected* / *was unplugged* | Plug the reader in. The kiosk retries every 5 seconds and picks it up by itself. |
| *Could not start capturing* | Another program holds the reader exclusively. Close other fingerprint software, e.g. the SDK's sample apps. |
| Scans do nothing | Without elevation, scans go only to the program in front. Bring the kiosk to the front, or run it with highest privileges (see *Start automatically*). |
| *Fingerprint not recognized* every time | Press the finger flat in the middle of the reader, as at enrollment, or enroll that finger again. With the folder scanner, check the image resolution in settings (500 dpi for most sample sets). |

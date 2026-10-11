# Kagjanaw HRIS

A web-based, on-premise Human Resource Information System for the **Municipality of Cagdianao, Province of Dinagat Islands**. It replaces paper-based HR processes with one system for employee records, biometric attendance, leave management and payroll. It runs on the municipal office LAN and works in desktop and mobile browsers.

## Status

| Phase | Scope | Status |
| --- | --- | --- |
| 1 | Foundation: authentication, roles and permissions, layout, role dashboards, user accounts, departments, positions, employment types | Done |
| 2 | Employee records and 201 files, automatic employee login accounts, sign-in by employee ID, forced password change | Done |
| 3 | Attendance: device driver interface and mock driver, scheduled sync, CSV import, DTR processing (CSC Form 48), HR corrections with audit trail, validation, summaries and DTR PDF; office hours and holidays | Done |
| 4 | Leave management | Planned |
| 5 | Payroll | Planned |
| 6 | Notifications, announcements, reports, admin tools (audit log, backup/restore, settings) | Planned |
| 7 | Hardening, user guide | Planned |

## Tech stack

- Laravel 13, PHP 8.4, with a service layer in `app/Services`
- Blade, Alpine.js, Tailwind CSS v4, DataTables 3 (bundled with Vite; no CDN at runtime)
- MariaDB 10.4 (XAMPP), which is compatible with MySQL 8.0
- Breeze-style Blade authentication; Spatie Laravel Permission for role-based access control
- Pest for tests

## Requirements (Windows)

| Tool | Notes |
| --- | --- |
| PHP 8.3+ | Laravel 13 needs PHP 8.3 or later. XAMPP's bundled PHP 8.2 is **too old**; use [Laravel Herd](https://herd.laravel.com/windows) or another PHP 8.3+ build. Required extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `intl`, `zip`, `gd`. |
| PHP upload limits | 201-file uploads can be up to 10 MB. In `php.ini` set `upload_max_filesize = 12M` and `post_max_size = 16M` (the defaults are 2M and 8M). |
| Composer 2 | |
| Node.js 20+ and npm | Only needed to build front-end assets. |
| XAMPP | Only its **MariaDB/MySQL** service is used. Start it from the XAMPP Control Panel. |

## Setup

1. **Create the databases** (XAMPP shell or phpMyAdmin):

   ```sql
   CREATE DATABASE kagjanaw_hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE kagjanaw_hris_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; -- used by the test suite
   ```

2. **Install and configure**:

   ```powershell
   composer install
   copy .env.example .env
   php artisan key:generate
   ```

   Edit `.env` if your database credentials differ from XAMPP's defaults (`root`, no password).

3. **Migrate and seed** (reference data plus demo accounts outside production):

   ```powershell
   php artisan migrate --seed
   ```

4. **Build the front end**:

   ```powershell
   npm install
   npm run build
   ```

5. **Run**:

   ```powershell
   composer run dev     # web server, queue worker and Vite together
   # or just: php artisan serve
   ```

   Open http://localhost:8000.

6. **Run the scheduler** (attendance sync every 5 minutes, nightly DTR processing). During development:

   ```powershell
   php artisan schedule:work
   ```

   On the server, create a **Windows Task Scheduler** task that runs every minute, indefinitely:

   - Program: `C:\path\to\php.exe`
   - Arguments: `artisan schedule:run`
   - Start in: the project folder (for example `C:\inetpub\kagjanaw_hris`)
   - Run whether the user is logged on or not.

   On Linux, use the cron entry `* * * * * cd /path/to/kagjanaw_hris && php artisan schedule:run >> /dev/null 2>&1`.

### OneDrive / read-only folders

If the project lives in a OneDrive folder, Windows may mark directories read-only, which PHP reports as "must be present and writable". Clear the flag once:

```powershell
attrib -R bootstrap\cache
attrib -R storage /S /D
```

## Demo accounts (development only)

`DemoUserSeeder` creates one account per role and 30 sample employees when `APP_ENV` is not `production`. Sign in with the **Employee ID** (or username) below; all four use the password **`Kagjanaw@2026`**.

| Role | Employee ID / username | Employee record |
| --- | --- | --- |
| System Administrator | `admin` | none (system account) |
| HR Officer | `2015-0101` | Maria L. Santos, MHRMO |
| Department Head | `2010-0045` | Juan G. Dela Cruz, Municipal Health Office |
| Employee | `2021-0230` | Ana B. Reyes, Municipal Health Office |

The sample employees have accounts with unknown temporary passwords; reset one under **User Accounts** to sign in as them. Never use these accounts on a production server.

`DemoAttendanceSeeder` also adds a mock time clock ("Main Lobby Time Clock") and syncs it from the start of last month, so DTRs, summaries and the sync log have data.

## Production: first administrator

With `APP_ENV=production`, the demo accounts are not created. Create the first System Administrator with:

```powershell
php artisan app:create-administrator --name="Juan Dela Cruz" --username="admin" --email="admin@cagdianao.gov.ph"
```

The command prompts for the password, so it never appears in shell history. Next, the administrator creates an account for an HR officer under **Administration → User Accounts**. HR then adds employees, and each employee gets a login account automatically.

## Employee accounts and sign-in

- Employees sign in with their **Employee ID** (for example `2021-0230`). System accounts that are not employees, such as `admin`, use a username instead.
- When HR adds an employee, the system creates an **Employee**-role account. Its username is the employee ID, and it gets a random 12-character temporary password. The password is shown once on the employee's record for HR to hand over in person.
- Anyone signing in with a password set by someone else (a new account, or an administrator reset) must choose their own password before they can do anything else.
- The account name and username follow the employee record. Separating an employee (resigned, retired, and so on) deactivates the account; returning them to Active restores it.
- To make an employee a Department Head or HR Officer, the System Administrator changes the role of their account.
- Email is optional. It is only used for "forgot password" links; otherwise the System Administrator resets passwords.

## Environment keys

| Key | Purpose |
| --- | --- |
| `APP_NAME`, `APP_URL` | Application name and the LAN URL users open. |
| `APP_TIMEZONE` | `Asia/Manila`. Attendance and payroll depend on local time. |
| `APP_ENV`, `APP_DEBUG` | Use `production` and `false` on the server. |
| `DB_CONNECTION` | `mariadb` for XAMPP; `mysql` for MySQL 8.0. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection. |
| `SESSION_ENCRYPT` | `true`: session payloads are encrypted at rest. |
| `SESSION_SECURE_COOKIE` | Set to `true` once the server is served over HTTPS. |
| `SESSION_LIFETIME` | Idle minutes before users are signed out (default 120). |
| `QUEUE_CONNECTION` | `database`. Payroll runs are queued from Phase 5. |
| `ATTENDANCE_DRIVER` | Default driver for new biometric devices: `mock` (no hardware). |
| `ATTENDANCE_SYNC_INTERVAL` | Minutes between automatic device syncs (default 5). |
| `MAIL_*` | Password-reset emails. With no mail server on the LAN, keep `MAIL_MAILER=log`; the System Administrator resets passwords instead. |

## Roles and access

Access is enforced on the server by permission middleware on every route group and by policies. Hiding a menu item is never the only protection. Roles and their permissions are defined in [app/Enums/Role.php](app/Enums/Role.php) and [app/Enums/Permission.php](app/Enums/Permission.php), and are seeded by `RolesAndPermissionsSeeder`. Re-running it is safe.

| Role | Access so far |
| --- | --- |
| System Administrator | User accounts (create, assign role, reset password, activate or deactivate), reference data, **biometric devices** (settings, test connection, sync now, sync log), **office hours** and **holidays**. Admins cannot change their own role or deactivate themselves. They do not see employee records or attendance. |
| HR Officer | All employee records and 201 files; attendance summaries, every DTR (view, print, correct, validate, recompute), raw attendance logs and CSV import; plus departments, positions and employment types. |
| Department Head | The basic profile (name, position, type, status, date hired, mobile number) and the attendance summary and DTRs of employees in **their own department**, read-only. No government IDs, salary, birth details, address or 201 documents. |
| Employee | Their own full record and 201 file under **My Record**, and their own DTR under **My Attendance** (with the printable Form 48), read-only. |

Requests for records a user may not see return **404 Not Found**, so changing an ID in the URL does not reveal whether a record exists.

### 201 files

- Accepted files: PDF, JPG and PNG up to 10 MB. The type is checked from the file contents, not just the extension.
- Files are stored on the private disk under `storage/app/private/employees/{employee_id}/` with random names. They are only served through authorized routes, with `nosniff` and `no-store` headers, never by a public URL.
- Government ID numbers (GSIS, PhilHealth, Pag-IBIG, TIN) are encrypted at rest with the `APP_KEY`, and masked on screen until the viewer clicks **Show**. **Back up the `APP_KEY`**: without it, encrypted values cannot be read.

Each account holds exactly one role. Deactivated users cannot sign in, and if they are already signed in they are logged out on their next request.

## Attendance

### How punches become a Daily Time Record

1. **Sync.** The scheduler runs `php artisan attendance:sync` every 5 minutes for each active device; administrators can also click **Sync Now**. Each run re-reads from one day before the last successful sync (seven days on a device's first sync). Duplicates are skipped by a unique index on (biometric ID, time), so syncing again is always safe. Every attempt, including failures and their error messages, appears in the device's sync log.
2. **Flagging.** Punches from a biometric ID that no employee has, or stamped more than 5 minutes in the future (a wrong device clock), are stored but flagged and left out of the DTR. Once HR enters that biometric user ID on an employee's record, earlier unknown punches are attached to that employee automatically.
3. **Processing.** Valid punches are assigned to the four Form 48 columns. Punches before 12:30 belong to the morning, the rest to the afternoon. In each session the first punch is "in" and the last is "out", and repeated taps within 5 minutes count once. `php artisan attendance:process` runs nightly at 00:15 to finalize the previous day, including recording absences on days with no punches.
4. **Rules** (CSC, applied per session):
   - Arriving within the **15-minute grace period** is on time. Arriving later is late **from 8:00 / 1:00**.
   - Leaving before 12:00 / 5:00 is undertime.
   - A session with no punches is a half-day absence.
   - A session with only one punch counts as absent and the day is flagged **Missing punch** for HR.
   - No extra credit for arriving early or staying late.
   - Weekends are rest days and holidays carry no deductions. Today stays **In progress** until 5:00 PM.
5. **HR review.** HR corrects a day's punches with a required reason. Each correction is kept in an audit trail (who, when, before and after, why), and corrected days are never overwritten by reprocessing. HR then **validates** the month, which is refused while any day still has a missing punch. Correcting a validated day requires validating it again.

### CSV import

For a device that cannot be reached, or punches from a paper logbook: **Attendance → Import Attendance**, with a downloadable template.

```csv
biometric_user_id,date,time
1024,2026-10-05,07:58
1024,2026-10-05,12:02
```

The header row is required, and columns may be in any order. Dates are `YYYY-MM-DD`; times are 24-hour `HH:MM`. Bad rows are listed by row number and skipped, and duplicates are skipped.

### Biometric devices

Add each time clock under **Administration → Biometric Devices** (IP address, port 4370, driver). The **mock** driver produces realistic punches for employees with a biometric user ID, so the whole flow can be demonstrated without hardware; a device at IP `0.0.0.0` behaves as unreachable. The real ZKTeco driver (ZKLib over TCP/IP) is added later by implementing `App\Services\Attendance\BiometricDriver` and registering it in `config/attendance.php`.

## Security notes

- Passwords: at least 12 characters, with mixed case, a number and a symbol. The online breached-password check is disabled because the server has no internet access.
- Login is throttled after 5 failed attempts per email and IP address.
- There is no self-registration, and users cannot delete their own accounts.
- Sessions are stored in the database and encrypted.

## Testing

```powershell
php artisan test --compact
```

The suite runs against the `kagjanaw_hris_test` database (see `phpunit.xml`). It covers authentication, the full role/permission matrix, the user policy, and access and validation for every Phase 1 endpoint.

## Assumptions and decisions

- **Laravel 13 instead of 11.** The project was already scaffolded on Laravel 13. It runs on PHP 8.4 (Herd), because XAMPP's PHP 8.2 cannot run it. Only XAMPP's database is used.
- **MariaDB 10.4 (XAMPP) instead of MySQL 8.0.** Migrations avoid MySQL-8-only features, so switching to MySQL 8.0 later only needs `DB_CONNECTION=mysql`.
- **Breeze stubs, not the Breeze installer.** Breeze's Blade views were copied and restyled for Tailwind v4, because the installer would have downgraded the project to Tailwind v3.
- **No biometric device yet.** Phase 3 will build the driver interface, a mock driver and CSV/manual entry. The ZKTeco (ZKLib) driver will be added when a device is available.
- **Department Head scoping** comes from the head's own employee record (`employees.department_id`). A Department Head account without an employee record sees no employees.
- **Employees are never deleted.** Separation is recorded with an employment status and separation date, which keeps the 201 file and later attendance, leave and payroll history intact.
- **Government ID formats** are checked by digit count only: PhilHealth and Pag-IBIG 12 digits, TIN 9–12, GSIS 8–15. Dashes and spaces are removed. Because the numbers are encrypted, the database cannot enforce that they are unique.
- **HR cannot reset passwords.** Only the System Administrator can, which stops an HR account from taking over an administrator's or another officer's account. A lost temporary password is reset by the administrator.
- **Salary grade and step are optional** on the employee record, because Job Order and Contract of Service workers are not paid by salary grade. Phase 5 will add their daily rates.
- **Reference data is deactivated, not deleted**, so historical records keep their department, position and type.
- **Seeded reference data must be verified.** Departments follow the standard municipal offices under RA 7160. Salary grades on positions follow the DBM Index of Occupational Services. Check both against the municipality's approved plantilla.
- **One office schedule for everyone**: 8:00–12:00 and 1:00–5:00, Monday to Friday, 15-minute grace period, no flexible time. Shift work (for example, rural health unit staff on duty schedules) is not yet supported.
- **The grace period applies to both sessions.** Beyond it, lateness counts from the scheduled start, not from the end of the grace period.
- **A missing punch counts as absent for that half-day** until HR corrects it. Months cannot be validated while missing punches remain.
- **The device's in/out key is ignored.** Punches are assigned to the four columns by time, because employees often press the wrong key.
- **Seeded 2026 holidays must be checked** against the presidential proclamation. Movable holidays (Eid'l Fitr, Eid'l Adha) and local holidays of Cagdianao and the Province of Dinagat Islands are added by the System Administrator.
- **Office hours and holidays are managed by the System Administrator**, as part of system configuration. Changes apply to days processed afterwards; HR can recompute earlier months from each DTR.

# Kagjanaw HRIS

A web-based, on-premise Human Resource Information System for the **Municipality of Cagdianao, Province of Dinagat Islands**. It replaces paper-based HR processes with one system for employee records, biometric attendance, leave management and payroll. It runs on the municipal office LAN and works in desktop and mobile browsers.

## Status

| Phase | Scope | Status |
| --- | --- | --- |
| 1 | Foundation: authentication, roles and permissions, layout, role dashboards, user accounts, departments, positions, employment types | Done |
| 2 | Employee records and 201 files, automatic employee login accounts, sign-in by employee ID, forced password change | Done |
| 3 | Attendance: device driver interface and mock driver, scheduled sync, CSV import, DTR processing (CSC Form 48), HR corrections with audit trail, validation, summaries and DTR PDF; office hours and holidays | Done |
| 4 | Leave: all CSC leave types, filing with rule checks, endorsement (department head or Municipal Mayor) and HR approval, leave ledger (leave card), monthly credits and tardiness deductions from validated DTRs, forced-leave forfeiture, opening balance import, Certificate of Leave Credits, utilization report, in-app notifications | Done |
| 5 | Payroll: semi-monthly regular and Job Order / COS payrolls, SSL VI Salary Schedule at the LGU income-class rate, allowances (PERA), absences and tardiness without pay from validated DTRs, GSIS / PhilHealth / Pag-IBIG and withholding tax from dated tables, loans with a minimum net pay, queued computation, approve and release with locking, payslip PDFs, payroll register (Excel, CSV, PDF) | Done |
| 6 | Announcements (all employees or chosen offices) with bell notifications and a notifications page; Reports page with employee list, attendance summary, leave utilization and payroll summary as Excel, CSV and PDF (department heads limited to their office); audit log of changes, access to personal data, sign-ins and denied requests; database and file backups with restore; System Settings | Done |
| 7 | Hardening: an access test of every route for every role, security headers and Content Security Policy, sign-out of other sessions on a password change, protection of Excel/CSV exports against formula injection, an HTTPS switch, N+1 query detection, a production checklist and the [User Guide](USER_GUIDE.md) | Done |

**End users:** see the [User Guide](USER_GUIDE.md), organized by role.

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

   Payrolls are computed by the **queue worker**. `composer run dev` starts one; on the server, keep one running (for example as a Windows service or scheduled task):

   ```powershell
   php artisan queue:work --timeout=80
   ```

   Without a worker, a payroll stays at "Computing…".

6. **Run the scheduler** (attendance sync every few minutes, nightly DTR processing, daily leave credit posting, yearly forced-leave forfeiture, the daily backup and nightly audit log pruning). The sync interval and the backup time are set under **Administration → System Settings**. During development:

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

`DemoUserSeeder` creates one account per role and 30 sample employees when `APP_ENV` is not `production`. Sign in with the **Employee ID** (or username) below; all five use the password **`Kagjanaw@2026`**.

| Role | Employee ID / username | Employee record |
| --- | --- | --- |
| System Administrator | `admin` | none (system account) |
| HR Officer | `2015-0101` | Maria L. Santos, MHRMO |
| Municipal Mayor | `2022-0001` | Ramon T. Villanueva, Office of the Municipal Mayor (elective) |
| Department Head | `2010-0045` | Juan G. Dela Cruz, Municipal Health Office |
| Employee | `2021-0230` | Ana B. Reyes, Municipal Health Office |

The sample employees have accounts with unknown temporary passwords; reset one under **User Accounts** to sign in as them. Never use these accounts on a production server.

`DemoAttendanceSeeder` also adds a mock time clock ("Main Lobby Time Clock") and syncs it from the start of last month, so DTRs, summaries and the sync log have data.

`DemoLeaveSeeder` then builds the leave data through the real services:
- An opening balance for every employee who earns credits, as of the end of the month before last.
- An approved sick leave last month, which shows as **On leave** on the DTR.
- Last month's missing punches corrected, every DTR validated and the month's credits posted.
- One application in each state of the workflow:
  - Waiting for Juan (department head).
  - Juan's own leave, waiting for the Mayor.
  - The Mayor's leave, waiting for HR.
  - A leave already endorsed to HR.
  - A rejected leave.
  - A leave the employee withdrew.

`DemoPayrollSeeder` finally builds the payroll data through the real services:
- Daily rates for the Job Order workers, a few loans and cooperative deductions, and RATA for Juan (at a demo amount).
- Last month's regular and Job Order / COS payrolls for both halves, approved and released, so employees see payslips.
- This month's first regular payroll, computed and waiting for review.

`DemoAnnouncementSeeder` posts three announcements from the HR Office (one pinned, one for the Municipal Health Office only), so the dashboards and the notification bell have content.

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
| `APP_FORCE_HTTPS` | `true` once the server has a certificate: every link and redirect uses `https://`. |
| `APP_TIMEZONE` | `Asia/Manila`. Attendance and payroll depend on local time. |
| `APP_ENV`, `APP_DEBUG` | Use `production` and `false` on the server. |
| `DB_CONNECTION` | `mariadb` for XAMPP; `mysql` for MySQL 8.0. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection. |
| `SESSION_ENCRYPT` | `true`: session payloads are encrypted at rest. |
| `SESSION_SECURE_COOKIE` | Set to `true` once the server is served over HTTPS. |
| `SESSION_LIFETIME` | Idle minutes before users are signed out (default 120). |
| `QUEUE_CONNECTION` | `database`. Payroll computations are queued; run `php artisan queue:work`. |
| `ATTENDANCE_DRIVER` | Default driver for new biometric devices: `mock` (no hardware). |
| `ATTENDANCE_SYNC_INTERVAL` | Minutes between automatic device syncs (default 5), until the administrator sets the interval under **System Settings**. |
| `BACKUP_MAX_UPLOAD_MB` | Largest backup the administrator may upload (default 512). PHP's `upload_max_filesize` and `post_max_size` must allow it too. |
| `MAIL_*` | Password-reset emails. With no mail server on the LAN, keep `MAIL_MAILER=log`; the System Administrator resets passwords instead. |

## Roles and access

Access is enforced on the server by permission middleware on every route group and by policies. Hiding a menu item is never the only protection. Roles and their permissions are defined in [app/Enums/Role.php](app/Enums/Role.php) and [app/Enums/Permission.php](app/Enums/Permission.php), and are seeded by `RolesAndPermissionsSeeder`. Re-running it is safe.

| Role | Access |
| --- | --- |
| System Administrator | User accounts (create, assign role, reset password, activate or deactivate), reference data, **biometric devices** (settings, test connection, sync now, sync log), **backups** (create, upload, download, restore, delete), the **audit log** and **System Settings**. Admins cannot change their own role or deactivate themselves. They do not see employee records, attendance, leave or reports. |
| HR Officer | All employee records and 201 files; attendance summaries, every DTR (view, print, correct, validate, recompute), raw attendance logs and CSV import; **office hours and holidays**; **leave**: approve or reject endorsed applications, cancel approved leave, leave cards and adjustments, monthly posting, opening balance import, Certificate of Leave Credits, leave types, utilization report; **payroll**: create, compute, approve and release payrolls, pay setup (allowances, loans), Salary Schedule, contribution and tax tables, payroll settings, every payslip and the payroll register; **announcements** (post, edit, delete, drafts); every **report** for any office, including the payroll summary; plus departments, positions and employment types. HR officers cannot approve their own leave. |
| Municipal Mayor | Everything a Department Head can do for the Office of the Mayor, plus **endorsing department heads' leave** (and leave from offices with no department head in the system). |
| Department Head | The basic profile (name, position, type, status, date hired, mobile number) and the attendance summary and DTRs of employees in **their own department**, read-only, **endorsing or rejecting their leave applications**, and the **reports** of their own office (employee list, attendance summary, leave utilization). No government IDs, salary, birth details, address or 201 documents. |
| Employee | Their own full record and 201 file under **My Record**, their own DTR under **My Attendance** (with the printable Form 48), and **My Leave**: balances, leave card, applying for leave and withdrawing an application still waiting for a decision, and **My Payslips**: their own payslips once a payroll is released. |

Everyone with an employee record (HR, the Mayor and department heads included) files their own leave and sees their own payslips the same way. Department heads and the Mayor see no one else's pay. Every user reads the **announcements** addressed to them and has a **Notifications** page.

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

### Office hours and holidays

HR maintains these under **Work Calendar**. Adding, moving or removing a past or current holiday recomputes that day's DTRs immediately. The 2026 national holidays are seeded; check them against the presidential proclamation, and add movable holidays (Eid'l Fitr, Eid'l Adha) and Cagdianao or provincial local holidays.

## Leave

### Leave types

All leave types on CS Form No. 6 are seeded, each with its legal basis and rules:
- Vacation, Mandatory/Forced and Sick Leave.
- Maternity, Paternity and Solo Parent Leave.
- Special Privilege Leave.
- Study Leave.
- 10-Day VAWC Leave.
- Rehabilitation Privilege.
- Special Leave Benefits for Women.
- Special Emergency (Calamity) Leave.
- Adoption Leave.
- Wellness Leave.

HR can change every rule under **Leave → Leave Types** when regulations change:
- What the type is charged to.
- Days per year and per application.
- Calendar or working days.
- Half days allowed.
- Advance notice required.
- Filing after the leave allowed.
- Available to one sex only.
- When an attachment is required.

Vacation, sick and forced leave drive the leave card, so their code and charging cannot be changed. **Verify all seeded values against current CSC rules.**

| Charged to | Types | Rule |
| --- | --- | --- |
| Vacation leave credits | Vacation, Forced (max 5 days a year) | Days beyond the balance are without pay. |
| Sick leave credits, then vacation leave credits | Sick | Beyond both balances, without pay. |
| Own yearly allotment | Special Privilege (3), Solo Parent (7), Calamity (5), Wellness (5) | Cannot be exceeded; not carried over. |
| Not charged | Maternity (105 calendar days), Paternity (7), VAWC (10), Study, Rehabilitation, Special Leave Benefits for Women, Adoption | Up to the legal maximum per application. |

### Workflow

1. **Filing.** The employee files under **My Leave → Apply for Leave**. The application is checked against its type's rules:
   - Advance notice (e.g. vacation leave 5 days ahead).
   - Whether it can be filed after the fact (e.g. sick leave).
   - Half days on a single date only.
   - Rest days and holidays are not counted.
   - Allotments and maximums.
   - Overlapping applications.
   - Required attachments (e.g. a medical certificate for sick leave over 5 days).
2. **Endorsement.**
   - **Employees** are endorsed by their department head.
   - **Department heads** (and offices with no department head in the system) are endorsed by the **Municipal Mayor**.
   - **The Mayor's own leave** needs no endorsement and goes straight to HR.

   The endorser endorses or rejects with remarks.
3. **Approval.** HR approves or rejects. Approval charges the credits inside a database transaction that locks the balances, splits the days into with and without pay, and marks the days **On leave** on the DTR so they are not counted as absences.
4. **Cancellation.**
   - The employee may withdraw an application that is still waiting for a decision.
   - HR may cancel approved leave, for example when the employee is recalled to work. This restores the credits and returns the days to the DTR.

The employee is notified in the app (the bell at the top of every page) at each step. Endorsers are notified of new applications, and HR is notified of endorsed ones.

### Leave card and monthly credits

Each employee's vacation and sick leave are kept as a **ledger (leave card)**:
- **Append-only:** every line is dated, with the balance after it, and is never edited. Mistakes are fixed with an adjustment that records the reason and who posted it.
- **Monthly posting:** when HR validates a finished month's DTR, that month is posted automatically:
  - **Earned:** 1.250 days of vacation and of sick leave. Partial months are prorated, and days of unexcused absence or leave without pay reduce the credits (the CSC tables).
  - **Tardiness and undertime:** the month's total minutes are converted to days with the CSC table (1 hour = 0.125 day) and deducted from vacation leave. What vacation leave cannot cover is recorded as without pay, for payroll.
- **Catch-up:** `php artisan leave:post-credits` also runs daily for last month, to catch DTRs validated late.
- **Re-validation:** if a DTR is corrected and validated again, only the difference is posted.
- **Forced leave:** in mid-January, `php artisan leave:forfeit-forced-leave` forfeits unused forced leave. This applies to employees who had 10 or more days of vacation leave and took fewer than 5 days of vacation or forced leave that year.
- **Monitoring:** **Leave → Monthly Posting** shows who is posted, ready or waiting (and why), with a button to post everyone ready.

Elective officials and Job Order / Contract of Service workers do not earn credits. This is set per employment type under **Reference Data → Employment Types**.

### Opening balances

When the system starts keeping the leave cards, HR imports each employee's balances from their paper leave card under **Leave → Leave Credits → Import Opening Balances**, as of the last day of a month.

```csv
employee_number,vacation_leave,sick_leave
2021-0230,45.250,60.000
```

Employees who already have leave card entries are skipped (use an adjustment instead). Bad rows are listed by row number. Credits are posted from the month after the cut-off.

### Certificate of Leave Credits

On an employee's leave card, HR can print a **Certificate of Leave Credits** (PDF) as of any date. It follows the "Certification of Leave Credits" block of CS Form No. 6: total earned, less applications still pending, and balance.

## Payroll

### Payrolls and pay periods

Pay is **semi-monthly**: the 1st to the 15th, and the 16th to the end of the month. Each period has two separate payrolls, both run by HR under **Payroll Runs**:
- **Regular payroll:** everyone whose employment type is paid by salary grade (permanent, temporary, coterminous, casual, contractual, elective).
- **Job Order / COS payroll:** everyone whose employment type is paid by daily rate.

The pay basis is set per employment type under **Reference Data → Employment Types**. A Job Order worker's daily rate is entered on their 201 record.

### Workflow

1. **Create** a payroll for a month, half and pay basis. It is queued for computation right away. The page shows "Computing…" and refreshes until the queue worker is done.
2. **Review** the employees, their payslips and the **Needs attention** list:
   - "Must fix" items block approval. Examples: an employee with no salary grade or daily rate, a Job Order DTR not yet validated, or deductions larger than pay.
   - Notes are information only. Example: a month's DTR not yet validated, so its absences will be deducted later.
   - Fix the records and **Recompute** as often as needed; each computation replaces the previous one.
3. **Approve.** An approved payroll can be **reopened** for corrections.
4. **Release.** The payroll is locked for good, each employee gets an in-app notification, and their payslip appears under **My Payslips**. Corrections after release go in a later payroll.

A payroll that is not yet approved can be deleted.

### Regular pay

Every amount is computed in whole centavos and stored as a decimal (never floats). Rounding is half up.

- **Basic pay:** half the monthly salary. The first half gets the lower half of an odd centavo, the second half the rest, so the month always adds up.
- **Monthly salary:** the national Salary Schedule amount for the employee's grade and step on the first day of the period, times the percentage for the LGU's income class, rounded to the peso.
  - The seeded schedule is **SSL VI, third tranche, effective January 1, 2026** (EO No. 64, s. 2024; DBM NBC No. 601, Annex A).
  - Municipalities pay 1st class 90%, 2nd 85%, 3rd 80%, 4th 75%, 5th 70% of the schedule (DBM Local Budget Circulars).
  - Set the class under **Payroll Settings**. Until HR ticks "Confirmed", payroll uses the 5th class (70%) and shows a reminder.
- **Partial periods:** new hires and separations are paid for the scheduled working days in service out of the period's working days. Holidays on working days count as paid.
- **Allowances:** half the monthly amount per payroll, prorated like basic pay.
  - An allowance covers either every salary-grade employee or only those it is assigned to.
  - An assignment can give one employee a different amount or period.
  - **PERA** (₱2,000 a month, not taxable, reduced for days without pay) is seeded.
- **Absences and tardiness without pay** are deducted in the first payroll after the month's DTR is validated:
  - Unexcused absences on the DTR.
  - Approved leave without pay.
  - Tardiness and undertime that vacation leave could not cover (see the leave card).
  - A day is the monthly salary ÷ 22 and a minute is a day ÷ 480. Allowances "reduced for days without pay" are reduced the same way.
  - If more is owed than one payroll can bear, the rest is deducted in the next one.
  - If a deducted month is corrected later, the next payroll deducts or refunds only the difference.
  - Months covered by the opening leave balance, and months before **Deduct absences from** in Payroll Settings, are never deducted.
- **GSIS, PhilHealth and Pag-IBIG:** half of the monthly contribution on the monthly salary, using the rate in force on the first day of the period. The employer shares are kept for remittance.

  | Contribution | Employee | Employer | Salary used |
  | --- | --- | --- | --- |
  | GSIS (RA 8291) | 9% | 12% | full monthly salary |
  | PhilHealth (RA 11223) | 2.5% | 2.5% | ₱10,000 to ₱100,000 |
  | Pag-IBIG (HDMF) | 2% | 2% | up to ₱10,000 |

- **Withholding tax:** from the BIR monthly table in force (TRAIN, effective 2023). Taxable pay is basic pay plus taxable allowances, less absences and the employee's contributions. Each half withholds half the monthly-table tax on twice its taxable pay.
- **Loans and other deductions** (GSIS and Pag-IBIG loans, cooperative, dues) are set up per employee under **Pay Setup**:
  - Half the monthly amount is deducted per payroll, in priority order.
  - A deduction stops at its end date or once its total has been deducted.
  - Loans stop where net pay would fall below half the **minimum monthly net take-home pay** (₱5,000 by default). The shortfall is noted on the payslip. Contributions and tax are always deducted.

### Job Order / COS pay

- **Pay:** daily rate × time worked in the period, from the validated DTR (8 hours = 1 day; holidays and absences are unpaid).
- **Tax:** the rate HR sets under Payroll Settings (5% by default) is withheld.
- **Other deductions:** loans and other deductions apply. There is no GSIS, PhilHealth or Pag-IBIG, and no minimum net pay.
- **Before approval:** the period's DTR must be validated.

### Rates and tables

Rates and tables are versioned with effectivity dates. Enter a new version ahead of time and payrolls switch to it on that date.
- **Salary Schedule:** shows the national and LGU rate for every grade and step. **Add tranche** copies the schedule in force for editing.
- **Contributions & Tax:** add a new GSIS, PhilHealth or Pag-IBIG rate, or a new tax table (copied from the one in force).

### Payslips and register

- **Payslip (PDF, A5):** earnings, deductions, net pay and notes. Employees see their own once released; HR sees all, marked "for review only" before release.
- **Payroll register:** one row per employee, by office then name, with totals and employer shares. Exported as Excel (.xlsx), CSV or PDF (legal, landscape, with signature lines).

## Announcements and notifications

- HR posts announcements under **Announcements** to **all employees** or to **selected offices**, with an optional expiry date and pin. A draft is not shown until it is published.
- Publishing notifies every active account in the audience through the bell. Later edits do not notify again.
- Dashboards show the three newest announcements addressed to the user, pinned first. Expired announcements disappear for employees; HR still sees them, marked "Expired".
- Messages are plain text; HTML is shown as typed, never run.
- The bell lists the eight newest notifications. **View all notifications** opens the full list.

## Reports

**Reports** (HR, department heads and the Mayor) gathers the reports a user may run. Each downloads as **Excel (.xlsx)**, **CSV** or **PDF**:

| Report | HR | Department head / Mayor |
| --- | --- | --- |
| Employee list (office, position, type, salary grade, date hired, status; never government IDs) | Any office | Own office |
| Attendance summary (monthly) | Any office | Own office |
| Leave utilization (month or year, by type and office) | Any office | Own office |
| Payroll summary (released payrolls only, by office and by payroll, with employer shares) | Yes | No |

The payroll register of a single payroll stays on the payroll page. Every export is recorded in the audit log.

## Administration

### Audit log

**Administration → Audit Log** (System Administrator) records:
- **Changes to records**: who created, changed or deleted employees, 201 documents, user accounts and roles, leave applications, DTR corrections, payrolls, pay setup, salary schedule and tax tables, reference data, the work calendar, announcements and settings, with the values before and after.
- **Access to personal data**: opening another employee's 201 file, documents, payslip, leave card or Certificate of Leave Credits, and every report or payroll register export. Employees looking at their own records are not logged.
- **Sign-ins and denied access**: sign-ins, sign-outs, failed sign-ins, lockouts, password resets, and every refused request (403) with its address.
- **Backups**: creating, uploading, downloading, restoring and deleting backups.

Government ID numbers and passwords are never written; the log only shows that they changed. Entries cannot be edited or deleted from the application. Entries older than the retention period (System Settings, default 5 years) are pruned nightly.

### Backups and restore

**Administration → Backups** (System Administrator):
- A backup is one `.zip` in `storage/app/backups` holding the whole database (`database.sql`), every uploaded file (201 documents, leave attachments) and a manifest. Sessions, cache and queue tables are left out.
- The **daily backup** runs at the time set in System Settings (default 6:00 PM); only the newest backups are kept (default 30). **Back Up Now** makes one at any time.
- **Copy backups off the server** (USB drive or another computer) regularly. A backup on the same disk does not survive a disk failure. Keep the `.env` file (with its `APP_KEY`) with them: without it, encrypted government IDs cannot be read.
- **Restore** asks for the administrator's password. It first makes a safety backup of the current data, replaces the database and the uploaded files, and then runs any newer migrations. If the restore fails, the system returns to the safety backup automatically. A backup made by a newer version of the system is refused.
- A backup from another computer can be uploaded and then restored. If it is larger than PHP's upload limit, copy it into `storage\app\backups` instead.

From the command line:

```powershell
php artisan backup:create                      # back up now
php artisan backup:restore                     # list the backups
php artisan backup:restore kagjanaw-hris-2026-10-13-180000-scheduled.zip
```

### System Settings

**Administration → System Settings** (System Administrator): the biometric sync interval, whether and when the daily backup runs, how many backups to keep, and how long audit log entries are kept. Office hours, holidays and payroll settings belong to HR.

## Security notes

- Passwords: at least 12 characters, with mixed case, a number and a symbol. The online breached-password check is disabled because the server has no internet access.
- Login is throttled after 5 failed attempts per username and IP address.
- There is no self-registration, and users cannot delete their own accounts.
- Sessions are stored in the database and encrypted.
- Changes, access to personal data, failed sign-ins and refused requests are recorded in the audit log (RA 10173). A failed sign-in with an unknown username records only "Unknown username", in case a password was typed into the username field.
- Backups contain all personal data. Only the System Administrator can download them, each download is logged, and they are served with `no-store` headers.
- A password change (by the user, or a reset by the administrator) signs the account out on every other browser. The browser that made the change stays signed in.
- Every response carries `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin` and a `Permissions-Policy`. Over HTTPS, `Strict-Transport-Security` is added.
- Pages carry a **Content Security Policy**: scripts, styles, images and fonts load only from the server itself, forms post only to it, and no other site can frame it. Inline `<script>` blocks and `onclick`/`onsubmit` attributes are refused, so views must not use them (a test checks this); confirmation prompts use `data-confirm="…"` on the form. `'unsafe-eval'` is allowed because Alpine.js evaluates its attributes that way. While `npm run dev` runs, the Vite dev server is allowed too.
- Excel and CSV exports store typed-in text (names, positions, offices) as text, never as a formula, so a value such as `=HYPERLINK(…)` cannot run when HR opens the file.
- No query interpolates user input; filters and sorting are bound or chosen from fixed lists. All Blade output is escaped (`{{ }}`); the templates contain no `{!! !!}`.

## Production checklist

Before the system holds real data:

1. **`.env`:** `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`, and a new `APP_KEY` (`php artisan key:generate`). Keep a copy of `.env` with the backups (see [Backups and restore](#backups-and-restore)).
2. **Database account:** create a MariaDB user for the application with a strong password, instead of `root` without one. It needs every privilege on `kagjanaw_hris`; restoring a backup needs nothing more.
3. **Web server:** point the document root at the `public` folder only (never the project folder), and use Apache or Nginx rather than `php artisan serve`. In `php.ini`, set `expose_php = Off` and `display_errors = Off`, and enable OPcache (`opcache.enable=1`).
4. **HTTPS** (recommended even on the LAN, because passwords and personal data cross it): install a certificate (for example from the provincial IT office, or a self-signed one trusted on the office computers), redirect `http://` to `https://` in the web server, then set `APP_URL=https://…`, `APP_FORCE_HTTPS=true` and `SESSION_SECURE_COOKIE=true`.
5. **Install and optimize:**

   ```powershell
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   php artisan migrate --force
   php artisan optimize          # caches config, routes, events and views
   ```

   Run `php artisan optimize` again after every update or `.env` change (`php artisan optimize:clear` undoes it).
6. **Background tasks:** the queue worker and the scheduler task (see [Setup](#setup)), both running as a service account.
7. **First administrator:** `php artisan app:create-administrator` (see below). The demo accounts are not created in production.
8. **Network:** allow only the office LAN to reach the server (Windows Firewall), and keep the database port (3306) closed to other computers.
9. **Verify the seeded values** listed under [Assumptions and decisions](#assumptions-and-decisions): holidays, salary schedule, contribution and tax tables, leave rules and the LGU income class.

## Performance

- **N+1 queries are caught in development.** Loading a relationship one model at a time inside a loop fails the test suite and is logged as a warning on a development server (`storage/logs`); production is unaffected. Lists eager-load what they show.
- **Indexes** cover the frequent lookups: attendance logs by employee and time, DTRs by employee and date (unique), leave ledger entries by employee, type and date, leave applications by status, payroll items by run and employee, and the audit log by event and date. Foreign keys are indexed.
- **Large lists are paginated** on the server (audit log, attendance logs, notifications, announcements). HR's leave application list is limited to one year at a time.
- **Heavy work runs outside the request:** payroll computation on the queue, device sync, DTR processing, credit posting and backups on the scheduler.
- In production, `php artisan optimize` and OPcache (see the checklist) make the biggest difference on a modest server.

## Testing

```powershell
php artisan test --compact
```

The suite runs against the `kagjanaw_hris_test` database (see `phpunit.xml`) and takes several minutes. It covers:
- Authentication and the full role/permission matrix.
- **Every route for every role** (`tests/Feature/RouteAuthorizationTest.php`): the roles allowed on each route are written out in the test. Guests are sent to sign in, every other role gets 403 or 404, and allowed roles open every page. A new route that is missing from the list fails the suite, so access is always decided on purpose.
- Security headers, the Content Security Policy (including a check that no view has inline scripts or event handlers), session sign-out after a password change, formula-safe exports and the HTTPS switch.
- The access rules for employee records, DTRs and leave, including changing IDs in the URL.
- Unit tests of the DTR rules, the CSC leave tables (checked against published values), leave charging and centavo arithmetic.
- Payroll computation against amounts worked out by hand from the seeded tables: halves, income-class rates, proration, allowances, contributions, tax, absences without pay (including partial and corrected months), loans and the minimum net pay, and Job Order pay.
- The payroll workflow, locking after release, payslip access and the register exports.
- End-to-end flows: biometric punches → DTR → validated month → leave card; leave approval → balance → DTR; and biometric punches with leave without pay → validated month → payroll deduction.
- Announcements: audiences, drafts, expiry, notifications sent once, and plain-text output.
- Reports: each role's reports, department heads held to their own office (also when asking for another), payroll summary totals, and the Excel, CSV and PDF exports.
- The audit log: values before and after, redaction of government IDs and passwords, sign-in events, refused requests, access logging, the admin screen and pruning.
- Backups: archive contents, pruning, restore with password, automatic return to the safety backup on failure, refusal of newer backups, uploads and safe file names. The SQL dump and restore run for real against a scratch database (`kagjanaw_hris_test_dumper`, created and dropped by the test), so the database user needs the CREATE and DROP DATABASE privileges.

## Assumptions and decisions

- **Laravel 13 instead of 11.** The project was already scaffolded on Laravel 13. It runs on PHP 8.4 (Herd), because XAMPP's PHP 8.2 cannot run it. Only XAMPP's database is used.
- **MariaDB 10.4 (XAMPP) instead of MySQL 8.0.** Migrations avoid MySQL-8-only features, so switching to MySQL 8.0 later only needs `DB_CONNECTION=mysql`.
- **Breeze stubs, not the Breeze installer.** Breeze's Blade views were copied and restyled for Tailwind v4, because the installer would have downgraded the project to Tailwind v3.
- **No biometric device yet.** Attendance runs on the mock driver and CSV import. The ZKTeco (ZKLib) driver will be added when a device is available.
- **Department Head scoping** comes from the head's own employee record (`employees.department_id`). A Department Head account without an employee record sees no employees.
- **Employees are never deleted.** Separation is recorded with an employment status and separation date, which keeps the 201 file and later attendance, leave and payroll history intact.
- **Government ID formats** are checked by digit count only: PhilHealth and Pag-IBIG 12 digits, TIN 9–12, GSIS 8–15. Dashes and spaces are removed. Because the numbers are encrypted, the database cannot enforce that they are unique.
- **HR cannot reset passwords.** Only the System Administrator can, which stops an HR account from taking over an administrator's or another officer's account. A lost temporary password is reset by the administrator.
- **Salary grade and step are optional** on the employee record, because Job Order and Contract of Service workers are paid by a daily rate instead.
- **Reference data is deactivated, not deleted**, so historical records keep their department, position and type.
- **Seeded reference data must be verified.** Departments follow the standard municipal offices under RA 7160. Salary grades on positions follow the DBM Index of Occupational Services. Check both against the municipality's approved plantilla.
- **One office schedule for everyone**: 8:00–12:00 and 1:00–5:00, Monday to Friday, 15-minute grace period, no flexible time. Shift work (for example, rural health unit staff on duty schedules) is not yet supported.
- **The grace period applies to both sessions.** Beyond it, lateness counts from the scheduled start, not from the end of the grace period.
- **A missing punch counts as absent for that half-day** until HR corrects it. Months cannot be validated while missing punches remain.
- **The device's in/out key is ignored.** Punches are assigned to the four columns by time, because employees often press the wrong key.
- **Seeded 2026 holidays must be checked** against the presidential proclamation. Movable holidays (Eid'l Fitr, Eid'l Adha) and local holidays of Cagdianao and the Province of Dinagat Islands are added by the System Administrator.
- **Office hours and holidays are managed by HR**, as part of the core work calendar. The System Administrator no longer sees them. Changes apply to days processed afterwards; HR can recompute earlier months from each DTR.
- **A Municipal Mayor role** endorses department heads' leave. The Mayor's own leave goes straight to HR. If no Mayor account exists, department heads' leave also goes straight to HR, so nothing gets stuck.
- **Credits are charged on final approval only.** Applications still waiting are shown as "applied for" against the balance, but are not deducted until HR approves.
- **Unexcused absences count as leave without pay** when computing earned credits. An absence covered by approved leave with pay does not.
- **Tardiness and undertime are posted per month**, after the DTR is validated, as one leave card line converted with the CSC table.
- **Forced leave** counts both vacation and forced leave taken toward the 5 days. Unused days are forfeited in mid-January, so December leave can be approved first.
- **Half-day leave** is morning or afternoon on a single date. Two half-day applications on the same date are not allowed (file a whole day instead).
- **Employees who do not earn credits** (elective, Job Order, Contract of Service) cannot file vacation or sick leave. They can still file leave granted by law or by yearly allotment.
- **Leave rules are seeded and must be verified**, especially Wellness Leave, whose governing circular should be confirmed. HR can change every value under **Leave Types**.
- **SSL VI instead of SSL V.** The spec named SSL V, but the EO No. 64, s. 2024 schedule is in force in 2026. The seeded amounts are the national third-tranche rates (DBM NBC No. 601, Annex A). LGUs may adopt tranches on their own timetable, so HR adds tranches under **Salary Schedule** when the Sanggunian approves them.
- **Income class not yet known.** Payroll uses the 5th-class rate (70%) until HR sets and confirms the municipality's class under **Payroll Settings**. The percentage for each class is editable, for an LGU that adopted a lower rate within its Personal Services limit.
- **Seeded rates must be verified** before production use: contribution rates and ceilings, the TRAIN tax table, PERA, the ₱5,000 minimum net pay and the 5% Job Order tax rate. All are editable, with effectivity dates.
- **Equal halves.** Salary, allowances, contributions and tax are split evenly between the two halves of the month. Absences are deducted whole, in the first payroll after the DTR is validated.
- **Tax is not annualized.** Each payroll uses the monthly table. Year-end adjustment (BIR Form 2316) is done outside the system.
- **HR alone computes, approves and releases payroll.** There is no separate Accountant or Mayor approval in the system; the printed register has signature lines for them.
- **Only elective and Job Order / COS employees are exempt from absence deductions**: deductions follow the leave card, which those employees do not have. Job Order workers are paid only for time worked instead.
- **Contributions are not reduced for absences.** GSIS, PhilHealth and Pag-IBIG are computed on the full monthly salary (prorated only for partial months of service).
- **Backups are made in PHP**, not with `mysqldump`, so they work the same on any server without extra programs or paths to configure. They suit the size of a municipal HRIS; a very large database would be better served by `mysqldump`.
- **Restore covers the database and uploaded files, not `.env`.** The `APP_KEY` must be the one in use when the backup was made, or encrypted government IDs cannot be read.
- **Sessions, cache and queue tables are not backed up or restored**, so the administrator stays signed in during a restore. Restore when no payroll is being computed.
- **Audit log retention defaults to 5 years.** Confirm the period with the municipality's records officer against the National Archives of the Philippines retention schedule, and set it under System Settings.
- **Employees viewing their own records are not logged**, only access by someone else. Department heads opening their team's DTRs are not logged either; 201 files, documents, payslips, leave cards, certificates and exports are.
- **Announcements are plain text**, without attachments or formatting, so nothing in them can run in the browser.
- **The payroll summary counts released payrolls only.** Draft, computed and approved payrolls can still change.
- **Department heads' reports follow their own employee record**, like the rest of their access. Without an office, the reports are empty rather than showing every office.
- **The Content Security Policy allows `'unsafe-eval'`**, because the standard Alpine.js build needs it. Removing it would mean switching to Alpine's CSP build and rewriting every `x-data` expression. Inline scripts stay blocked, which is the main protection.
- **HTTPS is optional** and switched on with `APP_FORCE_HTTPS`, because the server may start on the LAN without a certificate. It is strongly recommended (see the production checklist).
- **The User Guide is a Markdown file** ([USER_GUIDE.md](USER_GUIDE.md)) in the repository, organized by role. It can be printed or converted to PDF for distribution.
- **Development tool advisories.** `composer audit` reports no advisories for the PHP packages (October 2026). `npm audit` reports advisories in `concurrently` (through `shell-quote`) and `source-map-js`. Both are only used to build the assets on the developer's computer and are not shipped to browsers. They will be updated with the next dependency upgrade.

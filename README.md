# Kagjanaw HRIS

A web-based, on-premise Human Resource Information System for the **Municipality of Cagdianao, Province of Dinagat Islands**. It replaces paper-based HR processes with one system for employee records, biometric attendance, leave management and payroll. It runs on the municipal office LAN and works in desktop and mobile browsers.

## Status

| Phase | Scope | Status |
| --- | --- | --- |
| 1 | Foundation: authentication, roles and permissions, layout, role dashboards, user accounts, departments, positions, employment types | Done |
| 2 | Employee records and 201 files | Planned |
| 3 | Attendance: device driver interface, mock driver, CSV import, DTR processing, HR review, reports | Planned |
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

### OneDrive / read-only folders

If the project lives in a OneDrive folder, Windows may mark directories read-only, which PHP reports as "must be present and writable". Clear the flag once:

```powershell
attrib -R bootstrap\cache
attrib -R storage /S /D
```

## Demo accounts (development only)

`DemoUserSeeder` creates one account per role when `APP_ENV` is not `production`. All use the password **`Kagjanaw@2026`**.

| Role | Email |
| --- | --- |
| System Administrator | admin@cagdianao.local |
| HR Officer | hr@cagdianao.local |
| Department Head | depthead@cagdianao.local |
| Employee | employee@cagdianao.local |

Never use these accounts on a production server.

## Production: first administrator

With `APP_ENV=production`, the demo accounts are not created. Create the first System Administrator with:

```powershell
php artisan app:create-administrator --name="Juan Dela Cruz" --email="admin@cagdianao.gov.ph"
```

The command prompts for the password, so it never appears in shell history. The administrator then creates every other account under **Administration → User Accounts**.

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
| `MAIL_*` | Password-reset emails. With no mail server on the LAN, keep `MAIL_MAILER=log`; the System Administrator resets passwords instead. |

## Roles and access

Access is enforced on the server by permission middleware on every route group and by policies. Hiding a menu item is never the only protection. Roles and their permissions are defined in [app/Enums/Role.php](app/Enums/Role.php) and [app/Enums/Permission.php](app/Enums/Permission.php), and are seeded by `RolesAndPermissionsSeeder`. Re-running it is safe.

| Role | Phase 1 access |
| --- | --- |
| System Administrator | User accounts (create, assign role, activate or deactivate) and reference data. Admins cannot change their own role or deactivate themselves. |
| HR Officer | Departments, positions and employment types. |
| Department Head | Own dashboard and account. |
| Employee | Own dashboard and account. |

Each account holds exactly one role. Deactivated users cannot sign in, and if they are already signed in they are logged out on their next request.

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
- **Department Head scoping** will come from the employee record in Phase 2 (`employees.department_id`), not from the user account.
- **Reference data is deactivated, not deleted**, so historical records keep their department, position and type.
- **Seeded reference data must be verified.** Departments follow the standard municipal offices under RA 7160. Salary grades on positions follow the DBM Index of Occupational Services. Check both against the municipality's approved plantilla.

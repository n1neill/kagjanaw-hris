You are building **Kagjanaw HRIS**, a web-based, on-premise Human Resource Information System for the **Municipality of Cagdianao, Province of Dinagat Islands (Philippine LGU)**. It replaces paper-based HR processes with one system for employee records, biometric attendance, leave management, and payroll. It runs on the municipal office LAN and is used on desktop and mobile browsers.

Work in phases (below). After each phase, run the tests and migrations, fix failures, and commit before starting the next phase. Ask me only if a decision is truly blocking. Otherwise state your assumption in the README and proceed.

## 1. Tech stack (fixed)

- **Backend:** Laravel 11, PHP 8.2+. MVC plus a dedicated **Service Layer** (`app/Services`) for business logic such as payroll and leave balances. Use Form Requests for validation, Eloquent for DB access, queues for payroll jobs, and the task scheduler for biometric sync.
- **Frontend:** Blade templates, Alpine.js, Tailwind CSS (bundled with Vite), DataTables.js for data-heavy tables. Responsive and accessible.
- **Database:** MySQL 8.0 with foreign keys. Use migrations and seeders for everything, including reference data.
- **Auth:** Laravel Breeze (Blade stack). **Spatie Laravel Permission** for RBAC.
- **Biometrics:** ZKTeco device (K40 / ZK400, fingerprint and face, RJ-45) over TCP/IP using the **ZKLib PHP** library.
- **PDF:** barryvdh/laravel-dompdf (payslips, DTR, Certificate of Leave Credits).
- **Excel/CSV:** Maatwebsite/Laravel-Excel.
- **Files:** Laravel Storage facade, local disk.
- **Dev environment:** XAMPP on Windows, Composer, Node/NPM. Production: Apache or Nginx on a local server.
- **Testing:** PHPUnit/Pest.

## 2. Roles and permissions (Spatie)

| Role | Can do |
|---|---|
| **System Administrator** | Manage users and roles, system configuration, database backup/restore, biometric device configuration and sync monitoring |
| **HR Officer** | Employee records and 201 files, review and validate attendance, final approve/reject leave (after Dept Head endorsement), run payroll, generate and export reports, send announcements |
| **Department Head** | See only their department. Approve/reject/endorse leave with remarks, monitor attendance, view department reports |
| **Employee** | See only their own data. View profile and documents, view attendance (daily/weekly/monthly with late/undertime/absence), file leave and track status, view leave balances, view and download payslips, receive notifications and announcements |

Enforce access with policies and middleware, not only by hiding menu items. Employees must never be able to read another employee's data by changing an ID in the URL.

## 3. Functional modules

### 3.1 Employee records and 201 file (HR Officer)
- CRUD for employees: personal info, contact details, department, position, employment type, salary grade and step, date hired, status, government IDs (GSIS, PhilHealth, Pag-IBIG, TIN), biometric user ID.
- Upload and store documents (appointment papers, government IDs, certificates of eligibility, etc.). Validate type and size with Form Requests. Store under `employees/{employee_id}/`. Serve files only through authorized controller routes.
- Departments, positions, and employment types as managed reference data.

### 3.2 Biometric attendance
- A service (ZKLib) connects to the ZKTeco device by IP and port, pulls raw logs (employee/biometric ID, timestamp, verification type), and writes to an `attendance_logs` table.
- A scheduled command (Laravel scheduler, configurable interval, for example every 5 minutes) runs the sync. Detect and skip **duplicate** logs with a unique constraint on biometric ID plus timestamp, and flag invalid logs, for example unknown IDs.
- A processing service turns raw logs into **Daily Time Records (DTR)**: time in/out, late, undertime, absent, hours worked. Follow **Civil Service Commission (CSC)** rules for LGUs. Make work schedule, grace period, and holidays configurable.
- HR can review, validate, and manually correct records (with an audit trail). Reports: attendance summaries, DTR PDF.
- Admin screen: device settings, test connection, last sync, manual sync, sync error log.
- Provide a **fake/mock device driver** behind an interface so the whole flow can be developed and tested without hardware. Choose the driver by config.

### 3.3 Leave management
- Employees file leave (type, dates, reason) and see status: pending, endorsed, approved, rejected. They see real-time balances.
- **Workflow:** Employee files, Department Head endorses or rejects with remarks, HR Officer approves or rejects. Balances update automatically on final approval. Notify the employee at each step.
- Leave types and credits are seeded and configurable (Vacation, Sick, and others per CSC). Include accrual rules.
- Generate the **Certificate of Leave Credits** PDF (DomPDF) in CSC standard form layout. Report: leave utilization.

### 3.4 Payroll
- A `PayrollService` computes per period, semi-monthly or monthly:
  - Basic pay from **salary grade and step** (seed the **Salary Standardization Law, SSL V** schedule for LGUs, with a clear note that the values must be verified and are editable by HR).
  - Adjustments from attendance (absences, late, undertime) and approved leaves.
  - Mandatory deductions: **GSIS, PhilHealth, Pag-IBIG (HDMF), BIR withholding tax**. Keep rates and tables in versioned DB tables or config (not hard-coded) so they can be updated when regulations change. Include effectivity dates.
  - Gross pay, total deductions, net pay.
- Run payroll as a queued job. A payroll run has states (draft, computed, approved, released) and is locked once released. Only HR Officers can process it.
- Payslip PDFs (DomPDF) are available to employees for their own payslips. Payroll register is exported to Excel (.xlsx), CSV, and PDF.
- Use `decimal` columns and a money-safe approach. Never use floats for currency.

### 3.5 Notifications and announcements
- Database notifications (Laravel Notifications) for leave updates, payroll release, and announcements. Notification bell in the UI.
- HR posts announcements, shown on the dashboard.

### 3.6 Reports and exports
- Attendance reports, payroll summaries and register, leave utilization, and employee lists. Export to Excel, CSV, and PDF.

### 3.7 Administration
- User and role management (create users, assign roles, activate/deactivate).
- **Database backup and restore** (admin UI plus an Artisan command, scheduled).
- System configuration (office hours, grace period, holidays, sync interval, payroll periods).
- **Audit log** of user activity on sensitive data, and a log of failed or unauthorized access attempts.

### 3.8 Dashboards
- One dashboard per role, for example employee: today's attendance, leave balance, latest payslip. Dept Head: pending endorsements, team attendance. HR: pending leaves, payroll status, sync status. Admin: users, backups, device status.

## 4. Non-functional requirements

- **Security:** CSRF, validation, Eloquent parameterized queries, secure sessions, strong password rules, login throttling, encryption of sensitive fields at rest (Laravel `encrypted` casts for government IDs and similar), HTTPS-ready config.
- **Data Privacy Act of 2012 (RA 10173):** least-privilege access, audit logging of access to sensitive data, no sensitive data in logs or URLs.
- **Reliability:** DB transactions around payroll and leave balance changes, backups, validated inputs, idempotent biometric sync.
- **Performance:** eager loading, indexes on foreign keys and on attendance (employee, date), pagination, queues for heavy jobs.
- **Usability:** responsive UI (desktop and mobile), clear navigation, consistent Tailwind design system.
- **Maintainability:** modular structure, thin controllers, services for business logic, seeders, documented code, and a README with setup (XAMPP/Windows), `.env` keys, scheduler cron/Task Scheduler setup, and the biometric device config.
- **Architecture:** three-tier (presentation, application, data). On-premise on a LAN, no cloud dependencies.

## 5. Suggested data model (refine as needed)

`users`, `employees`, `departments`, `positions`, `employment_types`, `salary_grades` (grade, step, amount, effectivity), `employee_documents`, `attendance_logs` (raw), `daily_time_records` (processed), `work_schedules`, `holidays`, `leave_types`, `leave_balances`, `leave_requests` (with `endorsed_by`, `approved_by`, remarks), `payroll_periods`, `payroll_runs`, `payroll_items` (per employee: earnings and deductions breakdown), `deduction_rates` (GSIS/PhilHealth/Pag-IBIG/tax tables), `announcements`, `notifications`, `audit_logs`, `biometric_devices`, `settings`.

## 6. Delivery phases (follow the Waterfall order from the spec)

1. **Foundation:** Laravel 11 project, Breeze, Spatie roles/permissions and seeders, layout, Tailwind/Alpine/DataTables, and a dashboard shell per role. Departments, positions, and users.
2. **Employee records and 201 files.**
3. **Biometric attendance:** device interface plus ZKLib and mock drivers, scheduled sync, duplicate detection, DTR processing, HR review, and reports.
4. **Leave management:** workflow, balances, notifications, Certificate of Leave Credits.
5. **Payroll:** salary grade seed, deduction tables, `PayrollService`, queued runs, payslips, exports.
6. **Notifications, announcements, reports, and admin tools:** audit log, backup/restore, settings.
7. **Hardening:** authorization tests for every role boundary, security review, performance pass, and a README plus user guide.

## 7. Testing (required)

- **Unit tests:** payroll computation (including edge cases such as unpaid leave and mid-period hires), DTR late/undertime/absence logic, and leave balance calculations.
- **Integration tests:** biometric log, then DTR, then payroll. Leave approval, then balance, then payroll.
- **Feature tests:** RBAC. Each role can access only what it should, including IDOR attempts.
- Seed realistic demo data (one user per role, sample employees, sample logs) so the system can be demoed immediately.

## 8. Ground rules

- Do not hard-code tax, contribution, or salary values. Make them data-driven and mark seeded values clearly as "verify against current official tables".
- Keep controllers thin and put logic in services. Use Form Requests for all input.
- Never commit secrets. Provide `.env.example`.
- Commit after each phase with a clear message.
- At the end, give me a summary: what is built, how to run it on XAMPP, default demo logins (documented in the README, development only), and any assumptions or open questions.

Start with Phase 1.
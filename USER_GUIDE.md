# Kagjanaw HRIS — User Guide

Kagjanaw HRIS is the Human Resource Information System of the Municipality of Cagdianao. It keeps employee records (201 files), attendance from the time clock, leave applications and leave cards, and payroll in one place. Open it in any browser on the municipal network, on a computer or a phone.

This guide is organized by role. Read **Getting started** first, then the section for your role. Each account has exactly one role:

| Role | Who | Section |
| --- | --- | --- |
| Employee | Every municipal employee | [Employees](#employees) |
| Department Head | Heads of offices | [Department heads and the Municipal Mayor](#department-heads-and-the-municipal-mayor) |
| Municipal Mayor | The Mayor | [Department heads and the Municipal Mayor](#department-heads-and-the-municipal-mayor) |
| HR Officer | MHRMO staff | [HR officers](#hr-officers) |
| System Administrator | The IT administrator | [System administrators](#system-administrators) |

Department heads, the Mayor and HR officers are employees too: everything in the **Employees** section applies to them as well.

---

## Getting started

### Signing in

1. Open the address given by the HR office (for example `http://hris.cagdianao.local`).
2. Enter your **Employee ID** (for example `2021-0230`) and your password, then click **Log in**. The System Administrator signs in with a username instead.
3. After 5 wrong passwords, sign-in is blocked for a minute.

**First sign-in.** When HR adds you, you receive a temporary password in person. The first time you sign in, the system asks you to **set your own password** before anything else. The same happens after the administrator resets your password.

Passwords must have at least 12 characters, with upper- and lowercase letters, a number and a symbol (for example `Cagdianao#2026-Ana`). Never share your password; everything done under your account is recorded under your name.

**Forgot your password?** Ask the System Administrator to reset it. (**Forgot your password?** on the sign-in page only works if your account has an email address and the server can send mail.)

### Finding your way

- The **menu** is on the left (tap the ☰ button on a phone). It shows only what your role may use.
- The **bell** at the top shows your notifications: leave decisions, released payslips and announcements. Click one to open it, or **View all notifications** for the full list.
- **My Account** (bottom of the menu) changes your email address and password.
- **Log Out** when you leave a shared computer. You are also signed out after two hours without activity.

Changing your password signs your account out on every other computer and phone.

### Tables

Most lists have a **Search** box, sortable column headings and pages of 25 rows. Reports and registers can be downloaded as **Excel**, **CSV** or **PDF**.

---

## Employees

### Dashboard

The **Dashboard** shows today's attendance, your leave balances, your latest payslip and the newest announcements.

### My Record

**My Record** shows your 201 file: personal information, employment details, government ID numbers and your documents (appointment papers, IDs, certificates).

- Government ID numbers are hidden until you click **Show**.
- To correct anything, ask the HR office. Employees cannot edit their own record.

### My Attendance

**My Attendance** shows your **Daily Time Record (DTR)** for a month, built from the time clock:

- Morning and afternoon **in** and **out**, with minutes **late** and **undertime**, and absences.
- Arriving within the **15-minute grace period** (until 8:15 / 1:15) is on time. Later than that, lateness counts from 8:00 / 1:00.
- **Missing punch** means a session has only one punch. It counts as a half-day absence until HR corrects it.
- **On leave** marks days covered by approved leave.
- Click **Print DTR (Form 48)** for the printable CSC Form 48.

Think a time is wrong (for example, the time clock was down)? Ask the HR office to correct it. Every correction is recorded with the reason.

### My Leave

**My Leave** shows your **balances**, your **leave card** (every credit and deduction, month by month) and your applications.

**Applying for leave**

1. Click **Apply for Leave**.
2. Choose the **Type of leave**. The form shows that type's rules (for example, vacation leave must be filed 5 days ahead; sick leave can be filed after you return).
3. Enter the **first day** and, for more than one day, the **last day**. Rest days and holidays are not counted. A half day (morning or afternoon) is allowed on a single date.
4. Add the **details** (where you will spend the leave, the illness, and so on).
5. Attach a **supporting document** when required, for example a medical certificate for sick leave of more than 5 days (PDF, JPG or PNG, up to 5 MB).
6. Click **File Application**.

**What happens next**

1. Your **department head** endorses or rejects it. (A department head's own leave goes to the **Municipal Mayor**; the Mayor's goes straight to HR.)
2. **HR** approves or rejects it.
3. You get a notification at each step, with the remarks.

Credits are deducted only when HR approves. If your credits are not enough, the extra days are **without pay**.

**Withdrawing.** While an application is still waiting, open it and click **Withdraw application**. Approved leave can only be cancelled by HR (for example, if you are recalled to work).

**How credits are earned.** At the end of each month, once HR validates your DTR, you earn 1.25 days of vacation and 1.25 days of sick leave. Tardiness and undertime are deducted from vacation leave. Elective officials and Job Order / Contract of Service workers do not earn credits.

### My Payslips

**My Payslips** lists your payslips once HR **releases** each payroll (twice a month). Click one to open the PDF: earnings, deductions (GSIS, PhilHealth, Pag-IBIG, withholding tax, loans) and net pay. Questions about an amount go to the HR office.

### Announcements

**Announcements** lists notices from the HR office addressed to everyone or to your office. Pinned notices stay at the top.

---

## Department heads and the Municipal Mayor

You see your **own office** only, read-only. You do not see salaries, government IDs, addresses or 201 documents of your staff.

### Leave Endorsements

**Leave Endorsements** lists the applications waiting for you:

- Department heads: applications from employees in their office.
- The Mayor: applications from department heads (and from offices with no department head in the system).

Open an application to see the dates, the employee's balances and any attachment, then:

- **Endorse to HR**, with optional remarks, or
- **Reject**, giving the reason in the remarks.

The employee is notified either way, and HR is notified of endorsed applications. You cannot endorse your own leave.

### My Department and Department Attendance

- **My Department** lists the employees of your office with their basic profile (position, employment type, status, date hired, mobile number).
- **Department Attendance** shows the month's attendance summary of your office. Click **DTR** to open an employee's Daily Time Record.

### Reports

**Reports** gives your office's employee list, attendance summary and leave utilization, each downloadable as Excel, CSV or PDF.

---

## HR officers

HR officers manage employee records, attendance, leave and payroll for every office. The menu groups these under **Human Resources**, **Leave**, **Payroll** and **Work Calendar**.

### Employee records (201 files)

**Employees** lists everyone in service; filter by department or status.

**Adding an employee**

1. Click **New Employee** and fill in the form. The **Employee ID** becomes the employee's username. The **Biometric user ID** is the number enrolled on the time clock.
2. Click **Add Employee and Create Account**.
3. The record shows a **temporary password** once. Hand it to the employee in person; they will set their own at first sign-in.

**Updating a record.** Open the employee and click **Edit Record**. To record a resignation, retirement or other separation, change the **Employment status** and enter the **Separation date**: the account is deactivated, and the record and its history are kept. Employees are never deleted.

**201 documents.** On the employee's record, choose the **Document type**, pick the file (PDF, JPG or PNG, up to 10 MB) and click **Upload**. Documents are stored privately and opened only through the system.

To make an employee a department head or HR officer, ask the System Administrator to change the role of their account.

### Attendance

**Daily routine**

- The time clock is synchronized every few minutes. Each night the system finalizes the previous day's DTRs, including absences.
- **Attendance Logs** shows the raw punches. **Flagged** punches come from an unknown biometric ID (enter it on the right employee's record and they are attached automatically) or have a wrong device clock.
- **Import Attendance** loads punches from a CSV file (for example, a paper logbook while the clock was down). **Download template** gives the format.

**Correcting a DTR**

1. Open **Attendance Summary**, choose the month and click **DTR** for the employee.
2. Click **Correct** on the day, enter the right times and the **Reason for the correction**, and click **Save Correction**.

Every correction is kept in the day's correction history (who, when, before and after, why). Reprocessing never overwrites a corrected day.

**Validating a month.** When a month is complete and has no missing punches, click **Validate (month)** on the employee's DTR. Validation:
- posts the month's leave credits and tardiness deductions to the leave card, and
- makes the month's absences and leave without pay available to the next payroll.

Correcting a validated day requires validating the month again; only the difference is posted. **Recompute from logs** rebuilds the uncorrected days of the month, for example after changing office hours.

### Work calendar

- **Office Hours**: the daily schedule and the grace period.
- **Holidays**: add Eid'l Fitr, Eid'l Adha and local holidays each year, and check the seeded national holidays against the presidential proclamation. Changing a past holiday recomputes that day's DTRs.

### Leave

- **Leave Applications** lists applications; by default those **endorsed** and waiting for you. Open one and click **Approve** or **Reject** (with the reason). Approval charges the credits, splits the days into with and without pay, and marks the days **On leave** on the DTR. You cannot decide your own application.
- **Cancel approved leave** (on an approved application, with the reason) restores the credits and returns the days to the DTR.
- **Leave Credits** lists every employee's balances. Click **Leave card** to see the ledger, **Post Adjustment** to correct a balance (with the reason), or **Generate PDF** for the **Certificate of Leave Credits** as of any date.
- **Import Opening Balances** loads each employee's balances from their paper leave card when the system starts (CSV; **Download template** gives the format).
- **Monthly Posting** shows who is posted, ready or still waiting for a validated DTR. **Post for all ready employees** posts the month.
- **Leave Utilization** reports leave taken by type and office.
- **Leave Types** holds the rules of each type (days per year, notice required, attachments). Verify them against current CSC rules.

### Payroll

Pay is **semi-monthly**: the 1st–15th and the 16th–end of the month. Each half has a **regular payroll** (employees paid by salary grade) and a **Job Order / COS payroll** (employees paid by the day).

**Before the first payroll**

1. **Payroll Settings**: set the municipality's **income class** and tick **Confirmed** (until then payroll uses the 5th-class rate, 70%).
2. **Salary Schedule**, **Contributions & Tax**, and **Allowances**: check the seeded values against the current official tables. Each table has an effectivity date; add a new version ahead of time when rates change.
3. On each employee's **Pay Setup** (the button on their record), add allowances other than PERA, and loans or other deductions (GSIS and Pag-IBIG loans, cooperative, dues). Enter Job Order workers' daily rates on their records.

**Running a payroll**

1. **Payroll Runs → New payroll**: choose the month, half and payroll, then **Create and compute**. The page shows "Computing…" until the queue worker finishes.
2. Review the employees and the **Needs attention** list. **Must fix** items (for example, an employee without a salary grade, or a Job Order DTR not yet validated) block approval; fix the records and click **Recompute**.
3. **Approve**. Until it is released, an approved payroll can still be reopened with **Reopen for corrections**.
4. **Release payslips**. The payroll is locked for good, and every employee is notified and sees the payslip under **My Payslips**. Corrections after release go in a later payroll.

Download the **payroll register** (Excel, CSV or PDF with signature lines) from the payroll page.

### Announcements

**Announcements → New Announcement**: write the title and message (plain text), choose **Addressed to** (everyone, or selected offices), optionally **Pin to the top** and **Show until**, then **Publish** (notifies everyone addressed) or **Save as Draft**.

### Reports

**Reports** gives the employee list, attendance summary, leave utilization and payroll summary (released payrolls) for any office, as Excel, CSV or PDF. Exports are recorded in the audit log.

### Reference data

**Departments**, **Positions** and **Employment Types**. Entries are deactivated rather than deleted, so past records keep them. On an employment type, set whether it **earns leave credits** and whether it is **paid by salary grade or by the day**.

---

## System administrators

The administrator manages accounts, the time clocks, backups and system settings. The administrator does not see employee records, attendance, leave or payroll.

### User Accounts

- **New Account** creates a system account (for example, another administrator). Employee accounts are created automatically when HR adds an employee.
- **Edit** changes an account's **Role** or resets its password (**Reset password**): the user must choose a new password at next sign-in, and is signed out everywhere else.
- **Deactivate** blocks sign-in immediately; **Activate** restores it.

You cannot change your own role or deactivate yourself.

### Biometric Devices

Add each time clock with its IP address and port (4370). **Test Connection** checks it; **Sync Now** pulls punches immediately; the **Sync log** lists every attempt and its errors. Until a ZKTeco driver is installed, use the **mock** driver for demonstrations.

### Backups

- **Back Up Now** makes a backup (database and uploaded files) at any time. The daily backup runs at the time set in System Settings.
- **Download** backups regularly and **copy them off the server** (USB drive or another computer), together with the `.env` file. Without its `APP_KEY`, encrypted government IDs cannot be read.
- **Restore** asks for your password, makes a safety backup first, then replaces all data with the backup. Warn users first, and do not restore while a payroll is computing.
- **Upload a backup** brings in a backup from another computer so it can be restored.

### Audit Log

**Audit Log** records who changed what (with the values before and after), who opened other people's personal data or exported reports, sign-ins and failed sign-ins, and refused requests. Filter by category, user, date or text, and open an entry for its details. Government ID numbers and passwords are never written to the log. Check failed sign-ins and refused requests regularly.

### System Settings

The biometric sync interval, the daily backup time and how many backups to keep, and how long audit log entries are kept.

---

## Questions and problems

| Problem | What to do |
| --- | --- |
| I forgot my password. | Ask the System Administrator to reset it. |
| "Your account has been deactivated." | Ask the HR office or the System Administrator. |
| My DTR shows an absence or missing punch, but I was at work. | Ask the HR office to correct the day; bring proof if you have it. |
| My leave balance looks wrong. | Open **My Leave → leave card** to see every entry, then ask the HR office. |
| I cannot find my payslip. | Payslips appear only after HR releases the payroll. |
| "404 Not Found" | The page does not exist or is not available to your role. |
| "403 Forbidden" | Your role may not use this page. Ask the System Administrator if you need access. |
| A payroll stays at "Computing…". | The queue worker is not running on the server; tell the System Administrator. |

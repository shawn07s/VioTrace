# VioTrace: DMP Traffic Management System
DBMS Lab project (CSE0612216), UITS. Stack: MySQL/MariaDB (XAMPP), PHP (PDO), HTML, CSS, JavaScript.

## 1. Run it (XAMPP, Windows)
1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy the `viotrace` folder to `C:\xampp\htdocs\` (so `C:\xampp\htdocs\viotrace\index.php` exists).
3. Open **http://localhost/phpmyadmin** > **Import** > choose `sql/viotrace.sql` > **Import**.
   This DROPS and recreates the `viotrace` database with the new structure and demo data.
4. Open **http://localhost/viotrace/**.

Edit `includes/config.php` if your MySQL root has a password (`DB_PASS`) or the folder name differs (`BASE_URL`).
Optional: `SET GLOBAL event_scheduler = ON;` turns on the daily overdue job (dashboards also flag overdue fines on every load).

## 2. Demo accounts
| Role | Username | Password | PIN |
|---|---|---|---|
| Main admin | `adminDC` | `dc1234` | `123456` |
| Staff admins | `admin1` ... `admin5` | `admin1` ... `admin5` (same as the username) | `000001` |
| Officers | `DMP04325200001` ... `DMP04325200010` | `officer1` ... `officer10` (officer 1 to 10) | none |
| Citizens | `01711000001` (Rahim), `01711000003` (Nusrat) | `citizen123` | none |

These are demo credentials. Change them before using the system for anything real.

## 3. What changed in this version
**Admins (PIN and approval)**
- Admin sign-in is two steps: ID + password, then a 6-digit PIN (5 wrong tries ends the attempt; the step expires after 5 minutes).
- Any admin can create a new staff admin ID (`Admins` page). IDs continue the sequence (`admin6`, `admin7`, ...).
- A new admin ID created by staff is **Pending** and cannot sign in. Only `adminDC` can approve or reject it. The database enforces this with triggers, so it cannot be bypassed by editing the web page.
- An ID created by `adminDC` itself is active immediately.

**Officers**
- Officer ID format `DMP04325200001`, then `...002`, `...003`, and so on (10 are seeded). The username is the officer ID.
- Only an administrator can create officer IDs (`Manage data`). The next ID is generated automatically. A database trigger rejects any officer username in the wrong format.

**Case IDs and homepage lookup**
- Every violation gets a unique Case ID (`VT-` plus 8 characters) from a database trigger.
- Anyone can type a Case ID on the homepage to see the offence, fine, due date and payment status. The vehicle number is masked and no driver name is shown.
- To pay, a driver must sign in. A guest who clicks "Sign in to pay" returns to the payment page after signing in.
- Demo Case IDs: `VT-5E1A0001` to `VT-5E1A0007`.

**Payments need admin approval**
- A driver's payment is saved as **Pending** and the fine shows "Payment pending". Admins approve or reject on the `Payments` page.
- Approve: fine becomes **Paid**. Reject: fine is payable again. The database refuses to mark a fine Paid without an approved payment.
- Seed data: case `VT-5E1A0003` is paid and approved; case `VT-5E1A0006` is waiting for approval.

**Citizen registration**
- Needs a driving licence number (`DL` + 8 digits) **or** a Bangladesh vehicle number (`DHAKA METRO-HA-11-2345`). At least one must be filled; if both are given they must belong to the same person.
- The username is the mobile number (`01XXXXXXXXX`), one account per number.
- The person must be 18 or older (date of birth must match the record), and an NID of exactly 10 or 17 digits is required and must be unique.

**Look and feel**: new navy and blue palette, cleaner tables and status labels, and the logo on every header and the home page.
The logo is your VioTrace DMP Traffic Management System badge (`assets/img/logo.png`, cut to a circle). Replace that file to change it.

## 4. Demo script for your viva
1. **Case lookup.** On the homepage search `VT-5E1A0001` as a guest. Click "Sign in to pay", sign in as `01711000001`, and you land on the payment page.
2. **Pending payment.** Pay it. The fine now shows "Payment pending".
3. **Admin approval.** Sign in as `adminDC` (PIN `123456`), open **Payments**, approve it. The case becomes Paid.
4. **Admin hierarchy.** Sign in as `admin1` (PIN `000001`), open **Admins**, create a new admin. Try signing in as it: refused (pending). In `adminDC`, approve it, then it can sign in. As `admin1`, you will not see Approve buttons.
5. **Officer IDs.** As any admin, **Manage data**, add an officer: the ID `DMP04325200011` is generated. Try inserting a wrong ID in phpMyAdmin: the trigger rejects it.
6. **Rollback and locking.** Use the *Lab demo options* on the pay page (fail after the update, or hold the row lock for 15 seconds from one browser while paying from another).
7. **Immutability.** In phpMyAdmin run `DELETE FROM audit_log;` or `UPDATE payments SET amount = 1;`. Both are rejected by triggers.
8. **Audit.** Admin > Audit trail shows every step, including admin approvals, with who did it.

## 5. Folder layout
```
viotrace/
  index.php (home + Case ID lookup)  login.php  verify_pin.php  register.php  logout.php
  dashboard.php  search.php  history.php
  includes/  config.php functions.php header.php footer.php
  officer/   dashboard.php add_violation.php
  citizen/   dashboard.php pay.php appeal.php
  admin/     dashboard.php payments.php appeals.php admins.php audit.php manage.php
  assets/    css/style.css  js/app.js  img/logo.png
  sql/       viotrace.sql
```
Other notes: payment is simulated (no real gateway); evidence is stored as references; violations are immutable and corrected through appeals.

## 6. Troubleshooting
| Symptom | Fix |
|---|---|
| Import fails with a syntax error near `DELIMITER` | Use phpMyAdmin's **Import** tab (not the SQL box), or run `mysql -u root < sql/viotrace.sql`. |
| Import fails on `CHECK` or `GENERATED` | Your MariaDB/MySQL is too old. Update XAMPP (needs MariaDB 10.4+ or MySQL 8.0.16+). |
| "Access denied for user root" or "Unknown database" | Fix `DB_PASS` in `includes/config.php`, and make sure the import finished. |
| Page not found, or the CSS and logo are missing | The folder must be `htdocs/viotrace`. If you renamed it, change `BASE_URL` in `includes/config.php`. |
| Blank white page | Turn on `display_errors` in `php.ini`, or read `C:\xampp\apache\logs\error.log`, and send me the message. |
| A change to the SQL file has no effect | The database only changes when you re-import. Re-import `sql/viotrace.sql`. |

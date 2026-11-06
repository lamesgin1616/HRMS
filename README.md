# MAU HRMS

MAU HRMS is a human resource management system built for a university environment. The project combines a React frontend, a PHP JSON API, and a MySQL database to support core HR tasks such as employee management, leave requests, attendance tracking, applications, payroll, and evaluations.

## Overview

This application is designed as a starter HR platform for academic or administrative use and includes:

- Employee and department records
- Vacancy and application management
- Leave approval workflow
- Attendance and payroll tracking
- Evaluation and notification features
- Admin and employee authentication flows

## Tech stack

- Frontend: React + Vite
- Backend: PHP with PDO and JSON responses
- Database: MySQL
- Local web server: XAMPP / PHP built-in server

## Project structure

```text
MAU-HRMS/
├── backend/
│   ├── api.php
│   ├── config.php
│   └── create_admin.php
├── frontend/
│   ├── src/
│   ├── index.html
│   ├── package.json
│   └── vite.config.js
├── sql/
│   ├── mau_hrms.sql
│   └── employee_admin_workflow_migration.sql
├── README.md
├── start-dev.bat
└── ...
```

## Requirements

- PHP 8.1+
- MySQL 8+
- Node.js 20+
- npm
- XAMPP or equivalent local Apache + MySQL environment

## Local setup

### 1. Start your database services

Launch Apache and MySQL through XAMPP.

### 2. Import the database

Open phpMyAdmin at:

```text
http://localhost/phpmyadmin
```

Then import:

```text
sql/mau_hrms.sql
```

This creates the `mau_hrms` database and the initial HR-related tables.

### 3. Configure the backend database connection

Edit `backend/config.php` and update your local database settings if needed:

```php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'mau_hrms';
const DB_USER = 'root';
const DB_PASS = '';
```

For an existing database, import `sql/email_notifications_migration.sql` and then `sql/notification_details_delivery_migration.sql` once in phpMyAdmin, with `mau_hrms` selected. The first migration adds email addresses to accounts; the second adds account phone numbers and structured notification details. New databases already include these fields in `sql/mau_hrms.sql`.

Email and SMS delivery are optional. Set `MAIL_FROM_EMAIL` to a valid sender address. With `MAIL_SMTP_HOST` blank, the API uses PHP's `mail()` transport; on XAMPP/Windows, configure `sendmail_path` and SMTP in `php.ini`/`sendmail.ini`. To use PHPMailer over SMTP, run `composer require phpmailer/phpmailer` from `backend`, then configure `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT`, `MAIL_SMTP_USERNAME`, `MAIL_SMTP_PASSWORD`, and `MAIL_SMTP_ENCRYPTION` (`tls`, `ssl`, or empty) in `backend/config.php`. The API loads `backend/vendor/autoload.php` when present. Keep real SMTP credentials out of source control and use environment-specific secrets in deployment. For SMS, set `SMS_GATEWAY_URL`, `SMS_GATEWAY_TOKEN`, and optionally `SMS_SENDER_ID`. The configured gateway must accept a POST with a bearer token and JSON payload shaped like `{"from":"MAU HRMS","to":"+251...","message":"..."}`; enable PHP cURL. User-account email/phone fields are preferred, with linked employee contact fields used as fallbacks. Notifications are stored first; delivery failures are logged and do not undo an accepted request. Request-submission feedback reports success if at least one configured delivery channel succeeded.

The notification dispatcher accepts explicit user IDs and/or active account roles, persists a structured event and its timestamp, then attempts configured email and SMS delivery. The same dispatcher is used for leave/profile/role requests and decisions, account and employee changes, attendance, payroll, evaluations, vacancy changes, applications, and administrative directory changes. Add new business events by calling `notify_event()` or the `notify_user()`, `notify_roles()`, `notify_employee()`, and `notify_system_alert()` wrappers after the relevant database transaction commits. The authenticated notification endpoints return title, message, event type, timestamp, and decoded details for the detail modal. Sensitive payroll details are not copied into delivery messages; employees are directed to sign in to view their payroll record.

### 4. Create the first admin account

From the project root, run:

```bash
php backend/create_admin.php admin "Replace-With-A-Long-Unique-Password"
```

Use any password of at least 7 characters (maximum 72 bytes). This account is used to create employees and manage system setup.

CLI-created administrators are standalone user records and do not require an employee row. Employee accounts are linked to their employee record through `users.emp_id`. Both account types authenticate through `POST /backend/auth/login`, which verifies the stored password hash and uses the role saved on the account. There is no separate `login.php`; the login endpoint is routed by `backend/api.php`.

### Recover access to an existing account

If login returns **Invalid username or password**, check that the username exists and that the account is active. Five incorrect password attempts lock the account for 15 minutes. To set a new password for an existing account and clear that lockout, run this from the project root:

```bash
php backend/reset_password.php <username> "Replace-With-A-Long-Unique-Password"
```

The script is CLI-only, accepts any password of at least 7 characters (maximum 72 bytes), and does not activate inactive accounts. If you need a new administrator account instead, use the `create_admin.php` command above. SQL-imported account password hashes do not provide a recoverable plaintext password.

Public self-registration lets applicants request Administrator, HR Officer, Department Head, Employee, or Management. Every new account is created with the Employee role; requests for other roles remain pending until an Administrator approves them from the **Role requests** menu. Administrators can also assign roles when adding an employee from the **Employees** menu.

Employee registration uses cascading College and Department dropdowns. Departments are fetched only after a college is selected, and the employee record stores both IDs. New databases get the college, department, role request, and profile request tables and seed data from `sql/mau_hrms.sql`. The administrator CRUD and employee profile-request workflow requires `sql/employee_admin_workflow_migration.sql` on an existing database; run it once in phpMyAdmin with `mau_hrms` selected.

## Run the application

### Option A: Use the launcher

From the project root:

```bat
start-dev.bat
```

This starts:

- PHP API at `http://127.0.0.1:8000`
- Vite frontend at `http://127.0.0.1:5173`

### Option B: Run manually

Start the API:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t C:\xampp\htdocs\HRMS-main\backend
```

Then start the frontend:

```powershell
cd C:\xamppb\htdocs\MAU-HRMS\frontend
npm install
npm run dev
```

Open the app here:

```text
http://127.0.0.1:5173
```

## Important notes

- The frontend is served by Vite on port 5173.
- The PHP API is served on port 8000.
- The frontend is configured to proxy requests through the same origin to the PHP backend during local development.
- This project is intended as a functional starter system and should not be treated as a production-ready payroll or HR platform without further security and policy review.

## Main API areas

The backend exposes endpoints for:

- `GET /colleges` and `GET /departments?college_id={id}` for registration dropdowns
- Authentication: login, logout, current user
- Dashboard
- Administrator CRUD for employees, colleges, departments, and user accounts
- Employee profile requests and administrator/HR approval
- Role requests and notifications
- Employees and departments: `GET/POST /employees`, `PUT/DELETE /employees/{id}`
- Colleges: `GET/POST /colleges`, `PUT/DELETE /colleges/{id}` (Administrator only)
- Departments: `GET/POST /departments`, `PUT/DELETE /departments/{id}`
- User accounts: `GET/POST /users`, `PATCH/DELETE /users/{id}` (Administrator only)
- Profile requests: employee `POST /profile-requests`, approver `GET /profile-requests` and `PATCH /profile-requests/{id}`
- Role requests: Administrator `GET /role-requests` and `PATCH /role-requests/{id}`
- Notification center: `GET /notifications`, `GET /notifications/unread-count`, `PATCH /notifications/{id}`, and `PATCH /notifications/read-all`. The authenticated header bell refreshes its unread badge every 30 seconds and supports marking one or all notifications as read.
- Vacancies and applications
- Leave management
- Attendance management
- Payroll
- Evaluations
- Notifications
- Reports

### Troubleshooting API 500 responses

The API sets `Content-Type: application/json; charset=utf-8` and logs database/runtime exceptions with the request method and path. When `/backend/users` or `/backend/notifications` returns HTTP 500:

1. Check the PHP/Apache error log first; do not enable `display_errors` on a public server. Confirm PHP is 8.1 or later and that PDO MySQL is enabled.
2. Verify `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in `backend/config.php`, and make sure MySQL is running and the configured database is `mau_hrms`.
3. In phpMyAdmin, select `mau_hrms` and inspect the deployed schema:

   ```sql
   SHOW COLUMNS FROM users;
   SHOW COLUMNS FROM users;
   SHOW COLUMNS FROM notifications;
   SHOW CREATE TABLE notifications;
   ```

   The current API expects the full `users` structure defined in `sql/mau_hrms.sql`, including `user_id`, `username`, `password_hash`, `role`, `status`, `email`, `phone`, `emp_id`, `failed_attempts`, `locked_until`, and `created_at`. It also expects notification title/type/message/read/date/detail columns. The API logs actual SQL errors server-side and returns specific JSON errors for a missing or outdated `users` table.
4. If the deployed database is older, apply `sql/email_notifications_migration.sql` first and `sql/notification_details_delivery_migration.sql` second, once each. The notification migration creates the base table if it is missing, then adds the structured-detail columns. The API also creates a completely missing notification table on demand; the MySQL account must have `CREATE` permission and the existing `users` table must be present. An existing but outdated table still requires the migration. If MySQL reports a duplicate column, inspect the columns before proceeding; do not rerun an already-applied `ALTER TABLE` blindly. For a fresh install, import `sql/mau_hrms.sql`.
5. Test while signed in as an active user. `/backend/users` requires the Administrator role; `/backend/notifications` requires an authenticated session. A 401/403 is an authentication/permission issue rather than a database 500.

The API returns generic JSON errors to clients while the detailed exception stays in the server log. Ensure PHP's `display_errors=Off` and `log_errors=On` so warnings do not corrupt JSON responses.

To repair missing safe-to-add account columns in an existing MariaDB/XAMPP database, back up `mau_hrms`, select it in phpMyAdmin, then import `sql/users_account_schema_migration.sql`. This migration creates the complete table only if `users` is absent (and therefore expects `employees` to exist); otherwise it adds the optional contact, employee-link, login-lockout, and creation-time columns idempotently. The API performs the same safe additions on `/users` requests when the request has an existing session and its database account has `ALTER` permission. It deliberately does not invent missing usernames, password hashes, role, status, or user IDs: if those core fields are absent, apply the schema to a backup and restore/migrate the existing account data before retrying.

## Security and deployment warnings

Before using real staff data in production, review and strengthen the following:

- Password policies and account management
- Authentication/session security
- CSRF protection
- Secure database credentials
- HTTPS deployment
- Validation and sanitization of user input
- Backup and recovery strategy
- Access control for HR and department roles

## License

This project is created for local development and academic/demo use. Update licensing before production or public distribution.

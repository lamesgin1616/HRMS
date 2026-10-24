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
│   └── hrms.sql
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
sql/hrms.sql
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

### 4. Create the first admin account

From the project root, run:

```bash
php backend/create_admin.php admin "Replace-With-A-Long-Unique-Password"
```

Use a strong password with at least 12 characters. This account is used to create employees and manage system setup.

CLI-created administrators are standalone user records and do not require an employee row. Employee accounts are linked to their employee record through `users.emp_id`. Both account types authenticate through `POST /backend/auth/login`, which verifies the stored password hash and uses the role saved on the account. There is no separate `login.php`; the login endpoint is routed by `backend/api.php`.

Public self-registration lets applicants request Administrator, HR Officer, Department Head, Employee, or Management. Every new account is created with the Employee role; requests for other roles remain pending until an Administrator approves them from the **Role requests** menu. Administrators can also assign roles when adding an employee from the **Employees** menu.

Employee registration uses cascading College and Department dropdowns. Departments are fetched only after a college is selected, and the employee record stores both IDs. New databases get the college and department tables and seed data from `sql/hrms.sql`. To upgrade an existing database, run `sql/college_department_migration.sql` once in phpMyAdmin. To reapply only the department seed after that migration, run `sql/seed_mau_departments.sql`; it uses `INSERT IGNORE` and is safe to run repeatedly.

For an existing database, apply `sql/role_requests_migration.sql` once in phpMyAdmin after selecting the `mau_hrms` database. New installations get the role request table when importing `sql/hrms.sql`.

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
C:\xamppb\php\php.exe -S 127.0.0.1:8000 -t C:\xamppb\htdocs\MAU-HRMS
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
- Employees and departments
- Vacancies and applications
- Leave management
- Attendance management
- Payroll
- Evaluations
- Notifications
- Reports

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

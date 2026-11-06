<?php
declare(strict_types=1);
const DB_HOST = '127.0.0.1';
const DB_NAME = 'mau_hrms';
const DB_USER = 'root';
const DB_PASS = ''; // Change this for your environment.
const MAIL_FROM_EMAIL = ''; // Set to an address accepted by the configured PHP mail transport.
const MAIL_SMTP_HOST = ''; // Optional SMTP host; configure to send with PHPMailer.
const MAIL_SMTP_PORT = 587;
const MAIL_SMTP_USERNAME = '';
const MAIL_SMTP_PASSWORD = '';
const MAIL_SMTP_ENCRYPTION = 'tls'; // 'tls', 'ssl', or ''.
const SMS_GATEWAY_URL = ''; // Optional JSON API endpoint accepting from, to, and message.
const SMS_GATEWAY_TOKEN = ''; // Optional bearer token for the SMS gateway.
const SMS_SENDER_ID = 'MAU HRMS';
const APP_ORIGINS = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:5174',
    'http://127.0.0.1:5174',
];

function username_policy_error(string $username): ?string {
    $username = trim($username);
    if ($username === '') return 'usernameRequired';
    $length = preg_match_all('/./u', $username);
    if ($length === false) return 'usernameTooLong';
    if ($length < 4) return 'usernameTooShort';
    if ($length > 80) return 'usernameTooLong';
    return null;
}

function password_policy_error(string $password): ?string {
    $length = preg_match_all('/./us', $password);
    if ($length === false || $length < 7) return 'passwordTooShort';
    if (strlen($password) > 72) return 'passwordTooLong';
    return null;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

<?php
declare(strict_types=1);
const DB_HOST = '127.0.0.1';
const DB_NAME = 'mau_hrms';
const DB_USER = 'root';
const DB_PASS = ''; // Change this for your environment.
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
    if (strlen($password) < 6) return 'passwordTooShort';
    if (
        !preg_match('/[A-Z]/', $password)
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/[0-9]/', $password)
        || !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        return 'weakPassword';
    }
    if (strlen($password) > 72) return 'passwordTooLong';
    if (in_array(strtolower($password), ['password123!', 'welcome123!', 'qwerty123!', 'admin123!', 'letmein123!', 'changeme123!', 'iloveyou123!'], true)) {
        return 'commonPassword';
    }
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

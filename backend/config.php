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

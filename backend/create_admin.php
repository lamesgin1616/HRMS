<?php
declare(strict_types=1);

// Run once from the command line after importing sql/hrms.sql and configuring config.php.
require_once __DIR__.'/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line only.');
}

$username = trim($argv[1] ?? 'admin');
$password = $argv[2] ?? '';

if (username_policy_error($username) !== null || password_policy_error($password) !== null) {
    fwrite(STDERR, "Usage: php create_admin.php <username> <strong-password>\n");
    fwrite(STDERR, "Username must be 4-80 characters. Password must be 6-72 characters and include uppercase and lowercase letters, a number, and a symbol; common passwords are not allowed.\n");
    exit(1);
}

$stmt = db()->prepare("INSERT INTO users(username,password_hash,role,status) VALUES(?,?,'Administrator','Active')");
$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);

fwrite(STDOUT, "Administrator created successfully.\n");

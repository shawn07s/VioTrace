<?php
// VioTrace configuration. Edit these values to match your XAMPP setup.
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'viotrace';
const DB_USER = 'root';
const DB_PASS = '';            // XAMPP default root password is empty
const BASE_URL = '/viotrace';  // folder name inside htdocs

date_default_timezone_set('Asia/Dhaka');

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** One PDO connection per request. Tags the session with the acting user for audit triggers. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        $pdo->exec("SET time_zone = '+06:00'");
        if (!empty($_SESSION['user']['user_id'])) {
            $pdo->exec('SET @current_user_id = ' . (int)$_SESSION['user']['user_id']);
        }
    }
    return $pdo;
}

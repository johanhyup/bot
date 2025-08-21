<?php
declare(strict_types=1);

session_start();
error_reporting(E_ALL);
ini_set('display_errors', '0'); // 화면엔 숨기고
date_default_timezone_set('Asia/Seoul');

// ---------- 로그 경로 상수 ----------
define('BOT_LOG_DIR', dirname(__DIR__) . '/logs');

// ---------- .env 로드 ----------
$envFile = dirname(__DIR__) . '/python/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (($pos = strpos($line, '=')) !== false) {
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            if (!getenv($key)) {
                putenv("$key=$val");
            }
        }
    }
}

// ---------- 로깅 ----------
function log_error(string $msg): void
{
    if (!is_dir(BOT_LOG_DIR)) {
        mkdir(BOT_LOG_DIR, 0750, true);
    }
    $file = BOT_LOG_DIR . '/error.log';
    $entry = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $msg);
    file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
}

// ---------- DB ----------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            getenv('MYSQL_HOST'),
            getenv('MYSQL_DB')
        );
        $pdo = new PDO($dsn, getenv('MYSQL_USER'), getenv('MYSQL_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
    return $pdo;
}

// ---------- 전역 예외 처리 ----------
set_exception_handler(function (Throwable $e) {
    log_error($e->getMessage());
    http_response_code(503);
    exit('Service Unavailable');
});

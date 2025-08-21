<?php
session_start();
session_regenerate_id(true);
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: /admin_login.php?error=1');
    exit;
}

try {
    $stmt = db()->prepare('SELECT id, password_hash, role FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash']) && $user['role'] === 'admin') {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']   = $user['role'];
        header('Location: /admin.php');
        exit;
    }
} catch (Throwable $e) {
    log_error($e->getMessage());
}

header('Location: /admin_login.php?error=1');
exit;

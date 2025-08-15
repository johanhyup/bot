<?php
session_start();
session_regenerate_id(true);
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

try {
    $stmt = $pdo->prepare("SELECT id, username, password, name, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo "<script>alert('DB 오류: ".$e->getMessage()."'); window.location.href='/admin_login.php';</script>";
    exit;
}

if (!$user || !password_verify($password, $user['password'])) {
    echo "<script>alert('로그인 실패: 아이디 또는 비밀번호가 올바르지 않습니다.'); window.location.href='/admin_login.php';</script>";
    exit;
}

if (($user['role'] ?? 'user') !== 'admin') {
    echo "<script>alert('관리자 권한이 없습니다. 관리자에게 문의하세요.'); window.location.href='/admin_login.php';</script>";
    exit;
}

// 로그인 성공
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['role']    = $user['role'];
$_SESSION['name']    = $user['name'];

header('Location: /admin.php');
exit;

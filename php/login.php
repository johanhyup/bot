<?php
// php/login.php: Unchanged, but added session regen for security
session_start();
session_regenerate_id(true);
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name']; // 로그인한 사용자 이름 세션 저장
        header('Location: ../dashboard.php');
        exit;
    } else {
        echo "<script>alert('로그인 실패'); window.location.href='../index.php';</script>";
        exit;
    }
}

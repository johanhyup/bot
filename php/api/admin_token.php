<?php
declare(strict_types=1);
session_start();

// 관리자만 허용
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'forbidden']);
    exit;
}

// 서버 환경에서 ADMIN_TOKEN 읽기(없으면 빈 문자열)
$token = getenv('ADMIN_TOKEN') ?: '';

// (추가) 쿠키로도 전달 → FastAPI가 쿠키 인증 허용
if ($token !== '') {
    if (PHP_VERSION_ID >= 70300) {
        setcookie('ADMIN_TOKEN', $token, ['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    } else {
        // PHP 7.2 이하 호환
        setcookie('ADMIN_TOKEN', $token, 0, '/; samesite=Lax', '', false, true);
    }
}

// 응답
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['token' => $token]);

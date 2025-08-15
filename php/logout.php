<?php
// 캐시 금지
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
// 세션 데이터 비움
$_SESSION = [];

// 세션 쿠키 제거
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"] ?? '/', $params["domain"] ?? '', $params["secure"] ?? false, $params["httponly"] ?? true);
}

// 세션 파기
session_destroy();

// 로그인 페이지로 이동
header('Location: /index.php');
exit;

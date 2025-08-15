<?php
// 관리자 전용 로그인 페이지
session_start();
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? 'user') === 'admin') {
    header('Location: /admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 로그인</title>
    <link rel="stylesheet" href="styles/login.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center vh-100 bg-light">
    <div class="card shadow p-4" style="min-width: 320px; max-width: 420px; width: 100%;">
        <h3 class="text-center mb-3">관리자 로그인</h3>
        <form id="loginForm" action="/php/login_admin.php" method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="username" class="form-label">아이디</label>
                <input type="text" id="username" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">비밀번호</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button id="loginBtn" type="submit" class="btn btn-primary w-100">
                <span id="loginText">로그인</span>
                <span id="loginSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
            </button>
        </form>
        <div class="text-center mt-3">
            <a href="/index.php" class="text-decoration-none">일반 사용자 로그인으로 돌아가기</a>
        </div>
    </div>
    <script src="/js/login.js"></script>
</body>
</html>

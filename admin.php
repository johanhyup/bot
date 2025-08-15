<?php
require_once __DIR__ . '/php/init.php';
session_start();
require_once __DIR__ . '/php/db.php';

// 세션에 role 없으면 DB에서 가져오기
if (!isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['role'] = $stmt->fetchColumn() ?: 'user';
}

// 관리자 권한 체크
if (($_SESSION['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    echo '관리자 권한이 필요합니다.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>아비트라지 매매 시스템 - 관리자 페이지</title>
    <link rel="stylesheet" href="styles/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php"><i class="bi bi-arrow-left-circle me-2"></i>대시보드</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="php/logout.php"><i class="bi bi-box-arrow-right"></i> 로그아웃</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h2 class="mb-3">사용자 관리</h2>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>아이디</th>
                        <th>이름</th>
                        <th>권한</th>
                        <th>액션</th>
                    </tr>
                </thead>
                <tbody id="userList"><!-- JS로 채움 --></tbody>
            </table>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/admin.js"></script>
</body>
</html>

<?php
require_once __DIR__ . '/php/init.php';
session_start();
require_once __DIR__ . '/php/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

// 세션에 이름이 없으면 DB에서 조회
$userName = $_SESSION['name'] ?? null;
if (!$userName) {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userName = $stmt->fetchColumn() ?: '사용자';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>아비트라지 매매 시스템 - 대시보드</title>
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#"><i class="bi bi-graph-up me-2"></i>아비트라지 시스템</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php"><i class="bi bi-gear"></i> 관리자 페이지</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="php/logout.php"><i class="bi bi-box-arrow-right"></i> 로그아웃</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h1 class="text-center mb-4 fw-bold"><?= htmlspecialchars($userName) ?>님 환영합니다</h1>

        <div class="row gy-4">
            <div class="col-md-4">
                <div class="card border-0 shadow h-100 rounded-3">
                    <div class="card-body text-center">
                        <i class="bi bi-wallet2 display-4 text-success mb-3"></i>
                        <h5 class="card-title">업비트 평가액(USDT)</h5>
                        <p class="card-text fs-3 fw-bold" id="upbitBalance">로딩 중...</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow h-100 rounded-3">
                    <div class="card-body text-center">
                        <i class="bi bi-currency-bitcoin display-4 text-primary mb-3"></i>
                        <h5 class="card-title">바이낸스 평가액(USDT)</h5>
                        <p class="card-text fs-3 fw-bold" id="binanceBalance">로딩 중...</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow h-100 rounded-3">
                    <div class="card-body text-center">
                        <i class="bi bi-bar-chart-line-fill display-4 text-warning mb-3"></i>
                        <h5 class="card-title">누적 수익금</h5>
                        <p class="card-text fs-3 fw-bold" id="cumulativeProfit">로딩 중...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-md-6">
                <h2 class="mb-3 fw-bold">오늘 매매 내역</h2>
                <div class="table-responsive">
                    <table class="table table-striped table-hover rounded-3 overflow-hidden">
                        <thead class="table-dark">
                            <tr>
                                <th>시간</th>
                                <th>거래 유형</th>
                                <th>금액</th>
                                <th>수익</th>
                            </tr>
                        </thead>
                        <tbody id="tradeHistory">
                            <!-- JS로 동적 추가 -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <h2 class="mb-3 fw-bold">수익 추이 그래프</h2>
                <div class="card border-0 shadow rounded-3">
                    <div class="card-body">
                        <canvas id="profitChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/dashboard.js"></script>
</body>
</html>

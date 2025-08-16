<?php
session_start();
require_once __DIR__ . '/../php/db.php';

if (!isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['role'] = $stmt->fetchColumn() ?: 'user';
}
if (($_SESSION['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    echo '관리자 권한이 필요합니다.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>아비트리지 제어</title>
  <link rel="stylesheet" href="/styles/admin.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="/admin/"><i class="bi bi-arrow-left-circle me-2"></i>관리</a>
    <div class="collapse navbar-collapse"><ul class="navbar-nav ms-auto">
      <li class="nav-item"><a class="nav-link" href="/php/logout.php">로그아웃</a></li>
    </ul></div>
  </div>
</nav>

<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-3">
    <h3 class="m-0">아비트리지 엔진</h3>
    <span id="arbStatus" class="badge bg-secondary">상태 조회 중...</span>
    <button id="btnStart" class="btn btn-success btn-sm">시작</button>
    <button id="btnStop" class="btn btn-outline-danger btn-sm">중지</button>
  </div>

  <div class="card mb-4">
    <div class="card-header">설정</div>
    <div class="card-body row g-3">
      <div class="col-12">
        <label class="form-label">심볼(콤마로 구분, 예: BTC/USDT, XRP/USDT, BIT/USDT)</label>
        <input id="symbols" class="form-control" placeholder="BTC/USDT, XRP/USDT, BIT/USDT">
      </div>
      <div class="col-md-3">
        <label class="form-label">최소 스프레드(bp)</label>
        <input id="minSpreadBp" type="number" class="form-control" value="30">
      </div>
      <div class="col-md-3">
        <label class="form-label">Upbit 테이커 수수료(bp)</label>
        <input id="feeUpbit" type="number" class="form-control" value="8">
      </div>
      <div class="col-md-3">
        <label class="form-label">Binance 테이커 수수료(bp)</label>
        <input id="feeBinance" type="number" class="form-control" value="10">
      </div>
      <div class="col-md-3">
        <label class="form-label">주기(초)</label>
        <input id="intervalSec" type="number" class="form-control" value="15">
      </div>
      <div class="col-12">
        <button id="btnSave" class="btn btn-primary">저장</button>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>최근 시그널</span>
      <button id="btnRefresh" class="btn btn-outline-secondary btn-sm">새로고침</button>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped">
          <thead class="table-dark">
            <tr><th>시간</th><th>심볼</th><th>전략</th><th>스프레드(bp)</th></tr>
          </thead>
          <tbody id="signalsBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="/js/admin-arb.js"></script>
</body>
</html>

<?php
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../exchange.php';

$upbitBalance = 0.0;
$binanceBalance = 0.0;
$errors = [];

try {
    $upbitBalance = fetch_upbit_krw_balance();
} catch (Throwable $e) {
    $errors[] = 'Upbit 조회 실패: ' . $e->getMessage();
}

try {
    $binanceBalance = fetch_binance_usdt_balance();
} catch (Throwable $e) {
    $errors[] = 'Binance 조회 실패: ' . $e->getMessage();
}

// 0원 경고 (키 권한/지갑 위치 등 점검 유도)
if ($binanceBalance <= 0 && empty($errors)) {
    $errors[] = 'Binance USDT가 0입니다. 스팟 잔고 0이거나 키 권한/지갑 분리(펀딩)일 수 있습니다. /php/api/debug_binance.php 참고';
}

// 누적 수익(전체 합)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(profit), 0) AS total_profit FROM trades WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$cum = (float)$stmt->fetchColumn();

// 금일 거래 내역
$todayStart = date('Y-m-d') . ' 00:00:00';
$stmt = $pdo->prepare("SELECT time, type, amount, profit FROM trades WHERE user_id = ? AND time >= ? ORDER BY time DESC");
$stmt->execute([$_SESSION['user_id'], $todayStart]);
$trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'upbitBalance' => (float)$upbitBalance,
    'binanceBalance' => (float)$binanceBalance,
    'cumulativeProfit' => (float)$cum,
    'trades' => $trades ?: [],
    'errors' => $errors
]);

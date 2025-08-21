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
    <title>암호화폐 대시보드</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">암호화폐 포트폴리오</h1>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5>총 평가액</h5>
                    </div>
                    <div class="card-body">
                        <h2 id="totalUSDT">로딩 중...</h2>
                        <p id="totalKRW" class="text-muted">로딩 중...</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>업비트 잔고</h5>
                    </div>
                    <div class="card-body">
                        <div id="upbitBalance">로딩 중...</div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>바이낸스 잔고</h5>
                    </div>
                    <div class="card-body">
                        <div id="binanceBalance">로딩 중...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        loadDashboard();
        
        // 30초마다 자동 새로고침
        setInterval(loadDashboard, 30000);
    });
    
    function loadDashboard() {
        $.ajax({
            url: '/api/dashboard',
            method: 'GET',
            success: function(data) {
                if (data.status === 'success') {
                    updateDashboard(data.data);
                } else {
                    showError('데이터 로딩 실패');
                }
            },
            error: function(xhr) {
                showError('데이터 로딩 중 오류가 발생했습니다. HTTP ' + xhr.status + ' ' + xhr.statusText);
            }
        });
    }
    
    function updateDashboard(data) {
        // 총 평가액 업데이트
        $('#totalUSDT').text(data.total_evaluation.usdt.toLocaleString() + ' USDT');
        $('#totalKRW').text(data.total_evaluation.krw.toLocaleString() + ' KRW');
        
        // 업비트 잔고 업데이트
        let upbitHtml = '';
        if (data.upbit.coins.length > 0) {
            upbitHtml = '<table class="table table-sm">';
            data.upbit.coins.forEach(coin => {
                upbitHtml += `<tr>
                    <td>${coin.currency}</td>
                    <td>${coin.balance.toLocaleString()}</td>
                    <td>${coin.usdt_value} USDT</td>
                </tr>`;
            });
            upbitHtml += '</table>';
            upbitHtml += `<p><strong>총합: ${data.upbit.total_usdt} USDT</strong></p>`;
        } else {
            upbitHtml = '<p>잔고가 없거나 로딩 실패</p>';
        }
        $('#upbitBalance').html(upbitHtml);
        
        // 바이낸스 잔고 업데이트
        let binanceHtml = '';
        if (data.binance.coins.length > 0) {
            binanceHtml = '<table class="table table-sm">';
            data.binance.coins.forEach(coin => {
                binanceHtml += `<tr>
                    <td>${coin.currency}</td>
                    <td>${coin.balance.toLocaleString()}</td>
                    <td>${coin.usdt_value} USDT</td>
                </tr>`;
            });
            binanceHtml += '</table>';
            binanceHtml += `<p><strong>총합: ${data.binance.total_usdt} USDT</strong></p>`;
        } else {
            binanceHtml = '<p>잔고가 없거나 로딩 실패</p>';
        }
        $('#binanceBalance').html(binanceHtml);
    }
    
    function showError(message) {
        console.error(message);
        $('#totalUSDT').text('오류 발생');
        $('#totalKRW').text(message);
    }
    </script>
</body>
</html>

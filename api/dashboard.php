<?php
require_once dirname(__DIR__) . '/php/bootstrap.php';

// CORS 헤더 설정
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리 (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 세션 체크
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    // 대시보드 데이터 조회
    $data = [
        'status' => 'success',
        'user_id' => $_SESSION['user_id'],
        'role' => $_SESSION['role'] ?? 'user',
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => [
            'trades' => [],
            'balance' => 0,
            'profits' => 0
        ]
    ];

    // 실제 데이터베이스에서 조회 (예시)
    try {
        $pdo = db();
        // 필요한 데이터 쿼리 추가
        // $stmt = $pdo->prepare("SELECT * FROM trades WHERE user_id = ?");
        // $stmt->execute([$_SESSION['user_id']]);
        // $data['data']['trades'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        log_error('Dashboard API DB Error: ' . $e->getMessage());
    }

    echo json_encode($data);

} catch (Throwable $e) {
    log_error('Dashboard API Error: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Service Unavailable']);
}
?>

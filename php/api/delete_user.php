<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? 'user') !== 'admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int)($input['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'invalid_id']);
    exit;
}
if ($id === (int)($_SESSION['user_id'] ?? 0)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '본인 계정은 삭제할 수 없습니다.']);
    exit;
}

try {
    $st = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $st->execute([$id]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
}

<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../db.php';

$meId = (int)($_SESSION['user_id'] ?? 0);
$meRole = $_SESSION['role'] ?? 'user';

if ($meRole !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int)($input['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'message' => 'invalid id']); exit; }

// 자기 자신 삭제 방지(원치 않으면 제거)
if ($id === $meId) {
    echo json_encode(['success' => false, 'message' => 'cannot delete yourself']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $ok = $stmt->execute([$id]);
    echo json_encode(['success' => (bool)$ok]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

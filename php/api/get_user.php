<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../db.php';

$meId = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? 'user';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['error' => 'invalid id']); exit; }

if ($role !== 'admin' && $id !== (int)$meId) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, name, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['error' => 'not found']); exit; }
    echo json_encode($row);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error', 'detail' => $e->getMessage()]);
}

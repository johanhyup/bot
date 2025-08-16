<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? 'user') !== 'admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

try {
    $st = $pdo->prepare('SELECT id, username, name, role FROM users WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    echo json_encode($row, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error', 'message' => $e->getMessage()]);
}

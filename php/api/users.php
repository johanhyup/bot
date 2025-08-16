<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../db.php';

// 관리자만 허용
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? 'user') !== 'admin')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'forbidden']);
    exit;
}

try {
    $stmt = $pdo->query("SELECT id, username, name, role FROM users ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'db_error', 'message' => $e->getMessage()]);
}

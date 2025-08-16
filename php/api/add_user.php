<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// 관리자만 허용
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? 'user') !== 'admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$username = trim($input['username'] ?? '');
$password = (string)($input['password'] ?? '');
$name     = trim($input['name'] ?? '');
$role     = ($input['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

if ($username === '' || $password === '' || $name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '필수 항목 누락']);
    exit;
}

try {
    // 중복 체크
    $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $st->execute([$username]);
    if ((int)$st->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => '이미 존재하는 아이디']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $st = $pdo->prepare('INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)');
    $st->execute([$username, $hash, $name, $role]);

    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
}

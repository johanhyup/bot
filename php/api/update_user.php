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
$id       = (int)($input['id'] ?? 0);
$username = trim($input['username'] ?? '');
$name     = trim($input['name'] ?? '');
$role     = ($input['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
$password = $input['password'] ?? null;

if ($id <= 0 || $username === '' || $name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '입력 오류']);
    exit;
}

try {
    // username 중복(본인 제외)
    $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?');
    $st->execute([$username, $id]);
    if ((int)$st->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => '이미 존재하는 아이디']);
        exit;
    }

    if ($password !== null && $password !== '') {
        $hash = password_hash((string)$password, PASSWORD_DEFAULT);
        $st = $pdo->prepare('UPDATE users SET username = ?, password = ?, name = ?, role = ? WHERE id = ?');
        $st->execute([$username, $hash, $name, $role, $id]);
    } else {
        $st = $pdo->prepare('UPDATE users SET username = ?, name = ?, role = ? WHERE id = ?');
        $st->execute([$username, $name, $role, $id]);
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
}

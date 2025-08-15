<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
session_start();
require_once __DIR__ . '/../db.php';

$meId = (int)($_SESSION['user_id'] ?? 0);
$meRole = $_SESSION['role'] ?? 'user';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int)($input['id'] ?? 0);
$username = trim($input['username'] ?? '');
$name = trim($input['name'] ?? '');
$role = $input['role'] ?? null;
$password = $input['password'] ?? null;

if (!$id || !$username || !$name) {
    echo json_encode(['success' => false, 'message' => 'missing fields']);
    exit;
}

if ($meRole !== 'admin' && $id !== $meId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'forbidden']);
    exit;
}

try {
    $fields = ['username' => $username, 'name' => $name];
    $set = "username = :username, name = :name";

    if ($password !== null && $password !== '') {
        $fields['password'] = password_hash($password, PASSWORD_DEFAULT);
        $set .= ", password = :password";
    }
    if ($meRole === 'admin' && in_array($role, ['user','admin'], true)) {
        $fields['role'] = $role;
        $set .= ", role = :role";
    }

    $fields['id'] = $id;
    $sql = "UPDATE users SET $set WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($fields);

    echo json_encode(['success' => (bool)$ok]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

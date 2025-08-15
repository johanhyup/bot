<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit;
}
require_once __DIR__ . '/../db.php';

function usage($msg = '') {
    if ($msg) fwrite(STDERR, $msg . PHP_EOL);
    fwrite(STDERR, "Usage: --username <id> --password <pw> [--role user|admin]\n");
    exit(1);
}

$opts = getopt('', ['username:', 'password:', 'role::']);
$username = isset($opts['username']) ? trim((string)$opts['username']) : '';
$password = isset($opts['password']) ? (string)$opts['password'] : '';
$role     = isset($opts['role']) ? trim((string)$opts['role']) : null;

if (!$username || !$password) {
    usage('username/password 가 필요합니다.');
}
if ($role !== null && !in_array($role, ['user','admin'], true)) {
    usage('role 은 user|admin 중 하나여야 합니다.');
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        fwrite(STDERR, "Error: user not found: {$username}\n");
        exit(1);
    }
    $fields = ['password' => password_hash($password, PASSWORD_DEFAULT)];
    $sql = "UPDATE users SET password = :password";
    if ($role !== null) {
        $fields['role'] = $role;
        $sql .= ", role = :role";
    }
    $sql .= " WHERE id = :id";
    $fields['id'] = (int)$row['id'];

    $upd = $pdo->prepare($sql);
    $ok = $upd->execute($fields);
    if (!$ok) throw new RuntimeException('update failed');

    echo "OK: password updated for '{$username}'" . ($role ? " (role={$role})" : "") . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

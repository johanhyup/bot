<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit;
}
// 사용법 예:
//  새로 생성: php /var/www/bot/bot/php/cli/create_admin.php --username admin --password "pass1234" --name "관리자"
//  존재 시 갱신(역할=admin, 비번 변경): 위와 동일 명령으로 실행
require_once __DIR__ . '/../db.php';

function usage($msg = '') {
    if ($msg) fwrite(STDERR, $msg . PHP_EOL);
    fwrite(STDERR, "Usage: --username <id> --password <pw> [--name <displayName>]\n");
    exit(1);
}

$opts = getopt('', ['username:', 'password:', 'name::']);
$username = $opts['username'] ?? null;
$password = $opts['password'] ?? null;
$name     = $opts['name']     ?? '관리자';

if (!$username || !$password) {
    usage('username/password 가 필요합니다.');
}
ㅋ
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // 존재 여부 확인
    $stmt = $pdo->prepare("SELECT id, username, name, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // 갱신: role=admin, password 변경, name은 전달 시 갱신
        $stmt = $pdo->prepare("UPDATE users SET role = 'admin', password = ?, name = COALESCE(?, name) WHERE id = ?");
        $ok = $stmt->execute([$hash, $name, (int)$row['id']]);
        if (!$ok) throw new RuntimeException('업데이트 실패');
        echo "OK: existing user '{$username}' promoted to admin and password updated.\n";
    } else {
        // 생성
        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, 'admin')");
        $ok = $stmt->execute([$username, $hash, $name]);
        if (!$ok) throw new RuntimeException('생성 실패');
        echo "OK: admin user '{$username}' created.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

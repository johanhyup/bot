<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit;
}
// 사용법:
//  php /var/www/bot/bot/php/cli/promote_admin.php --id 1
//  php /var/www/bot/bot/php/cli/promote_admin.php --username johndoe
require_once __DIR__ . '/../db.php';

$id = null;
$username = null;
foreach ($argv as $i => $a) {
    if ($a === '--id' && isset($argv[$i+1])) $id = (int)$argv[$i+1];
    if ($a === '--username' && isset($argv[$i+1])) $username = $argv[$i+1];
}
if (!$id && !$username) {
    fwrite(STDERR, "Usage: --id <num> or --username <name>\n");
    exit(1);
}

if ($id) {
    $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
    $ok = $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE username = ?");
    $ok = $stmt->execute([$username]);
}
if (!$ok || $stmt->rowCount() === 0) {
    fwrite(STDERR, "No user updated. Check id/username.\n");
    exit(1);
}
echo "OK: user promoted to admin\n";

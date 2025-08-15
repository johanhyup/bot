<?php
// 1) SQLite 연결($pdo) 먼저 생성
try {
    $dbDir = __DIR__;
    $dbFile = $dbDir . '/database.db';
    // 경로 쓰기 가능 여부 로그
    if (!is_writable($dbDir)) {
        error_log("[db.php] directory not writable: {$dbDir}");
    }
    if (file_exists($dbFile) && !is_writable($dbFile)) {
        error_log("[db.php] db file not writable: {$dbFile}");
    }

    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 잠금 완화: WAL 시도 → 실패 시 DELETE 폴백
    try {
        $pdo->exec("PRAGMA journal_mode=WAL;");
    } catch (Throwable $e) {
        error_log("[db.php] WAL enable failed, fallback to DELETE: " . $e->getMessage());
        try { $pdo->exec("PRAGMA journal_mode=DELETE;"); } catch (Throwable $e2) {}
    }
    $pdo->exec("PRAGMA synchronous=NORMAL;");
    $pdo->exec("PRAGMA busy_timeout=5000;");
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    die('DB 연결 실패: ' . $e->getMessage());
}

// 2) 스키마 생성
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    password TEXT,
    name TEXT,
    role TEXT DEFAULT 'user'
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS trades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    time DATETIME DEFAULT CURRENT_TIMESTAMP,
    type TEXT,
    amount REAL,
    profit REAL,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// 3) 초기 계정 보장 함수
function ensure_user(PDO $pdo, string $username, string $password, string $name, string $role = 'user'): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ((int)$stmt->fetchColumn() === 0) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $ins = $pdo->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)");
        $ins->execute([$username, $hashed, $name, $role]);
    }
}

// 4) 기본 관리자/요청하신 사용자 생성
ensure_user($pdo, 'admin', 'admin123', '관리자', 'admin');
ensure_user($pdo, 'jkcorp5005', '1234', '이종도', 'user');

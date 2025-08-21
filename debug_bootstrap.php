<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "1. 기본 PHP 동작 확인<br>";

try {
    echo "2. bootstrap.php 로드 시도...<br>";
    require_once __DIR__ . '/php/bootstrap.php';
    echo "3. bootstrap.php 로드 성공<br>";
    
    echo "4. .env 값 확인:<br>";
    echo "MYSQL_HOST: " . getenv('MYSQL_HOST') . "<br>";
    echo "MYSQL_DB: " . getenv('MYSQL_DB') . "<br>";
    
    echo "5. DB 연결 테스트...<br>";
    $pdo = db();
    echo "6. DB 연결 성공<br>";
    
    echo "7. 로그 테스트...<br>";
    log_error('debug_bootstrap.php 테스트');
    echo "8. 로그 기록 완료<br>";
    
} catch (Throwable $e) {
    echo "오류 발생: " . $e->getMessage() . "<br>";
    echo "파일: " . $e->getFile() . ":" . $e->getLine() . "<br>";
}
?>

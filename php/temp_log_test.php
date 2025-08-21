<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

// /tmp 디렉터리에 로그 기록 테스트
$tempLogFile = '/tmp/bot_test.log';
$message = "[" . date('Y-m-d H:i:s') . "] 임시 로그 테스트 from " . $_SERVER['HTTP_HOST'] . "\n";

$result = file_put_contents($tempLogFile, $message, FILE_APPEND | LOCK_EX);

echo "1. 임시 로그 파일: $tempLogFile<br>";
echo "2. 쓰기 결과: " . ($result !== false ? "성공 ({$result}바이트)" : '실패') . "<br>";
echo "3. 파일 존재: " . (file_exists($tempLogFile) ? '예' : '아니오') . "<br>";

if (file_exists($tempLogFile)) {
    echo "4. 파일 내용:<pre>" . htmlspecialchars(file_get_contents($tempLogFile)) . "</pre>";
}

// 원래 로그 디렉터리 상태도 함께 확인
$logDir = dirname(__DIR__) . '/logs';
echo "<hr>";
echo "5. 프로젝트 로그 디렉터리: $logDir<br>";
echo "6. 존재 여부: " . (is_dir($logDir) ? '예' : '아니오') . "<br>";
if (is_dir($logDir)) {
    echo "7. 쓰기 가능: " . (is_writable($logDir) ? '예' : '아니오') . "<br>";
}
?>

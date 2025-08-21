<?php
echo "PHP 동작 정상<br>";
echo "현재 시간: " . date('Y-m-d H:i:s') . "<br>";
echo "PHP 버전: " . PHP_VERSION . "<br>";

// .env 파일 확인
$envFile = dirname(__DIR__) . '/python/.env';
echo ".env 파일 존재: " . (file_exists($envFile) ? '예' : '아니오') . "<br>";

// logs 디렉터리 확인  
$logDir = dirname(__DIR__) . '/logs';
echo "logs 디렉터리 존재: " . (is_dir($logDir) ? '예' : '아니오') . "<br>";
echo "logs 디렉터리 쓰기 가능: " . (is_writable($logDir) ? '예' : '아니오') . "<br>";

phpinfo();
?>

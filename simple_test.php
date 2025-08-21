<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "PHP 테스트 시작<br>";
echo "현재 시간: " . date('Y-m-d H:i:s') . "<br>";
echo "PHP 버전: " . PHP_VERSION . "<br>";
echo "현재 디렉터리: " . __DIR__ . "<br>";
echo "웹 루트: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "테스트 완료";
?>

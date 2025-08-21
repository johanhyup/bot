<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "API 테스트 시작<br>";

// 1. RewriteRule이 작동하는지 확인
echo "요청된 URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "스크립트 이름: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "쿼리 스트링: " . ($_SERVER['QUERY_STRING'] ?? 'none') . "<br>";

// 2. php/api/dashboard.php 파일 존재 여부
$apiFile = __DIR__ . '/php/api/dashboard.php';
echo "API 파일 경로: " . $apiFile . "<br>";
echo "API 파일 존재: " . (file_exists($apiFile) ? '예' : '아니오') . "<br>";

if (file_exists($apiFile)) {
    echo "API 파일 읽기 가능: " . (is_readable($apiFile) ? '예' : '아니오') . "<br>";
}

// 3. bootstrap.php 테스트
try {
    echo "bootstrap.php 로드 시도...<br>";
    require_once __DIR__ . '/php/bootstrap.php';
    echo "bootstrap.php 로드 성공<br>";
} catch (Throwable $e) {
    echo "bootstrap.php 로드 실패: " . $e->getMessage() . "<br>";
}

echo "테스트 완료";
?>

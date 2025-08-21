<?php
echo "리라이트 테스트<br>";
echo "현재 파일: " . __FILE__ . "<br>";
echo "요청 URI: " . ($_SERVER['REQUEST_URI'] ?? 'none') . "<br>";
echo "스크립트 이름: " . ($_SERVER['SCRIPT_NAME'] ?? 'none') . "<br>";

// URL 변수들 확인
echo "<hr>";
echo "SERVER 변수들:<br>";
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'REQUEST') !== false || strpos($key, 'SCRIPT') !== false || strpos($key, 'REDIRECT') !== false) {
        echo "$key: $value<br>";
    }
}

// api 디렉터리 확인
echo "<hr>";
echo "API 디렉터리 존재: " . (is_dir(__DIR__ . '/api') ? '예' : '아니오') . "<br>";
echo "PHP API 디렉터리 존재: " . (is_dir(__DIR__ . '/php/api') ? '예' : '아니오') . "<br>";
?>

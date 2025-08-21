<?php
echo "<h2>Dashboard.php 파일 분석</h2>";

$dashboardFile = __DIR__ . '/dashboard.php';
if (file_exists($dashboardFile)) {
    echo "<h3>Dashboard.php 내용:</h3>";
    echo "<pre>" . htmlspecialchars(file_get_contents($dashboardFile)) . "</pre>";
} else {
    echo "dashboard.php 파일이 존재하지 않습니다.<br>";
}

echo "<hr>";
echo "<h2>JavaScript 파일들 확인</h2>";

$jsDir = __DIR__ . '/js';
if (is_dir($jsDir)) {
    $jsFiles = scandir($jsDir);
    foreach ($jsFiles as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'js') {
            echo "<h3>/js/{$file}:</h3>";
            $content = file_get_contents($jsDir . '/' . $file);
            
            // API 호출 부분만 찾기
            if (strpos($content, 'api/') !== false || strpos($content, '/api') !== false) {
                echo "<pre>" . htmlspecialchars($content) . "</pre>";
            } else {
                echo "API 호출이 없는 파일입니다.<br>";
            }
            echo "<hr>";
        }
    }
} else {
    echo "js 디렉터리가 없습니다.<br>";
}

echo "<h2>기존 API 파일들 확인</h2>";
$phpApiDir = __DIR__ . '/php/api';
if (is_dir($phpApiDir)) {
    $apiFiles = scandir($phpApiDir);
    foreach ($apiFiles as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            echo "<h3>/php/api/{$file}:</h3>";
            echo "<pre>" . htmlspecialchars(file_get_contents($phpApiDir . '/' . $file)) . "</pre>";
            echo "<hr>";
        }
    }
}
?>

<?php
// 모든 오류 표시 활성화
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "=== 로그 테스트 시작 ===<br><br>";

try {
    $logDir = __DIR__ . '/logs';
    $logFile = $logDir . '/simple_test.log';

    echo "1. 로그 디렉터리: $logDir<br>";
    echo "2. 현재 디렉터리: " . __DIR__ . "<br>";
    echo "3. 디렉터리 존재: " . (is_dir($logDir) ? '예' : '아니오') . "<br>";
    
    if (is_dir($logDir)) {
        echo "4. 디렉터리 쓰기 가능: " . (is_writable($logDir) ? '예' : '아니오') . "<br>";
    } else {
        echo "4. 디렉터리 생성 시도...<br>";
        $result = mkdir($logDir, 0775, true);
        echo "5. 생성 결과: " . ($result ? '성공' : '실패') . "<br>";
        
        if ($result) {
            echo "6. 생성 후 쓰기 가능: " . (is_writable($logDir) ? '예' : '아니오') . "<br>";
        }
    }

    // 로그 파일 쓰기 테스트
    echo "7. 파일 쓰기 테스트 시작...<br>";
    $message = "[" . date('Y-m-d H:i:s') . "] 직접 로그 테스트\n";
    $writeResult = file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);

    echo "8. 파일 쓰기 결과: " . ($writeResult !== false ? "성공 ({$writeResult}바이트)" : '실패') . "<br>";
    echo "9. 파일 존재: " . (file_exists($logFile) ? '예' : '아니오') . "<br>";

    if (file_exists($logFile)) {
        echo "10. 파일 내용:<pre>" . htmlspecialchars(file_get_contents($logFile)) . "</pre>";
    }

    // 권한 정보
    echo "<hr>";
    echo "권한 정보:<br>";
    echo "- 현재 사용자: " . get_current_user() . "<br>";
    echo "- 프로세스 UID: " . getmyuid() . "<br>";
    echo "- 프로세스 GID: " . getmygid() . "<br>";

    if (is_dir($logDir)) {
        $stat = stat($logDir);
        echo "- 디렉터리 권한: " . sprintf('%o', $stat['mode'] & 0777) . "<br>";
        echo "- 디렉터리 소유자 UID: " . $stat['uid'] . "<br>";
        echo "- 디렉터리 그룹 GID: " . $stat['gid'] . "<br>";
    }
    
    echo "<br>=== 테스트 완료 ===";
    
} catch (Exception $e) {
    echo "<br><strong>예외 발생:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>파일:</strong> " . $e->getFile() . ":" . $e->getLine() . "<br>";
} catch (Error $e) {
    echo "<br><strong>치명적 오류:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>파일:</strong> " . $e->getFile() . ":" . $e->getLine() . "<br>";
}
?>

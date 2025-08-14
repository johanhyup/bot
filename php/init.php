<?php
// 개발 환경 에러 출력
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// 필요 시 출력 버퍼링 (헤더 전송 경고 방지)
if (!headers_sent()) {
    // ob_start(); // 필요 시 주석 해제
}

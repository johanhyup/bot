<?php
require_once dirname(__DIR__) . '/php/bootstrap.php';

header('Content-Type: application/json');

$request_data = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'headers' => getallheaders(),
    'get_data' => $_GET,
    'post_data' => $_POST,
    'raw_input' => file_get_contents('php://input'),
    'session' => $_SESSION ?? [],
    'timestamp' => date('Y-m-d H:i:s')
];

// 로그에 기록
log_error('API Debug Request: ' . json_encode($request_data, JSON_PRETTY_PRINT));

// 응답
echo json_encode([
    'debug' => true,
    'message' => 'API 디버그 정보가 로그에 기록되었습니다',
    'request_info' => $request_data
]);
?>

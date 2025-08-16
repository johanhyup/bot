<?php
declare(strict_types=1);
session_start();

$path = trim((string)($_GET['path'] ?? ''), " \t\n\r\0\x0B/");
if ($path === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'missing path']);
    exit;
}

$target = "http://127.0.0.1:8000/api/" . $path;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = file_get_contents('php://input') ?: '';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $target,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 15,
]);

$sendHeaders = ['Content-Type: application/json'];
$adminToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
if (!$adminToken && isset($_SESSION['ADMIN_TOKEN'])) {
    $adminToken = (string)$_SESSION['ADMIN_TOKEN'];
}
if ($adminToken) $sendHeaders[] = 'X-Admin-Token: ' . $adminToken;
if ($method !== 'GET' && $method !== 'HEAD') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $sendHeaders[] = 'Content-Length: ' . strlen($body);
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $sendHeaders);

$resp = curl_exec($ch);
if ($resp === false) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'bad_gateway', 'message' => curl_error($ch)]);
    curl_close($ch);
    exit;
}
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 502;
$respHeaders = substr($resp, 0, $headerSize);
$respBody = substr($resp, $headerSize);
curl_close($ch);

http_response_code($status);
// Content-Type 전달
$ct = 'application/json; charset=utf-8';
foreach (explode("\r\n", $respHeaders) as $line) {
    if (stripos($line, 'Content-Type:') === 0) {
        $ct = trim(substr($line, strlen('Content-Type:')));
        break;
    }
}
header('Content-Type: ' . $ct);
echo $respBody;

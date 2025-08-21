<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = $_POST['url'] ?? 'unknown';
    $status = $_POST['status'] ?? 'unknown';
    $response = $_POST['response'] ?? '';
    
    log_error(sprintf(
        'AJAX 503 Error - URL: %s, Status: %s, Response: %s',
        $url,
        $status,
        substr($response, 0, 100)
    ));
    
    echo 'logged';
} else {
    http_response_code(405);
    echo 'Method not allowed';
}
?>

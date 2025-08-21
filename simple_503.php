<?php
http_response_code(503);
?>
<!DOCTYPE html>
<html>
<head>
    <title>서비스 점검 중</title>
</head>
<body>
    <h1>서비스 점검 중입니다</h1>
    <p>현재 시간: <?= date('Y-m-d H:i:s') ?></p>
    <p>잠시 후 다시 시도해주세요.</p>
</body>
</html>

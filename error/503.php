<?php
// 503 Service Unavailable – 사용자 안내 전용 페이지
http_response_code(503);
header('Retry-After: 30');             // 30초 후 재시도 권유
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>서비스 점검 중</title>
    <meta http-equiv="refresh" content="30"> <!-- 30초 후 자동 새로고침 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center vh-100 bg-light">
    <div class="text-center">
        <h1 class="display-6 mb-3">서비스 이용이 불가능합니다</h1>
        <p class="lead mb-4">
            현재 서버 점검 또는 과부하로 일시적으로 접속이 불가능합니다.<br>
            잠시 후 다시 시도해 주세요.
        </p>
        <p class="text-muted">지속적으로 문제가 발생하면 관리자에게 문의 바랍니다.</p>
    </div>
</body>
</html>

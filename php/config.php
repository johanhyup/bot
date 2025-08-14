<?php
// 운영에서는 환경변수로 관리하세요. (예: Apache SetEnv, Nginx fastcgi_param, export 등)
$upbitAccess = getenv('UPBIT_ACCESS_KEY') ?: 'A39bNkLvNeDi3uMKDvWoYG36PhHZESR7dzFGGUBW';
$upbitSecret = getenv('UPBIT_SECRET_KEY') ?: 'xZksx37gF2oBKjWnadxhRDc9xYTDCSIa0f3GB1sd';
$binanceKey  = getenv('BINANCE_API_KEY') ?: 'e18OAvpK5MsDSJAUGs95X1ZDYOPr619fNeNlWuC4z9bbXIRzLv4hY1l5vWvviYBW';
$binanceSec  = getenv('BINANCE_API_SECRET') ?: 'RrERe5jKLVYjV0mw1zWSxoO6o3ZDowDwLbf9oHnkDZ3ilVbNSaPfpkcL16OJ0DcF'; // 개행 제거

// 기존 상수 이름을 유지해 교체 비용 최소화
define('UPBIT_ACCESS_KEY', $upbitAccess);
define('UPBIT_SECRET_KEY', $upbitSecret);
define('BINANCE_API_KEY', $binanceKey);
define('BINANCE_API_SECRET', $binanceSec);

// 키가 비어있으면 실제 호출 실패합니다. 운영에서는 환경변수 사용 권장.

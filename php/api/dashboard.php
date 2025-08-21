<?php
require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 세션 체크 (개발 중에는 임시로 주석 처리)
    // if (!isset($_SESSION['user_id'])) {
    //     http_response_code(401);
    //     echo json_encode(['error' => 'Unauthorized']);
    //     exit;
    // }

    $upbitBalance = getUpbitBalance();
    $binanceBalance = getBinanceBalance();
    
    // USDT 기준 총 평가액 계산
    $totalUSDT = $upbitBalance['total_usdt'] + $binanceBalance['total_usdt'];
    
    $response = [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => [
            'upbit' => $upbitBalance,
            'binance' => $binanceBalance,
            'total_evaluation' => [
                'usdt' => round($totalUSDT, 2),
                'krw' => round($totalUSDT * 1300, 0) // 대략적인 환율
            ]
        ]
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    log_error('Dashboard API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
}

// 업비트 잔고 조회
function getUpbitBalance() {
    $accessKey = getenv('UPBIT_ACCESS_KEY');
    $secretKey = getenv('UPBIT_SECRET_KEY');
    
    if (!$accessKey || !$secretKey) {
        return ['coins' => [], 'total_usdt' => 0, 'error' => 'API keys not found'];
    }
    
    $uuid = uniqid();
    $queryHash = hash('sha512', 'accounts');
    $jwt = generateUpbitJWT($accessKey, $secretKey, $queryHash);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.upbit.com/v1/accounts',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $jwt,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['coins' => [], 'total_usdt' => 0, 'error' => 'Upbit API error: ' . $httpCode];
    }
    
    $accounts = json_decode($response, true);
    $coins = [];
    $totalUSDT = 0;
    
    foreach ($accounts as $account) {
        if (floatval($account['balance']) > 0) {
            $currency = $account['currency'];
            $balance = floatval($account['balance']);
            
            // KRW는 직접 환산, 다른 코인은 현재가 조회
            if ($currency === 'KRW') {
                $usdtValue = $balance / 1300; // 대략적인 환율
            } else {
                $usdtValue = getCoinPriceInUSDT($currency, 'upbit') * $balance;
            }
            
            $coins[] = [
                'currency' => $currency,
                'balance' => $balance,
                'usdt_value' => round($usdtValue, 2)
            ];
            
            $totalUSDT += $usdtValue;
        }
    }
    
    return [
        'coins' => $coins,
        'total_usdt' => round($totalUSDT, 2)
    ];
}

// 바이낸스 잔고 조회
function getBinanceBalance() {
    $apiKey = getenv('BINANCE_API_KEY');
    $apiSecret = getenv('BINANCE_API_SECRET');
    
    if (!$apiKey || !$apiSecret) {
        return ['coins' => [], 'total_usdt' => 0, 'error' => 'API keys not found'];
    }
    
    $timestamp = round(microtime(true) * 1000);
    $query = "timestamp={$timestamp}";
    $signature = hash_hmac('sha256', $query, $apiSecret);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.binance.com/api/v3/account?{$query}&signature={$signature}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'X-MBX-APIKEY: ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['coins' => [], 'total_usdt' => 0, 'error' => 'Binance API error: ' . $httpCode];
    }
    
    $account = json_decode($response, true);
    $coins = [];
    $totalUSDT = 0;
    
    foreach ($account['balances'] as $balance) {
        $free = floatval($balance['free']);
        $locked = floatval($balance['locked']);
        $total = $free + $locked;
        
        if ($total > 0) {
            $currency = $balance['asset'];
            
            if ($currency === 'USDT') {
                $usdtValue = $total;
            } else {
                $usdtValue = getCoinPriceInUSDT($currency, 'binance') * $total;
            }
            
            $coins[] = [
                'currency' => $currency,
                'balance' => $total,
                'usdt_value' => round($usdtValue, 2)
            ];
            
            $totalUSDT += $usdtValue;
        }
    }
    
    return [
        'coins' => $coins,
        'total_usdt' => round($totalUSDT, 2)
    ];
}

// 코인 가격을 USDT 기준으로 조회
function getCoinPriceInUSDT($currency, $exchange) {
    // 간단한 가격 조회 (실제로는 각 거래소별 API 호출)
    if ($exchange === 'upbit') {
        // 업비트는 KRW 마켓이므로 USD 환산
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.upbit.com/v1/ticker?markets=KRW-{$currency}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if ($data && isset($data[0]['trade_price'])) {
            return $data[0]['trade_price'] / 1300; // KRW를 USDT로 환산
        }
    } else {
        // 바이낸스 USDT 가격
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.binance.com/api/v3/ticker/price?symbol={$currency}USDT",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if ($data && isset($data['price'])) {
            return floatval($data['price']);
        }
    }
    
    return 0;
}

// 업비트 JWT 생성
function generateUpbitJWT($accessKey, $secretKey, $queryHash) {
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'access_key' => $accessKey,
        'nonce' => uniqid(),
        'query_hash' => $queryHash,
        'query_hash_alg' => 'SHA512'
    ]);
    
    $headerEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $payloadEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $secretKey, true);
    $signatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
}
?>

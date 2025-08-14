<?php
require_once __DIR__ . '/config.php';

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function upbit_jwt_token($accessKey, $secretKey, array $payload = []) {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = array_merge(['access_key' => $accessKey, 'nonce' => uniqid('', true)], $payload);

    $jwtHeader = base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $jwtPayload = base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signature = hash_hmac('sha256', $jwtHeader . '.' . $jwtPayload, $secretKey, true);
    return $jwtHeader . '.' . $jwtPayload . '.' . base64url_encode($signature);
}

function http_get_json($url, $headers = []) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException($err ?: 'HTTP GET failed');
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("HTTP $status: $res");
    }
    $json = json_decode($res, true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid JSON response');
    }
    return $json;
}

function http_get_raw($url, $headers = []) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException($err ?: 'HTTP GET failed');
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("HTTP $status: $res");
    }
    return $res;
}

// 추가: POST 원본문자열 요청
function http_post_raw($url, $headers = [], $body = '') {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException($err ?: 'HTTP POST failed');
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("HTTP $status: $res");
    }
    return $res;
}

function fetch_upbit_krw_balance(): float {
    $token = upbit_jwt_token(UPBIT_ACCESS_KEY, UPBIT_SECRET_KEY);
    $headers = ["Authorization: Bearer {$token}"];
    $url = 'https://api.upbit.com/v1/accounts';
    $accounts = http_get_json($url, $headers);
    foreach ($accounts as $acc) {
        if (isset($acc['currency']) && strtoupper($acc['currency']) === 'KRW') {
            $balance = isset($acc['balance']) ? (float)$acc['balance'] : 0.0;
            $locked = isset($acc['locked']) ? (float)$acc['locked'] : 0.0;
            return $balance + $locked;
        }
    }
    return 0.0;
}

function binance_server_time(): ?int {
    $url = 'https://api.binance.com/api/v3/time';
    try {
        $response = http_get_json($url);
        return isset($response['serverTime']) ? (int)$response['serverTime'] : null;
    } catch (Throwable $e) {
        return null;
    }
}

// 공통: 서명 요청(JSON) - base를 주입(api|sapi|fapi 등)
function binance_signed_request_json(string $base, string $path, string $method = 'GET', array $params = []) {
    $serverTime = binance_server_time();
    $params['timestamp'] = $serverTime ?: (int) floor(microtime(true) * 1000);
    $params['recvWindow'] = $params['recvWindow'] ?? 60000;

    $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $sig = hash_hmac('sha256', $qs, BINANCE_API_SECRET);
    $headers = ['X-MBX-APIKEY: ' . BINANCE_API_KEY, 'Content-Type: application/x-www-form-urlencoded'];

    if (strtoupper($method) === 'POST') {
        // POST: 서명을 포함한 본문으로 전송
        $url = "https://{$base}.binance.com{$path}";
        $body = $qs . '&signature=' . $sig;
        $raw = http_post_raw($url, $headers, $body);
    } else {
        // GET: 쿼리스트링에 서명 포함
        $url = "https://{$base}.binance.com{$path}?" . $qs . "&signature=" . $sig;
        $raw = http_get_raw($url, $headers);
    }

    if (defined('DEBUG_EXCHANGE') && DEBUG_EXCHANGE) {
        error_log("[BINANCE {$base}] {$path} {$method} raw: " . substr($raw, 0, 500));
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON from Binance');
    }
    if (isset($data['code']) && isset($data['msg']) && $data['code'] !== 0) {
        throw new RuntimeException("Binance error {$data['code']}: {$data['msg']}");
    }
    return $data;
}

// 기존: 스팟
function binance_signed_get_json(string $path, array $params = []) {
    return binance_signed_request_json('api', $path, 'GET', $params);
}

// 추가: SAPI GET/POST, FAPI GET
function binance_sapi_get(string $path, array $params = []) {
    return binance_signed_request_json('sapi', $path, 'GET', $params);
}
function binance_sapi_post(string $path, array $params = []) {
    return binance_signed_request_json('sapi', $path, 'POST', $params);
}
function binance_fapi_get(string $path, array $params = []) {
    return binance_signed_request_json('fapi', $path, 'GET', $params);
}

function fetch_binance_usdt_balance(): float {
    // 1) 스팟 /api/v3/account
    try {
        $spot = binance_signed_get_json('/api/v3/account');
        if (isset($spot['balances'])) {
            foreach ($spot['balances'] as $bal) {
                if (isset($bal['asset']) && strtoupper($bal['asset']) === 'USDT') {
                    $free = (float)($bal['free'] ?? 0);
                    $locked = (float)($bal['locked'] ?? 0);
                    $total = $free + $locked;
                    if ($total > 0) return $total;
                }
            }
        }
    } catch (Throwable $e) { /* 스팟 실패 → 폴백 */ }

    // 2) 펀딩 지갑 /sapi/v1/asset/getFundingAsset (POST, asset=USDT)
    try {
        $fund = binance_sapi_post('/sapi/v1/asset/getFundingAsset', ['asset' => 'USDT', 'needBtcValuation' => false]);
        // 응답은 배열 [{asset:'USDT', free:'...', locked:'...'}]
        if (is_array($fund)) {
            foreach ($fund as $row) {
                if (isset($row['asset']) && strtoupper($row['asset']) === 'USDT') {
                    $free = (float)($row['free'] ?? 0);
                    $locked = (float)($row['locked'] ?? 0);
                    $freeze = (float)($row['freeze'] ?? 0);
                    $withdrawing = (float)($row['withdrawing'] ?? 0);
                    $total = $free + $locked + $freeze + $withdrawing;
                    if ($total > 0) return $total;
                }
            }
        }
    } catch (Throwable $e) { /* 펀딩 실패 → 폴백 */ }

    // 3) 자산 구성 /sapi/v1/capital/config/getall
    try {
        $coins = binance_sapi_get('/sapi/v1/capital/config/getall');
        if (is_array($coins)) {
            foreach ($coins as $coin) {
                if (isset($coin['coin']) && strtoupper($coin['coin']) === 'USDT') {
                    $free = (float)($coin['free'] ?? 0);
                    $locked = (float)($coin['locked'] ?? 0);
                    $freeze = (float)($coin['freeze'] ?? 0);
                    $withdrawing = (float)($coin['withdrawing'] ?? 0);
                    $ipoable = (float)($coin['ipoable'] ?? 0);
                    $ipoing = (float)($coin['ipoing'] ?? 0);
                    $total = $free + $locked + $freeze + $withdrawing + $ipoable + $ipoing;
                    if ($total > 0) return $total;
                }
            }
        }
    } catch (Throwable $e) { /* 자산구성 실패 → 폴백 */ }

    // 4) USD-M 선물 /fapi/v2/balance
    try {
        $fapi = binance_fapi_get('/fapi/v2/balance');
        if (is_array($fapi)) {
            foreach ($fapi as $row) {
                // { accountAlias, asset, balance, withdrawAvailable, ... }
                if (isset($row['asset']) && strtoupper($row['asset']) === 'USDT') {
                    $bal = (float)($row['balance'] ?? 0);
                    if ($bal > 0) return $bal;
                }
            }
        }
    } catch (Throwable $e) { /* 선물 실패 → 폴백 */ }

    // 5) (선택) 마진 /sapi/v1/margin/account
    try {
        $margin = binance_sapi_get('/sapi/v1/margin/account');
        if (isset($margin['userAssets']) && is_array($margin['userAssets'])) {
            foreach ($margin['userAssets'] as $a) {
                if (strtoupper($a['asset'] ?? '') === 'USDT') {
                    $free = (float)($a['free'] ?? 0);
                    $locked = (float)($a['locked'] ?? 0);
                    $borrowed = (float)($a['borrowed'] ?? 0);
                    $interest = (float)($a['interest'] ?? 0);
                    $total = ($free + $locked) - ($borrowed + $interest);
                    if ($total > 0) return $total;
                }
            }
        }
    } catch (Throwable $e) { /* 마진 실패 → 종료 */ }

    return 0.0;
}

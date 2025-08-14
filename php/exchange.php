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

function binance_signed_request_json(string $base, string $path, string $method = 'GET', array $params = []) {
    $serverTime = binance_server_time();
    $params['timestamp'] = $serverTime ?: (int) floor(microtime(true) * 1000);
    $params['recvWindow'] = $params['recvWindow'] ?? 60000;

    $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $sig = hash_hmac('sha256', $qs, BINANCE_API_SECRET);
    $headers = ['X-MBX-APIKEY: ' . BINANCE_API_KEY, 'Content-Type: application/x-www-form-urlencoded'];

    if (strtoupper($method) === 'POST') {
        $url = "https://{$base}.binance.com{$path}";
        $body = $qs . '&signature=' . $sig;
        $raw = http_post_raw($url, $headers, $body);
    } else {
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

function binance_signed_get_json(string $path, array $params = []) {
    return binance_signed_request_json('api', $path, 'GET', $params);
}

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
    } catch (Throwable $e) { }

    try {
        $fund = binance_sapi_post('/sapi/v1/asset/getFundingAsset', ['asset' => 'USDT', 'needBtcValuation' => false]);
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
    } catch (Throwable $e) { }

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
    } catch (Throwable $e) { }

    try {
        $fapi = binance_fapi_get('/fapi/v2/balance');
        if (is_array($fapi)) {
            foreach ($fapi as $row) {
                if (isset($row['asset']) && strtoupper($row['asset']) === 'USDT') {
                    $bal = (float)($row['balance'] ?? 0);
                    if ($bal > 0) return $bal;
                }
            }
        }
    } catch (Throwable $e) { }

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
    } catch (Throwable $e) { }

    return 0.0;
}

function upbit_get_tickers(array $markets): array {
    if (empty($markets)) return [];
    $qs = http_build_query(['markets' => implode(',', $markets)]);
    $arr = http_get_json('https://api.upbit.com/v1/ticker?' . $qs);
    $map = [];
    foreach ($arr as $row) {
        if (!empty($row['market']) && isset($row['trade_price'])) {
            $map[$row['market']] = (float)$row['trade_price'];
        }
    }
    return $map;
}

function upbit_total_usdt_valuation(): float {
    $token = upbit_jwt_token(UPBIT_ACCESS_KEY, UPBIT_SECRET_KEY);
    $headers = ["Authorization: Bearer {$token}"];
    $accounts = http_get_json('https://api.upbit.com/v1/accounts', $headers);

    $want = ['KRW','USDT','XRP','BIT'];
    $balances = ['KRW'=>0.0,'USDT'=>0.0,'XRP'=>0.0,'BIT'=>0.0];
    foreach ($accounts as $acc) {
        $cur = strtoupper($acc['currency'] ?? '');
        if (!in_array($cur, $want, true)) continue;
        $free = (float)($acc['balance'] ?? 0);
        $locked = (float)($acc['locked'] ?? 0);
        $balances[$cur] += $free + $locked;
    }

    // 가격 데이터 요청: KRW-USDT 직상장 우선, 없으면 BTC 브릿지 사용
    $markets = [
        'KRW-USDT',        // 직상장(USDT 한 개의 KRW 가격)
        'USDT-BTC','KRW-BTC',
        'USDT-XRP','KRW-XRP','BTC-XRP',
        'USDT-BIT','KRW-BIT','BTC-BIT'
    ];
    $tickers = upbit_get_tickers($markets);

    $usdt_krw = $tickers['KRW-USDT'] ?? null; // KRW per 1 USDT
    $btc_usdt = $tickers['USDT-BTC'] ?? null; // USDT per 1 BTC
    $btc_krw  = $tickers['KRW-BTC'] ?? null;  // KRW per 1 BTC

    // KRW → USDT 환산비: 직상장(KRW-USDT)이 있으면 1 / KRW_USDT, 없으면 BTC 브릿지(USDT/BTC / KRW/BTC)
    $krw_to_usdt = null;
    if ($usdt_krw && $usdt_krw > 0) {
        $krw_to_usdt = 1.0 / $usdt_krw;
    } elseif ($btc_usdt && $btc_krw && $btc_krw > 0) {
        $krw_to_usdt = $btc_usdt / $btc_krw;
    }

    $total = 0.0;

    $total += $balances['USDT'];

    // KRW -> USDT
    if ($balances['KRW'] > 0) {
        if ($krw_to_usdt) {
            $total += $balances['KRW'] * $krw_to_usdt;
        } else {
            throw new RuntimeException('Upbit KRW→USDT 환산 실패(KRW-USDT/브릿지 가격 부재)');
        }
    }

    if ($balances['XRP'] > 0) {
        $px = null;
        if (isset($tickers['USDT-XRP'])) {
            $px = (float)$tickers['USDT-XRP'];
        } elseif (isset($tickers['KRW-XRP']) && $krw_to_usdt) {
            $px = (float)$tickers['KRW-XRP'] * $krw_to_usdt;
        } elseif (isset($tickers['BTC-XRP']) && $btc_usdt) {
            $px = (float)$tickers['BTC-XRP'] * $btc_usdt;
        }
        if ($px === null) throw new RuntimeException('Upbit XRP 가격 조회 실패');
        $total += $balances['XRP'] * $px;
    }

    if ($balances['BIT'] > 0) {
        $px = null;
        if (isset($tickers['USDT-BIT'])) {
            $px = (float)$tickers['USDT-BIT'];
        } elseif (isset($tickers['KRW-BIT']) && $krw_to_usdt) {
            $px = (float)$tickers['KRW-BIT'] * $krw_to_usdt;
        } elseif (isset($tickers['BTC-BIT']) && $btc_usdt) {
            $px = (float)$tickers['BTC-BIT'] * $btc_usdt;
        }
        if ($px === null) throw new RuntimeException('Upbit BIT 가격 조회 실패');
        $total += $balances['BIT'] * $px;
    }

    return (float)$total;
}

function binance_symbol_prices(array $symbols): array {
    if (empty($symbols)) return [];
    $url = 'https://api.binance.com/api/v3/ticker/price?symbols=' . urlencode(json_encode(array_values($symbols)));
    $list = http_get_json($url);
    $map = [];
    foreach ($list as $row) {
        if (isset($row['symbol'], $row['price'])) {
            $map[$row['symbol']] = (float)$row['price'];
        }
    }
    return $map;
}

function binance_total_usdt_valuation(): float {
    $assets = ['USDT'=>0.0,'BIT'=>0.0,'XRP'=>0.0];

    try {
        $spot = binance_signed_get_json('/api/v3/account');
        if (isset($spot['balances']) && is_array($spot['balances'])) {
            foreach ($spot['balances'] as $bal) {
                $a = strtoupper($bal['asset'] ?? '');
                if (!isset($assets[$a])) continue;
                $free = (float)($bal['free'] ?? 0);
                $locked = (float)($bal['locked'] ?? 0);
                $assets[$a] += $free + $locked;
            }
        }
    } catch (Throwable $e) { }

    try {
        $fund = binance_sapi_post('/sapi/v1/asset/getFundingAsset', []);
        if (is_array($fund)) {
            foreach ($fund as $row) {
                $a = strtoupper($row['asset'] ?? '');
                if (!isset($assets[$a])) continue;
                $free = (float)($row['free'] ?? 0);
                $locked = (float)($row['locked'] ?? 0);
                $freeze = (float)($row['freeze'] ?? 0);
                $withdrawing = (float)($row['withdrawing'] ?? 0);
                $assets[$a] += $free + $locked + $freeze + $withdrawing;
            }
        }
    } catch (Throwable $e) { }

    $prices = binance_symbol_prices(['BITUSDT','XRPUSDT']);
    $bitPx = $prices['BITUSDT'] ?? null;
    $xrpPx = $prices['XRPUSDT'] ?? null;

    $total = 0.0;
    $total += $assets['USDT'];
    if ($assets['BIT'] > 0) {
        if ($bitPx === null) throw new RuntimeException('Binance BITUSDT 가격 조회 실패');
        $total += $assets['BIT'] * $bitPx;
    }
    if ($assets['XRP'] > 0) {
        if ($xrpPx === null) throw new RuntimeException('Binance XRPUSDT 가격 조회 실패');
        $total += $assets['XRP'] * $xrpPx;
    }

    return (float)$total;
}
?>

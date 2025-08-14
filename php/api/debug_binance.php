<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../exchange.php';

$result = [
    'serverTime' => null,
    'spot' => [
        'usdt' => null,
        'balances_count' => 0,
        'error' => null,
    ],
    'funding' => [
        'usdt' => null,
        'error' => null,
    ],
    'capital_config' => [
        'usdt_total' => null,
        'found' => false,
        'error' => null,
    ],
    'futures_um' => [
        'usdt' => null,
        'error' => null,
    ],
    'margin' => [
        'usdt' => null,
        'error' => null,
    ],
    'restrictions' => [
        'canRead' => null,
        'enableFutures' => null,
        'enableMargin' => null,
        'ipRestrict' => null,
        'error' => null,
    ],
];

try {
    $result['serverTime'] = binance_server_time();
} catch (Throwable $e) {
    // ignore
}

// 1) 스팟 계정
try {
    $spot = binance_signed_get_json('/api/v3/account');
    if (isset($spot['balances']) && is_array($spot['balances'])) {
        $result['spot']['balances_count'] = count($spot['balances']);
        foreach ($spot['balances'] as $bal) {
            if (isset($bal['asset']) && strtoupper($bal['asset']) === 'USDT') {
                $free = (float)($bal['free'] ?? 0);
                $locked = (float)($bal['locked'] ?? 0);
                $result['spot']['usdt'] = $free + $locked;
                break;
            }
        }
    }
} catch (Throwable $e) {
    $result['spot']['error'] = $e->getMessage();
}

// 펀딩
try {
    $fund = binance_sapi_post('/sapi/v1/asset/getFundingAsset', ['asset' => 'USDT', 'needBtcValuation' => false]);
    if (is_array($fund)) {
        foreach ($fund as $row) {
            if (strtoupper($row['asset'] ?? '') === 'USDT') {
                $free = (float)($row['free'] ?? 0);
                $locked = (float)($row['locked'] ?? 0);
                $freeze = (float)($row['freeze'] ?? 0);
                $withdrawing = (float)($row['withdrawing'] ?? 0);
                $result['funding']['usdt'] = $free + $locked + $freeze + $withdrawing;
                break;
            }
        }
    }
} catch (Throwable $e) {
    $result['funding']['error'] = $e->getMessage();
}

// 2) 자산 구성(펀딩 포함)
try {
    $coins = binance_signed_get_json('/sapi/v1/capital/config/getall');
    if (is_array($coins)) {
        foreach ($coins as $coin) {
            if (isset($coin['coin']) && strtoupper($coin['coin']) === 'USDT') {
                $free = (float)($coin['free'] ?? 0);
                $locked = (float)($coin['locked'] ?? 0);
                $freeze = (float)($coin['freeze'] ?? 0);
                $withdrawing = (float)($coin['withdrawing'] ?? 0);
                $ipoable = (float)($coin['ipoable'] ?? 0);
                $ipoing = (float)($coin['ipoing'] ?? 0);
                $result['capital_config']['usdt_total'] = $free + $locked + $freeze + $withdrawing + $ipoable + $ipoing;
                $result['capital_config']['found'] = true;
                break;
            }
        }
    }
} catch (Throwable $e) {
    $result['capital_config']['error'] = $e->getMessage();
}

// 선물(USD-M)
try {
    $fapi = binance_fapi_get('/fapi/v2/balance');
    if (is_array($fapi)) {
        foreach ($fapi as $row) {
            if (strtoupper($row['asset'] ?? '') === 'USDT') {
                $result['futures_um']['usdt'] = (float)($row['balance'] ?? 0);
                break;
            }
        }
    }
} catch (Throwable $e) {
    $result['futures_um']['error'] = $e->getMessage();
}

// 마진
try {
    $margin = binance_sapi_get('/sapi/v1/margin/account');
    if (isset($margin['userAssets'])) {
        foreach ($margin['userAssets'] as $a) {
            if (strtoupper($a['asset'] ?? '') === 'USDT') {
                $free = (float)($a['free'] ?? 0);
                $locked = (float)($a['locked'] ?? 0);
                $borrowed = (float)($a['borrowed'] ?? 0);
                $interest = (float)($a['interest'] ?? 0);
                $result['margin']['usdt'] = ($free + $locked) - ($borrowed + $interest);
                break;
            }
        }
    }
} catch (Throwable $e) {
    $result['margin']['error'] = $e->getMessage();
}

// 3) API 키 권한/제한
try {
    $restrictions = binance_signed_get_json('/sapi/v1/account/apiRestrictions');
    $result['restrictions']['canRead'] = $restrictions['enableReading'] ?? null;
    $result['restrictions']['enableFutures'] = $restrictions['enableFutures'] ?? null;
    $result['restrictions']['enableMargin'] = $restrictions['enableMargin'] ?? null;
    $result['restrictions']['ipRestrict'] = $restrictions['ipRestrict'] ?? null;
} catch (Throwable $e) {
    $result['restrictions']['error'] = $e->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);

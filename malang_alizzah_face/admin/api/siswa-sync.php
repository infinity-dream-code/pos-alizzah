<?php

declare(strict_types=1);

/**
 * Tarik daftar siswa dari MobileMerchant (StudentRequest) → simpan ke MySQL.
 * POST atau GET
 */

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/MobileMerchantClient.php';
require_once __DIR__ . '/lib/SiswaRepository.php';

api_send_cors();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $client = MobileMerchantClient::create();
    $raw = $client->studentRequest();

    $rows = [];
    if (is_array($raw)) {
        if (isset($raw['datas']) && is_array($raw['datas'])) {
            $rows = $raw['datas'];
        } elseif (isset($raw[0])) {
            $rows = $raw;
        }
    }

    $repo = new SiswaRepository();
    $stats = $repo->syncFromMerchant($rows);

    api_json([
        'ok' => true,
        'sync' => $stats,
        'data' => $repo->listAllLight(),
    ]);
} catch (Throwable $e) {
    api_json(['ok' => false, 'error' => $e->getMessage()], 502);
}

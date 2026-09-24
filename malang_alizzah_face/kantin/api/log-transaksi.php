<?php

declare(strict_types=1);

/**
 * LogTransaksiRequest — USERNAME dari session kantin.
 * GET
 */

require_once __DIR__ . '/bootstrap.php';

kantin_send_headers();
kantin_handle_options();
$user = KantinAuth::requireUser();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    kantin_api_json(['ok' => false, 'error' => 'Gunakan GET'], 405);
}

try {
    $username = (string) $user['username'];
    if ($username === '') {
        kantin_api_json(['ok' => false, 'error' => 'Session kantin tidak valid'], 401);
    }

    $client = MobileMerchantClient::create();
    $raw = $client->call([
        'METHOD' => 'LogTransaksiRequest',
        'USERNAME' => $username,
    ]);

    $rows = [];
    if (is_array($raw)) {
        if (isset($raw['datas']) && is_array($raw['datas'])) {
            $rows = $raw['datas'];
        } elseif (isset($raw[0]) && is_array($raw[0])) {
            $rows = $raw;
        }
    }

    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $out[] = [
            'namaCust' => (string) ($row['NamaCust'] ?? $row['namaCust'] ?? ''),
            'trxDate' => (string) ($row['TRXDATE'] ?? $row['trxDate'] ?? ''),
            'kantin' => (string) ($row['KANTIN'] ?? $row['kantin'] ?? ''),
            'nominal' => (string) ($row['Nominal'] ?? $row['nominal'] ?? '0'),
            'ket' => (string) ($row['KET'] ?? $row['Ket'] ?? $row['keterangan'] ?? $row['Keterangan'] ?? $row['KETERANGAN'] ?? ''),
        ];
    }

    kantin_api_json(['ok' => true, 'data' => $out]);
} catch (Throwable $e) {
    kantin_api_json(['ok' => false, 'error' => $e->getMessage()], 502);
}

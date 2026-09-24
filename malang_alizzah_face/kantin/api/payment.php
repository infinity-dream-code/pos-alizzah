<?php

declare(strict_types=1);

/**
 * PaymentBELANJAKantinWithKeterangan — pengurangan saldo + deskripsi barang.
 * NAMAKANTIN dari session (bukan body client).
 * POST: { "nokartu": "...", "nominal": 100, "ket": "Nasi goreng" }
 */

require_once __DIR__ . '/bootstrap.php';

kantin_send_headers();
kantin_handle_options();
$user = KantinAuth::requireUser();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kantin_api_json(['ok' => false, 'error' => 'Gunakan POST'], 405);
}

try {
    $body = api_read_json_body();
    $noKartu = preg_replace('/\D/', '', (string) ($body['nokartu'] ?? $body['NOKARTU'] ?? ''));
    $nominal = $body['nominal'] ?? $body['NOMINAL'] ?? null;
    $ketRaw = $body['ket'] ?? $body['KET'] ?? $body['keterangan'] ?? '';

    if ($noKartu === '') {
        kantin_api_json(['ok' => false, 'error' => 'nokartu wajib'], 400);
    }

    if ($nominal === null || $nominal === '' || !is_numeric($nominal)) {
        kantin_api_json(['ok' => false, 'error' => 'nominal tidak valid'], 400);
    }

    $nominalInt = (int) $nominal;
    if ($nominalInt <= 0) {
        kantin_api_json(['ok' => false, 'error' => 'nominal harus lebih dari 0'], 400);
    }
    if ($nominalInt > 999999999) {
        kantin_api_json(['ok' => false, 'error' => 'nominal terlalu besar'], 400);
    }

    $ket = trim((string) $ketRaw);
    $ket = preg_replace('/\s+/u', ' ', $ket) ?? $ket;
    if (function_exists('mb_substr')) {
        $ket = mb_substr($ket, 0, 60, 'UTF-8');
    } else {
        $ket = substr($ket, 0, 60);
    }
    $ket = trim($ket);
    if ($ket === '') {
        kantin_api_json(['ok' => false, 'error' => 'Keterangan barang wajib diisi (maks. 60 karakter)'], 400);
    }

    $namaKantin = (string) $user['username'];
    if ($namaKantin === '') {
        kantin_api_json(['ok' => false, 'error' => 'Session kantin tidak valid'], 401);
    }

    $noKartuNum = is_numeric($noKartu) ? (int) $noKartu : $noKartu;

    $client = MobileMerchantClient::create();
    $raw = $client->call([
        'METHOD' => 'PaymentBELANJAKantinWithKeterangan',
        'NOKARTU' => $noKartuNum,
        'NOMINAL' => $nominalInt,
        'NAMAKANTIN' => $namaKantin,
        'KET' => $ket,
    ]);

    kantin_api_json([
        'ok' => true,
        'data' => [
            'nokartu' => (string) $noKartuNum,
            'nominal' => $nominalInt,
            'ket' => $ket,
            'namaKantin' => $namaKantin,
            'raw' => $raw,
        ],
    ]);
} catch (Throwable $e) {
    kantin_api_json(['ok' => false, 'error' => $e->getMessage()], 502);
}

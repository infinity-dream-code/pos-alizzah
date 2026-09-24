<?php

return [
    /*
    | MobileMerchant Malang_Alizzah_ForVPS — FacePay (NOKARTU = NIS)
    | Private: http://10.99.23.111/...  Public: http://103.23.103.43/...
    | JWT secret = MD5('FarrelGantengSekali') di Malang_Alizzah_ForVPS/index.php
    */
    'api_url' => env(
        'MALANG_ALIZZAH_API_URL',
        'http://10.99.23.111/MobileMerchant/Malang_Alizzah_ForVPS/index.php'
    ),
    'jwt_secret' => env('MALANG_ALIZZAH_JWT_SECRET', '4ecfd4c24aee85b4b485f9d828aa1b7d'),
    'timeout' => (int) env('MALANG_ALIZZAH_TIMEOUT', 30),

    /**
     * NAMAKANTIN untuk PaymentBELANJA*.
     * Kosong = pakai username user kasir yang login.
     */
    'nama_kantin' => env('MALANG_ALIZZAH_NAMA_KANTIN', ''),
];

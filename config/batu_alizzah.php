<?php

return [
    /*
    | MobileMerchant Batu_Alizzah — belanja RFID
    | URL: http://10.99.23.18/MobileMerchant/Batu_Alizzah/index.php?token=
    | JWT secret = MD5('FarrelGantengSekali') di Batu_Alizzah/index.php
    */
    'api_url' => env(
        'BATU_ALIZZAH_API_URL',
        'http://10.99.23.18/MobileMerchant/Batu_Alizzah/index.php'
    ),
    'jwt_secret' => env('BATU_ALIZZAH_JWT_SECRET', '27576a43cc88a0abbc5a6a509b51be9c'),
    'timeout' => (int) env('BATU_ALIZZAH_TIMEOUT', 30),

    /**
     * NAMAKANTIN untuk PaymentBELANJA*.
     * Kosong = pakai username user kasir yang login.
     */
    'nama_kantin' => env('BATU_ALIZZAH_NAMA_KANTIN', ''),
];

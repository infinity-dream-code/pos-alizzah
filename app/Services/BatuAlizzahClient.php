<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client MobileMerchant Batu_Alizzah (RFID belanja).
 * METHOD: InquirySALDO, PaymentBELANJAKantin, PaymentBELANJAKantinPIN
 */
class BatuAlizzahClient
{
    private string $apiUrl;
    private string $jwtSecret;
    private int $timeout;

    public function __construct(?array $config = null)
    {
        $config = $config ?? config('batu_alizzah', []);
        $this->apiUrl = rtrim((string) ($config['api_url'] ?? ''), '/');
        $this->jwtSecret = (string) ($config['jwt_secret'] ?? '');
        $this->timeout = (int) ($config['timeout'] ?? 30);
    }

    public static function make(): self
    {
        return new self();
    }

    public function namaKantin(): string
    {
        $fromConfig = trim((string) config('batu_alizzah.nama_kantin', ''));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        $user = Auth::user();
        $username = $user ? trim((string) ($user->username ?? '')) : '';
        if ($username === '') {
            throw new RuntimeException('NAMAKANTIN tidak tersedia (username kasir kosong).');
        }

        return $username;
    }

    /**
     * @return array{ok:bool,nama:?string,saldo:int,raw:mixed,error:?string}
     */
    public function inquirySaldo(string $noKartu): array
    {
        $raw = $this->call([
            'METHOD' => 'InquirySALDO',
            'NOKARTU' => $noKartu,
        ]);

        $row = $this->firstRow($raw);
        $status = strtoupper((string) ($row['STATUS'] ?? ''));

        if ($status === 'OK') {
            return [
                'ok' => true,
                'nama' => (string) ($row['NAMA'] ?? ''),
                'saldo' => (int) ($row['SALDO'] ?? 0),
                'raw' => $raw,
                'error' => null,
            ];
        }

        return [
            'ok' => false,
            'nama' => (string) ($row['NAMA'] ?? 'Kartu tidak terdaftar'),
            'saldo' => (int) ($row['SALDO'] ?? 0),
            'raw' => $raw,
            'error' => (string) ($row['NAMA'] ?? 'Kartu tidak terdaftar'),
        ];
    }

    /**
     * Debit belanja. Ada PIN → PaymentBELANJAKantinPIN, tanpa PIN → PaymentBELANJAKantin.
     *
     * @return array{ok:bool,nama:?string,saldo:?int,result:?string,message:string,raw:mixed}
     */
    public function paymentBelanja(string $noKartu, int $nominal, ?string $pin = null): array
    {
        $payload = [
            'METHOD' => ($pin !== null && $pin !== '')
                ? 'PaymentBELANJAKantinPIN'
                : 'PaymentBELANJAKantin',
            'NOKARTU' => $noKartu,
            'NOMINAL' => $nominal,
            'NAMAKANTIN' => $this->namaKantin(),
        ];

        if ($pin !== null && $pin !== '') {
            $payload['PIN'] = $pin;
        }

        $raw = $this->call($payload);
        $row = $this->firstRow($raw);
        $status = strtoupper((string) ($row['STATUS'] ?? ''));

        if ($status === 'OK') {
            return [
                'ok' => true,
                'nama' => (string) ($row['NAMA'] ?? ''),
                'saldo' => isset($row['SALDO']) ? (int) $row['SALDO'] : null,
                'result' => null,
                'message' => 'Pembayaran berhasil',
                'raw' => $raw,
            ];
        }

        $result = (string) ($row['RESULT'] ?? 'Koneksi_Error');

        return [
            'ok' => false,
            'nama' => isset($row['NAMA']) ? (string) $row['NAMA'] : null,
            'saldo' => isset($row['SALDO']) && $row['SALDO'] !== '-' ? (int) $row['SALDO'] : null,
            'result' => $result,
            'message' => $this->mapResultMessage($result),
            'raw' => $raw,
        ];
    }

    public function mapResultMessage(string $result): string
    {
        return match (strtoupper($result)) {
            'SALDO_TAK_MENCUKUPI' => 'Saldo tidak mencukupi',
            'TRANSAKSI_LIMIT' => 'Limit transaksi harian terlampaui',
            'KARTU_TIDAK_TERDAFTAR' => 'Kartu tidak terdaftar atau diblokir',
            'KONEKSI_ERROR' => 'Koneksi ke server pembayaran gagal',
            default => $result !== '' ? $result : 'Pembayaran gagal',
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return mixed
     */
    public function call(array $payload)
    {
        if ($this->apiUrl === '' || $this->jwtSecret === '') {
            throw new RuntimeException('Konfigurasi Batu_Alizzah belum lengkap (api_url / jwt_secret).');
        }

        $token = $this->signJwt($payload);
        $url = $this->apiUrl . '?token=' . rawurlencode($token);

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->get($url);

        if (!$response->successful()) {
            throw new RuntimeException('HTTP ' . $response->status() . ' dari Batu_Alizzah');
        }

        $json = $response->json();
        if ($json === null && $response->body() !== '') {
            throw new RuntimeException('Respons Batu_Alizzah bukan JSON valid');
        }

        return $json;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function signJwt(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            $this->b64url(json_encode($header, JSON_UNESCAPED_UNICODE)),
            $this->b64url(json_encode($payload, JSON_UNESCAPED_UNICODE)),
        ];
        $input = implode('.', $segments);
        $segments[] = $this->b64url(hash_hmac('sha256', $input, $this->jwtSecret, true));

        return implode('.', $segments);
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private function firstRow($raw): array
    {
        if (is_array($raw) && isset($raw[0]) && is_array($raw[0])) {
            return $raw[0];
        }
        if (is_array($raw)) {
            return $raw;
        }

        return [];
    }
}

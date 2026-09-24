<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client MobileMerchant Malang_Alizzah_ForVPS (FacePay / NIS).
 * METHOD: InquirySALDO, PaymentBELANJAKantinWithKeterangan
 */
class MalangAlizzahClient
{
    private string $apiUrl;
    private string $jwtSecret;
    private int $timeout;

    public function __construct(?array $config = null)
    {
        $config = $config ?? config('malang_alizzah', []);
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
        $fromConfig = trim((string) config('malang_alizzah.nama_kantin', ''));
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
     * Debit belanja dengan keterangan (PaymentBELANJAKantinWithKeterangan).
     *
     * @return array{ok:bool,nama:?string,saldo:?int,result:?string,message:string,raw:mixed}
     */
    public function paymentBelanjaWithKeterangan(string $noKartu, int $nominal, string $ket): array
    {
        $ket = trim(preg_replace('/\s+/u', ' ', $ket) ?? $ket);
        if (function_exists('mb_substr')) {
            $ket = mb_substr($ket, 0, 60, 'UTF-8');
        } else {
            $ket = substr($ket, 0, 60);
        }
        $ket = trim($ket);

        if ($ket === '') {
            return [
                'ok' => false,
                'nama' => null,
                'saldo' => null,
                'result' => 'KET_KOSONG',
                'message' => 'Keterangan barang wajib diisi (maks. 60 karakter)',
                'raw' => null,
            ];
        }

        $raw = $this->call([
            'METHOD' => 'PaymentBELANJAKantinWithKeterangan',
            'NOKARTU' => is_numeric($noKartu) ? (int) $noKartu : $noKartu,
            'NOMINAL' => $nominal,
            'NAMAKANTIN' => $this->namaKantin(),
            'KET' => $ket,
        ]);

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
            throw new RuntimeException('Konfigurasi Malang_Alizzah belum lengkap (api_url / jwt_secret).');
        }

        $token = $this->signJwt($payload);
        $url = $this->apiUrl . '?token=' . rawurlencode($token);

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->get($url);

        if (!$response->successful()) {
            throw new RuntimeException('HTTP ' . $response->status() . ' dari Malang_Alizzah');
        }

        $json = $response->json();
        if ($json === null && $response->body() !== '') {
            throw new RuntimeException('Respons Malang_Alizzah bukan JSON valid');
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

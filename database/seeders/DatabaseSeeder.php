<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Barang;
use App\Models\Stok;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'nama' => 'Administrator',
            'username' => 'admin',
            'password' => Hash::make('admin123'),
            'role' => 'admin'
        ]);

        User::create([
            'nama' => 'Kasir Toko',
            'username' => 'kasir',
            'password' => Hash::make('kasir123'),
            'role' => 'kasir'
        ]);

        $year = date('Y');

        $barangList = [
            [
                'nama_barang' => 'Roti',
                'deskripsi' => 'Roti kantin',
                'harga_beli' => 3000,
                'harga_jual' => 5000,
                'stok' => 50
            ],
            [
                'nama_barang' => 'Burger',
                'deskripsi' => 'Burger kantin',
                'harga_beli' => 8000,
                'harga_jual' => 12000,
                'stok' => 30
            ],
            [
                'nama_barang' => 'Es Teh',
                'deskripsi' => 'Es teh manis segar',
                'harga_beli' => 2000,
                'harga_jual' => 4000,
                'stok' => 100
            ],
            [
                'nama_barang' => 'Air Mineral',
                'deskripsi' => 'Air mineral botol',
                'harga_beli' => 2000,
                'harga_jual' => 3500,
                'stok' => 120
            ],
            [
                'nama_barang' => 'Kopi',
                'deskripsi' => 'Kopi panas kantin',
                'harga_beli' => 2000,
                'harga_jual' => 5000,
                'stok' => 80
            ]
        ];

        $counter = 1;

        foreach ($barangList as $b) {
            $kode = $year . str_pad($counter, 4, '0', STR_PAD_LEFT);

            $barang = Barang::create([
                'kode_barang' => $kode,
                'nama_barang' => $b['nama_barang'],
                'deskripsi' => $b['deskripsi'],
                'harga_beli' => $b['harga_beli'],
                'harga_jual' => $b['harga_jual'],
                'stok' => $b['stok'],
                'status' => 'aktif'
            ]);

            Stok::create([
                'id_barang' => $barang->id,
                'id_user' => $admin->id,
                'stok' => $b['stok']
            ]);

            $counter++;
        }
    }
}

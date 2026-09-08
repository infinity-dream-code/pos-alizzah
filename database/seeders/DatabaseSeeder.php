<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Barang;
use App\Models\WaitingBarang;
use App\Models\Diskon;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        User::create([
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
        $barangIds = [];

        for ($i = 1; $i <= 500; $i++) {
            $kode = $year . str_pad($i, 4, '0', STR_PAD_LEFT);
            $harga_beli = rand(2, 20) * 1000;
            $harga_jual = $harga_beli + rand(1, 10) * 500;

            $barang = Barang::create([
                'kode_barang' => $kode,
                'nama_barang' => 'Barang ' . $i,
                'deskripsi' => 'Deskripsi barang ' . $i,
                'harga_beli' => $harga_beli,
                'harga_jual' => $harga_jual,
                'stok' => rand(5, 50),
                'status' => 'aktif'
            ]);

            $barangIds[] = $barang->id;
        }

        $randomBarangWaiting = array_slice($barangIds, 0, 100);

        foreach ($randomBarangWaiting as $id) {
            $harga_beli = rand(2, 20) * 1000;
            $harga_jual = $harga_beli + rand(1, 10) * 500;

            WaitingBarang::create([
                'barang_id' => $id,
                'kode_barang' => $year . str_pad($id, 4, '0', STR_PAD_LEFT),
                'stok' => rand(1, 20),
                'harga_beli' => $harga_beli,
                'harga_jual' => $harga_jual
            ]);
        }

        $randomBarangDiskon = array_slice($barangIds, 0, 300);

        foreach ($randomBarangDiskon as $id) {
            Diskon::create([
                'barang_id' => $id,
                'nilai' => rand(5, 30),
                'aktif' => true
            ]);
        }
    }
}

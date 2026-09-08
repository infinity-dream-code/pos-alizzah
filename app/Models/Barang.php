<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_category',
        'kode_barang',
        'nama_barang',
        'deskripsi',
        'harga_beli',
        'harga_jual',
        'stok',
        'status'
    ];

    public function detailTransaksi()
    {
        return $this->hasMany(DetailTransaksi::class, 'barang_id');
    }
    public function waitingBarang()
    {
        return $this->hasMany(WaitingBarang::class, 'barang_id');
    }

    public function diskon()
    {
        return $this->hasOne(Diskon::class)->where('aktif', 1);
    }

    public function stok()
    {
        return $this->hasOne(Stok::class, 'id_barang', 'id');
    }
}

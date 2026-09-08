<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaitingBarang extends Model
{
    use HasFactory;

    protected $table = 'waiting_barang';

    protected $fillable = [
        'barang_id',
        'kode_barang',
        'stok',
        'harga_beli',
        'harga_jual',
    ];

    public $timestamps = false;

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}

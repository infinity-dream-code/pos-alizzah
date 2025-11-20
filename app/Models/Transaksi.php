<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    protected $table = 'transaksi';

    protected $fillable = [
        'kode_transaksi',
        'tanggal',
        'total',
        'bayar',
        'kembalian',
        'metode',
        'pelanggan_id',
        'diskon_nominal',
        'grand_total',
        'profit',
        'user_id'
    ];

    protected $casts = [
        'tanggal' => 'datetime',
    ];

    public function detail()
    {
        return $this->hasMany(DetailTransaksi::class, 'transaksi_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

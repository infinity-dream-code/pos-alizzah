<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Diskon extends Model
{
    protected $fillable = ['barang_id', 'nilai', 'aktif'];

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $table = 'user';

    protected $fillable = [
        'username',
        'password',
        'role',
        'nama'
    ];

    protected $hidden = ['password'];

    public function transaksi()
    {
        return $this->hasMany(Transaksi::class, 'user_id');
    }

    public function stok()
    {
        return $this->hasMany(Stok::class, 'id_user', 'id');
    }
}

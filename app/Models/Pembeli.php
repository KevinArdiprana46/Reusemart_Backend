<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Pembeli extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'pembeli'; // nama tabel di database

    protected $primaryKey = 'id_pembeli'; // primary key custom
    public $incrementing = true; // jika auto-increment
    protected $keyType = 'int'; // tipe datanya (int, string, dsb)

    public $timestamps = false;

    protected $fillable = [
        'nama_lengkap',
        'gender',
        'email',
        'no_telepon',
        'password',
        'tanggal_lahir',
        'poin_sosial',
        'image_user',
        'id_role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}

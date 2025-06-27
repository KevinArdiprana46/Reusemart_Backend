<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Penitip extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;


    protected $table = 'penitip'; // nama tabel di database

    protected $primaryKey = 'id_penitip'; // primary key custom
    public $timestamps = false;

    protected $fillable = [
        'badge',
        'poin_sosial',
        'rating_penitip',
        'nama_lengkap',
        'alamat_penitip',
        'no_telepon',
        'email',
        'password',
        'gender',
        'tanggal_lahir',
        'saldo',
        'nominal_tarik',
        'komisi',
        'bonus',
        'image_user',
        'id_role',
        'foto_ktp',
        'no_ktp',
        'fcm_token',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role');
    }

    public function penitipan()
    {
        return $this->hasMany(Penitipan::class, 'id_penitip');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Penitip extends Model
{
    use HasApiTokens, HasFactory, Notifiable;


    protected $table = 'penitip'; // nama tabel di database

    protected $primaryKey = 'id_penitip'; // primary key custom
    public $timestamps = false;

    protected $fillable = [
        'id_alamat',
        'badge',
        'poin_sosial',
        'nama_lengkap',
        'no_telepon',
        'email',
        'password',
        'gender',
        'tanggal_lahir',
        'komisi',
        'bonus',
        'image_user',
        'id_role',
        'foto_ktp',
        'no_ktp',
    ];

    protected $appends = ['image_user_url'];

    public function getImageUserUrlAttribute()
    {
        return $this->image_user ? asset('storage/' . $this->image_user) : null;
    }


    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role');
    }

    public function alamat()
    {
        return $this->belongsTo(Alamat::class, 'id_alamat');
    }
}

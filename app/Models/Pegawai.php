<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;

class Pegawai extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'pegawai';
    protected $primaryKey = 'id_pegawai';

    public $incrementing = true; // jika auto-increment
    protected $keyType = 'int'; // tipe datanya (int, string, dsb)

    public $timestamps = false;
    
    protected $fillable = [
        'id_jabatan',
        'id_role',
        'nama_lengkap',
        'alamat',
        'no_telepon',
        'email',
        'gender',
        'tanggal_lahir',
        'password',
        'komisi_hunter',
        'image_user',
        'KTP',
    ];

    protected $hidden = ['password'];

    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role');
    }

    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class, 'id_jabatan');
    }
}

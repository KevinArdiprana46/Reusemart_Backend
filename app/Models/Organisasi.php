<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Traits\SafeToArray;

class Organisasi extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable, SafeToArray;

    protected $table = 'organisasi';
    protected $primaryKey = 'id_organisasi';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'nama_organisasi',
        'nama_penerima',
        'no_telepon',
        'alamat',
        'email',
        'password',
        'id_role',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role');
    }
    public function donasi()
    {
        return $this->hasMany(Donasi::class, 'id_organisasi');
    }

}

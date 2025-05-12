<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Organisasi extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $table = 'organisasi';
    protected $primaryKey = 'id_organisasi';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true; 

    protected $fillable = [
        'nama_organisasi',
        'email',
        'no_telepon',
        'alamat',
        'nama_penerima',
        'id_role',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organisasi extends Model
{
    use HasFactory;

    protected $table = 'organisasi';
    protected $primaryKey = 'id_organisasi';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true; 

    protected $fillable = [
        'nama_organisasi',
        'no_telepon',
        'alamat',
        'nama_penerima',
        'id_role',
    ];
}

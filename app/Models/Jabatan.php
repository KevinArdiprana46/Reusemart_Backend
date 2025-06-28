<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\SafeToArray;

class Jabatan extends Model
{
    use SafeToArray;
    
    protected $table = 'jabatan';
    protected $primaryKey = 'id_jabatan';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'nama_jabatan'
    ];
}

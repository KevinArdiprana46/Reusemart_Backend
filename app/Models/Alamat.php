<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\SafeToArray;

class Alamat extends Model
{
    use HasFactory, SafeToArray;

    protected $table = 'alamat';
    protected $primaryKey = 'id_alamat';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'provinsi',
        'kelurahan',
        'kecamatan',
        'detail_alamat',
        'kode_pos',
        'id_pembeli',
        'created_at',
        'updated_at',
        'utama',
    ];

    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class, 'id_pembeli', 'id_pembeli');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\SafeToArray;

class Diskusi extends Model
{
    use SafeToArray;
    
    protected $table = 'diskusi';
    protected $primaryKey = 'id_diskusi';
    public $timestamps = true;

    protected $fillable = [
        'id_barang',
        'id_pembeli',
        'id_pegawai',
        'pesan_diskusi',
        'is_read',
        'created_at',
        'updated_at',
    ];

    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class, 'id_pembeli');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penitipan extends Model
{
    protected $table = 'penitipan';
    protected $primaryKey = 'id_penitipan';
    public $timestamps = false;

    protected $fillable = [
        'id_penitip',
        'id_barang',
        'tanggal_masuk',
        'tanggal_akhir',
        'batas_pengambilan',
        'status_perpanjangan',
        'saldo_penitip',
    ];

    // Relasi ke Penitip
    public function penitip()
    {
        return $this->belongsTo(Penitip::class, 'id_penitip');
    }

    // Relasi ke Barang
    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang');
    }

    
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DetailPenitipan extends Pivot
{
    
    protected $table = 'detailpenitipan';
    public $timestamps = false;

    protected $fillable = [
        'id_penitipan',
        'id_barang',
    ];

    // Relasi ke Penitipan
    public function penitipan()
    {
        return $this->belongsTo(Penitipan::class, 'id_penitipan');
    }

    // Relasi ke Barang
    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang');
    }
}

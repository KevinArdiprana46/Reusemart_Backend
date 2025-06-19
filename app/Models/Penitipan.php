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
        'id_pegawai',
        'id_qc',
        'tanggal_masuk',
        'tanggal_akhir',
        'batas_pengambilan',
        'status_perpanjangan',
    ];

    // Relasi ke Penitip
    public function penitip()
    {
        return $this->belongsTo(Penitip::class, 'id_penitip');
    }

    // Relasi ke Barang
    public function barang()
    {
        return $this->belongsToMany(Barang::class, 'detailpenitipan', 'id_penitipan', 'id_barang');
    }

    public function detailpenitipan()
    {
        return $this->hasMany(DetailPenitipan::class, 'id_penitipan');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function pegawaiqc()
    {
        return $this->belongsTo(Pegawai::class, 'id_qc');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use HasFactory;
    protected $table = 'barang';
    protected $primaryKey = 'id_barang';
    public $timestamps = false;

    protected $fillable = [
        'nama_barang',
        'deskripsi',
        'kategori_barang',
        'harga_barang',
        'status_barang',
        'stock',
        'tanggal_garansi'
    ];

    // Relasi dengan Pegawai (misalnya)
    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    //Relasi Dengan Penitip
    public function penitip()
    {
        return $this->belongsTo(Penitip::class, 'id_penitip');
    }

    public function donasi()
    {
        return $this->belongsTo(Donasi::class, 'id_barang');
    }

    // public function diskusi()
    // {
    //     return $this->belongsTo(Diskusi::class, 'id_barang');
    // }



    // public function penitipan()
    // {
    //     return $this->belongsTo(Penitipan::class, 'id_barang');
    // }

    public function detailTransaksi()
    {
        return $this->belongsTo(DetailTransaksi::class, 'id_barang');
    }

    public function foto_barang()
    {
        return $this->hasMany(FotoBarang::class, 'id_barang');
    }
}

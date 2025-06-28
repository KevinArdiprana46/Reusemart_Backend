<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\SafeToArray;

class Barang extends Model
{
    use HasFactory, SafeToArray;
    protected $table = 'barang';
    protected $primaryKey = 'id_barang';
    public $timestamps = false;

    protected $fillable = [
        'id_pegawai',
        'nama_barang',
        'deskripsi',
        'kategori_barang',
        'harga_barang',
        'berat_barang',
        'status_barang',
        'stock',
        'rating_barang',
        'tanggal_garansi',
        'created_at'
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function penitipan()
    {
        return $this->belongsToMany(Penitipan::class, 'detailpenitipan', 'id_barang', 'id_penitipan');
    }

    public function donasi()
    {
        return $this->belongsTo(Donasi::class, 'id_barang');
    }

    public function diskusi()
    {
        return $this->hasMany(\App\Models\Diskusi::class, 'id_barang', 'id_barang');
    }

    public function detailPenitipan()
    {
        return $this->hasOne(DetailPenitipan::class, 'id_barang');
    }

    public function detailTransaksi()
    {
        return $this->hasOne(DetailTransaksi::class, 'id_barang');
    }

    public function foto_barang()
    {
        return $this->hasMany(FotoBarang::class, 'id_barang');
    }

    // public function penitip()
    // {
    //     return $this->belongsTo(Penitip::class, 'detailpenitipan', 'id_barang', 'id_penitip')->limit(1);
    // }

}

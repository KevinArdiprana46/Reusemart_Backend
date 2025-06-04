<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchandise extends Model
{
     use HasFactory;
    protected $table = 'merchandise';
    protected $primaryKey = 'id_merchandise';
    public $timestamps = false;

    protected $fillable =[
        'id_merchandise',
        'id_pegawai',
        'nama_merchandise',
        'kategori',
        'stock',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function FotoMerchandise()
    {
        return $this->hasMany(FotoMerchandise::class, 'id_merchandise');
    }
}

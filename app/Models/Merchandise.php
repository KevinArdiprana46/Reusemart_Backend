<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\SafeToArray;

class Merchandise extends Model
{
     use HasFactory, SafeToArray;
    protected $table = 'merchandise';
    protected $primaryKey = 'id_merchandise';
    public $timestamps = false;

    protected $fillable =[
        'id_merchandise',
        'id_pegawai',
        'nama_merchandise',
        'poin_penukaran',
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

    public function klaim()
    {
        return $this->hasMany(KlaimMerchandise::class, 'id_merchandise');
    }
}

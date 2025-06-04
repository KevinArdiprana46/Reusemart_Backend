<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KlaimMerchandise extends Model
{
    protected $table = 'klaim_merchandise';
    protected $primaryKey = 'id_klaim';
    public $timestamps = false;
    
    protected $fillable = [
        'id_merchandise',
        'id_pembeli',
        'tanggal_klaim',
    ];

    public function merchandise()
    {
        return $this->belongsTo(Merchandise::class, 'id_merchandise');
    }

    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class, 'id_pembeli');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Barang;
use App\Traits\SafeToArray;

class Keranjang extends Model
{
    use HasFactory;

    protected $table = 'keranjang';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_pembeli',
        'id_barang',
        'jumlah',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }

    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class, 'id_pembeli', 'id_pembeli');
    }
}

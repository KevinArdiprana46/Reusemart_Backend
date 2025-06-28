<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\SafeToArray;

class DetailTransaksi extends Model
{
    use SafeToArray;
    
    protected $table = 'detailtransaksi';
    protected $primaryKey = 'id_detail';
    public $timestamps = false;

    protected $fillable = ['id_transaksi', 'id_barang', 'jumlah', 'bonus_penitip'];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang');
    }

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi');
    }
}

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaksi extends Model
{
    use HasFactory;
    public $timestamps = true;
    protected $table = 'transaksi';
    protected $primaryKey = 'id_transaksi';
    protected $fillable = [
        'id_pembeli',
        'id_penitip',
        'id_pegawai',
        'status_transaksi',
        'jenis_pengiriman',
        'biaya_pengiriman',
        'nama_pengirim',
        'tanggal_pengambilan',
        'total_pembayaran',
        'nomor_nota',
        'bukti_pembayaran',
        'tanggal_pelunasan',
        'poin_reward',
        'poin_digunakan',
        'komisi_reusemart',
        'tanggal_dikirim',
    ];

    // Relasi ke pembeli
    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class, 'id_pembeli');
    }

    // Relasi ke penitip
    public function penitip()
    {
        return $this->belongsTo(Penitip::class, 'id_penitip');
    }

    public function detailtransaksi()
    {
        return $this->hasMany(DetailTransaksi::class, 'id_transaksi');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }


    public function penitipan()
    {
        return $this->belongsTo(Penitipan::class, 'id_penitipan');
    }

}

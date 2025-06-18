<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BarangExpired extends Command
{
    protected $signature = 'barang:mark-expired';
    protected $description = 'Menandai barang sebagai expired jika sudah melewati tanggal akhir tapi belum melewati batas pengambilan';

    public function handle()
    {
        $now = Carbon::now();

        // Ambil barang yang masih "tersedia", sudah lewat tanggal_akhir tapi belum melewati batas_pengambilan
        $affected = DB::table('barang')
            ->join('detailpenitipan', 'barang.id_barang', '=', 'detailpenitipan.id_barang')
            ->join('penitipan', 'detailpenitipan.id_penitipan', '=', 'penitipan.id_penitipan')
            ->where('barang.status_barang', 'tersedia')
            ->whereDate('penitipan.tanggal_akhir', '<', $now)
            ->whereDate('penitipan.batas_pengambilan', '>=', $now)
            ->update(['barang.status_barang' => 'expired']);

        $this->info("✅ Selesai: $affected barang berhasil ditandai sebagai 'expired'.");
    }
}

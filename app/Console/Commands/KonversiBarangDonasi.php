<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KonversiBarangDonasi extends Command
{
    protected $signature = 'barang:konversi-donasi';
    protected $description = 'Mengubah status barang menjadi "donasi" jika tidak diambil >7 hari setelah masa titip habis';

    public function handle()
    {
        $now = Carbon::now();

        // Ambil barang yang masih "tersedia" dan melewati batas pengambilan
        $affected = DB::table('barang')
            ->join('detailpenitipan', 'barang.id_barang', '=', 'detailpenitipan.id_barang')
            ->join('penitipan', 'detailpenitipan.id_penitipan', '=', 'penitipan.id_penitipan')
            ->where('barang.status_barang', 'expired')
            ->whereDate('penitipan.batas_pengambilan', '<', $now)
            ->update(['barang.status_barang' => 'donasi']);

        $this->info("✅ Selesai: $affected barang berhasil dikonversi menjadi 'donasi'.");
    }
}

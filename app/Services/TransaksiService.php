<?php

namespace App\Services;

use App\Models\Transaksi;
use App\Models\Barang;
use App\Models\Pembeli;
use Illuminate\Support\Facades\Log;

class TransaksiService
{
    public static function prosesHangusTransaksi()
    {
        Log::info("sheduler jalan");
        $transaksis = Transaksi::where('status_transaksi', 'belum bayar')
            ->where('created_at', '<', now()->subMinutes(1))
            ->get();

            Log::info("Ditemukan " . $transaksis->count() . " transaksi untuk dibatalkan.");


        foreach ($transaksis as $trx) {
            // Kembalikan poin ke pembeli
            $pembeli = $trx->pembeli;
            if ($pembeli) {
                $pembeli->poin_sosial += $trx->poin_digunakan ?? 0;
                $pembeli->save();
            }
            

            // Kembalikan stok barang
            foreach ($trx->detailtransaksi as $detail) {
                $barang = $detail->barang;
                if ($barang) {
                    $barang->stock += $detail->jumlah;
                    $barang->status_barang = 'tersedia';
                    $barang->save();
                }
            }

            // Ubah status
            $trx->status_transaksi = 'batal';
            $trx->save();

            Log::info("⏳ Transaksi dibatalkan otomatis: {$trx->id_transaksi}");
        }
    }
}

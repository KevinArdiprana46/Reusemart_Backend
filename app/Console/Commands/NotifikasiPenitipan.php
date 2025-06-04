<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DetailPenitipan;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NotifikasiPenitipan extends Command
{
    protected $signature = 'notifikasi:penitipan-h3-h';
    protected $description = 'Kirim notifikasi H-3 dan Hari-H masa penitipan ke penitip';

    public function handle()
    {
        $h3 = Carbon::now()->addDays(3)->toDateString();
        $hariH = Carbon::now()->toDateString();
        Log::info('⏰ Command notifikasi dijalankan pada: ' . now());
        $jadwalNotif = [
            $h3 => 'Sisa 3 hari sebelum masa penitipan barang Anda berakhir.',
            $hariH => 'Hari ini adalah hari terakhir masa penitipan barang Anda.',
        ];

        $penitipNotified = [];

        foreach ($jadwalNotif as $tanggal => $templatePesan) {
            $details = DetailPenitipan::with(['penitipan.penitip', 'barang'])
                ->whereHas('penitipan', function ($query) use ($tanggal) {
                    $query->whereDate('tanggal_akhir', $tanggal);
                })
                ->whereHas('barang', function ($q) {
                    $q->where('status_barang', 'tersedia');
                })
                ->get()
                ->groupBy(fn($d) => optional($d->penitipan)->id_penitip);

            foreach ($details as $id_penitip => $detailList) {
                $penitip = optional($detailList->first()->penitipan)->penitip;

                if ($penitip && $penitip->fcm_token) {
                    $namaBarangList = $detailList
                        ->map(fn($d) => optional($d->barang)->nama_barang)
                        ->filter()
                        ->implode(', ');

                    $pesan = "Barang Anda berikut ini: {$namaBarangList}, " .
                        ($tanggal === $h3
                            ? 'akan habis masa penitipannya dalam 3 hari.'
                            : 'hari ini adalah hari terakhir masa penitipannya.');

                    sendFCMWithJWT(
                        $penitip->fcm_token,
                        'Masa Penitipan Berakhir',
                        $pesan
                    );

                    Log::info("📨 Notifikasi '{$pesan}' dikirim ke penitip ID {$id_penitip} (tanggal: $tanggal)");
                    $penitipNotified[] = $id_penitip;
                }
            }
        }


        $this->info('✅ Notifikasi penitipan H-3 dan Hari-H dikirim. Total: ' . count($penitipNotified));
    }
}

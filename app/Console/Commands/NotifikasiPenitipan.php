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

        foreach ($jadwalNotif as $tanggal => $pesan) {
            $details = DetailPenitipan::with('penitipan.penitip')
                ->whereHas('penitipan', function ($query) use ($tanggal) {
                    $query->whereDate('tanggal_akhir', $tanggal); // ✅ AMAN
                })
                ->get();

            foreach ($details as $detail) {
                $penitip = optional($detail->penitipan)->penitip;

                if ($penitip && $penitip->fcm_token && !in_array($penitip->id_penitip, $penitipNotified)) {
                    sendFCMWithJWT(
                        $penitip->fcm_token,
                        'Masa Penitipan Berakhir',
                        $pesan
                    );

                    Log::info("📨 Notifikasi '{$pesan}' dikirim ke penitip ID {$penitip->id_penitip} (tanggal: $tanggal)");
                    $penitipNotified[] = $penitip->id_penitip;
                }
            }
        }

        $this->info('✅ Notifikasi penitipan H-3 dan Hari-H dikirim. Total: ' . count($penitipNotified));
    }
}

<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Services\TransaksiService;
use Illuminate\Support\Facades\Log;


class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
            Log::info("⏰ schedule() dipanggil dari Kernel");

        $schedule->call(function () {
            TransaksiService::prosesHangusTransaksi();
        })->everyMinute();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}


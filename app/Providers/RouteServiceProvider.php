<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

class RouteServiceProvider extends ServiceProvider
{
    public function boot()
    {
        parent::boot();

        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $email = $notifiable->getEmailForPasswordReset();
            return "http://reusemartgggeming.my.id/reset-password?token=$token&email=$email";
        });

        $this->routes(function () {
            Route::middleware('api')
                // Hilangkan prefix di sini
                ->group(base_path('routes/api.php'));
        });
    }
}

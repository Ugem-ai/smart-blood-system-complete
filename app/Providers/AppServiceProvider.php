<?php

namespace App\Providers;

use App\Events\EmergencyBloodRequestEvent;
use App\Listeners\SendEmergencyBloodRequestNotifications;
use App\Models\DonationHistory;
use App\Models\DonorRequestResponse;
use App\Observers\DonationHistoryObserver;
use App\Observers\DonorRequestResponseObserver;
use App\Services\EmergencyBroadcastModeService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only force HTTPS URL generation when the incoming request is already secure.
        // This prevents local/LAN HTTP runs from producing https://127.0.0.1 asset URLs.
        if (request()->isSecure() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Event::listen(EmergencyBloodRequestEvent::class, SendEmergencyBloodRequestNotifications::class);

        app(EmergencyBroadcastModeService::class)->warmCacheFromDatabase();

        // Auto-recalculate donor reliability score on every response or donation record change.
        DonorRequestResponse::observe(DonorRequestResponseObserver::class);
        DonationHistory::observe(DonationHistoryObserver::class);
    }
}

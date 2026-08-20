<?php

namespace App\Providers;

use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped, not shared: the active company is per request, and Octane or
        // a queue worker must never carry one request's company into the next.
        $this->app->scoped(CurrentCompany::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Codebooks are national or international standards shared by every
        // company, so they are read by all and maintained by administrators.
        Gate::define('manage-codebooks', fn (User $user) => $user->isAdmin());
    }
}

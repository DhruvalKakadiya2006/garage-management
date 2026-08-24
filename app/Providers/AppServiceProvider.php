<?php

namespace App\Providers;

use App\ImportExport\ImportRegistry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImportRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Surface missing eager loads as exceptions outside production — a
        // silent N+1 is the most common performance regression here.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Destructive actions (deletes, bulk-deletes, payment edits) are
        // reserved for admin users; regular staff may create and edit.
        Gate::define('admin', fn (User $user) => (bool) $user->is_admin);
    }
}

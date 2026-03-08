<?php

namespace ProAI\DataIntegrity;

use Illuminate\Support\ServiceProvider;

class DataIntegrityServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditCommand::class,
            ]);
        }
    }
}

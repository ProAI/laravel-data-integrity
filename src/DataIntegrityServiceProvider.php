<?php

namespace ProAI\DataIntegrity;

use Illuminate\Support\ServiceProvider;

class DataIntegrityServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditCommand::class,
            ]);
        }
    }
}

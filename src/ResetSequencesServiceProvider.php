<?php

namespace Dipesh79\PgResetSequences;

use Illuminate\Support\ServiceProvider;
use Dipesh79\PgResetSequences\Commands\ResetPostgresSequences;

class ResetSequencesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ResetPostgresSequences::class,
            ]);
        }
    }
}
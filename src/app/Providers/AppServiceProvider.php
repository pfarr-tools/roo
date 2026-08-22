<?php

namespace App\Providers;

use App\Documents\DocumentTemplateRegistry;
use App\Documents\Templates\PrimarySchoolAssessmentTemplate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DocumentTemplateRegistry::class, fn () => new DocumentTemplateRegistry([
            new PrimarySchoolAssessmentTemplate,
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone'));
    }
}

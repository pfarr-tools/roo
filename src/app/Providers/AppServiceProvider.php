<?php

namespace App\Providers;

use App\Documents\DocumentTemplateRegistry;
use App\Documents\Templates\ParentLetterTemplate;
use App\Documents\Templates\PrimarySchoolAssessmentTemplate;
use App\Services\AssessmentScan\DataMatrixDecoder;
use App\Services\AssessmentScan\DmtxReadDecoder;
use App\Services\AssessmentScan\PdfPageRenderer;
use App\Services\AssessmentScan\PopplerPdfPageRenderer;
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
            new ParentLetterTemplate,
        ]));
        $this->app->bind(DataMatrixDecoder::class, DmtxReadDecoder::class);
        $this->app->bind(PdfPageRenderer::class, PopplerPdfPageRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone'));
    }
}

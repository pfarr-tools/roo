<?php

use App\Http\Controllers\EducationPlanController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SchoolYearController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/schulen', [SchoolController::class, 'index'])->name('schools.index');
    Route::get('/bildungsplaene', [EducationPlanController::class, 'index'])->name('education-plans.index');
    Route::get('/bildungsplaene/{educationPlan}', [EducationPlanController::class, 'show'])->name('education-plans.show');
    Route::post('/bildungsplaene/{educationPlan}/kompetenzen/{competency}/status', [EducationPlanController::class, 'updateCompetencyStatus'])->name('education-plans.competencies.status');
    Route::post('/schulen', [SchoolController::class, 'store'])->name('schools.store');
    Route::put('/schulen/{school}', [SchoolController::class, 'update'])->name('schools.update');
    Route::get('/schuljahre/{schoolYear}', [SchoolYearController::class, 'show'])->name('school-years.show');
    Route::post('/schuljahre', [SchoolYearController::class, 'store'])->name('school-years.store');
    Route::put('/schuljahre/{schoolYear}', [SchoolYearController::class, 'update'])->name('school-years.update');
    Route::post('/schuljahre/{schoolYear}/ferien', [SchoolYearController::class, 'storeHoliday'])->name('school-years.holidays.store');
    Route::post('/schuljahre/{schoolYear}/ausnahmen', [SchoolYearController::class, 'storeException'])->name('school-years.exceptions.store');
    Route::post('/schuljahre/{schoolYear}/ferien/importieren', [SchoolYearController::class, 'importHolidays'])->name('school-years.holidays.import');
});

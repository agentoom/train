<?php

use App\Http\Controllers\ExportController;
use App\Livewire\Dashboard\Overview;
use App\Livewire\Datasets\DatasetDetail;
use App\Livewire\Datasets\DatasetForm;
use App\Livewire\Datasets\DatasetList;
use App\Livewire\Evaluation\EvaluationIndex;
use App\Livewire\Providers\ProviderForm;
use App\Livewire\Providers\ProviderList;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', Overview::class)->name('dashboard');

    Route::get('/providers', ProviderList::class)->name('providers.index');
    Route::get('/providers/create', ProviderForm::class)->name('providers.create');
    Route::get('/providers/{provider}/edit', ProviderForm::class)->name('providers.edit');

    Route::get('/datasets', DatasetList::class)->name('datasets.index');
    Route::get('/datasets/create', DatasetForm::class)->name('datasets.create');
    Route::get('/datasets/{project}', DatasetDetail::class)->name('datasets.show');
    Route::get('/datasets/{project}/edit', DatasetForm::class)->name('datasets.edit');
    Route::get('/datasets/{project}/export', ExportController::class)->name('datasets.export');

    Route::get('/evaluation', EvaluationIndex::class)->name('evaluation.index');
});

require __DIR__.'/settings.php';

Route::fallback(function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

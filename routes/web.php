<?php

use App\Http\Controllers\AwardeeController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ParseController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SearchController::class, 'index'])->name('search.index');
Route::get('/search/json', [SearchController::class, 'indexJson'])->name('search.indexJson');

Route::prefix('/monitoring')->group(function () {
    Route::get('/', [MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('/json', [MonitoringController::class, 'indexJson'])->name('monitoring.indexJson');
    Route::get('/json/distinct', [MonitoringController::class, 'distinctJson'])->name('monitoring.distinctJson');
    Route::post('/', [MonitoringController::class, 'store'])->name('monitoring.store');
    Route::post('/import', [MonitoringController::class, 'import'])->name('monitoring.import');
    Route::get('/export.csv', [MonitoringController::class, 'export'])->name('monitoring.export');
    Route::post('/export.csv', [MonitoringController::class, 'export']);
    Route::put('/{id}', [MonitoringController::class, 'update'])->name('monitoring.update');
    Route::delete('/{id}', [MonitoringController::class, 'destroy'])->name('monitoring.destroy');
    Route::delete('/', [MonitoringController::class, 'clear'])->name('monitoring.clear');
});

Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/json', [OrderController::class, 'indexJson'])->name('orders.indexJson');
Route::get('/orders/json/distinct', [OrderController::class, 'distinctJson'])->name('orders.distinctJson');
Route::get('/orders/{id}/awardees/json/distinct', [OrderController::class, 'showAwardeesDistinctJson'])->name('orders.awardeesDistinctJson');
Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
Route::get('/orders/{id}/awardees/json', [OrderController::class, 'showAwardeesJson'])->name('orders.awardeesJson');
Route::get('/orders/{id}/export.csv', [OrderController::class, 'export'])->name('orders.export');
Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->name('orders.destroy');
Route::patch('/orders/{id}/restore', [OrderController::class, 'restore'])->name('orders.restore');
Route::patch('/awardees/{awardee}', [AwardeeController::class, 'update'])->name('awardees.update');

Route::get('/parse', [ParseController::class, 'index'])->name('parse.index');
Route::post('/parse', [ParseController::class, 'store'])->name('parse.store');
Route::delete('/parse/database', [ParseController::class, 'clearDatabase'])->name('parse.clear');

<?php

use App\Http\Controllers\AwardeeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ParseController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SearchController::class, 'index'])->name('search.index');

Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
Route::get('/orders/{id}/export.csv', [OrderController::class, 'export'])->name('orders.export');
Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->name('orders.destroy');
Route::patch('/orders/{id}/restore', [OrderController::class, 'restore'])->name('orders.restore');
Route::patch('/awardees/{awardee}', [AwardeeController::class, 'update'])->name('awardees.update');

Route::get('/parse', [ParseController::class, 'index'])->name('parse.index');
Route::post('/parse', [ParseController::class, 'store'])->name('parse.store');

<?php

use App\Http\Controllers\StatisticsDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', StatisticsDashboardController::class)->name('home');

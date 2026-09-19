<?php

use App\Http\Controllers\PublicScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicScheduleController::class)->name('public.schedule');

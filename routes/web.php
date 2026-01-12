<?php

use App\Http\Controllers\GalaxyDebugController;
use Illuminate\Support\Facades\Route;

Route::get('/galaxy-debug', [GalaxyDebugController::class, 'index']);

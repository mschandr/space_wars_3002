<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GalaxyDebugController;

Route::get('/galaxy-debug', [GalaxyDebugController::class, 'index']);

<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class GalaxyDebugController extends Controller
{
    public function index()
    {
        return Inertia::render('GalaxyDebug');
    }
}

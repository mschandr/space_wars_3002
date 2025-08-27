<?php

namespace App\Http\Controllers;

use App\Models\Galaxy;
use GalaxyResource;
use Illuminate\Http\Request;

class GalaxyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return GalaxyResource::collection(Galaxy::all());
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $galaxy = Galaxy::create($request->all());
        return new GalaxyResource($galaxy);
    }

    /**
     * Display the specified resource.
     */
    public function show(Galaxy $galaxy)
    {
        return new GalaxyResource($galaxy);
    }

}

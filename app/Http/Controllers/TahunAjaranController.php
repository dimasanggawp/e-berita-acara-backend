<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TahunAjaranController extends Controller
{
    public function index()
    {
        return \App\Models\TahunAjaran::all();
    }
}

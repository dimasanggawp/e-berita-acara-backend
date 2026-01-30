<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TahunAjaranController extends Controller
{
    public function index()
    {
        return \App\Models\TahunAjaran::orderBy('id', 'desc')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tahun' => 'required|string|unique:tahun_ajarans,tahun',
            'is_active' => 'boolean'
        ]);

        return \App\Models\TahunAjaran::create($validated);
    }

    public function update(Request $request, $id)
    {
        $ta = \App\Models\TahunAjaran::findOrFail($id);

        $validated = $request->validate([
            'tahun' => 'required|string|unique:tahun_ajarans,tahun,' . $id,
            'is_active' => 'boolean'
        ]);

        $ta->update($validated);
        return $ta;
    }

    public function destroy($id)
    {
        $ta = \App\Models\TahunAjaran::findOrFail($id);
        $ta->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}

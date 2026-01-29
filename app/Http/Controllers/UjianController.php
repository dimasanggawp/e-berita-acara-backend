<?php

namespace App\Http\Controllers;

use App\Models\Ujian;
use Illuminate\Http\Request;

class UjianController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ujians = Ujian::orderBy('created_at', 'desc')->get();
        return response()->json($ujians);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_ujian' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $ujian = Ujian::create($validated);

        return response()->json([
            'message' => 'Data ujian berhasil ditambahkan',
            'data' => $ujian
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $ujian = Ujian::findOrFail($id);
        return response()->json($ujian);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $ujian = Ujian::findOrFail($id);

        $validated = $request->validate([
            'nama_ujian' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $ujian->update($validated);

        return response()->json([
            'message' => 'Data ujian berhasil diperbarui',
            'data' => $ujian
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $ujian = Ujian::findOrFail($id);
        $ujian->delete();

        return response()->json([
            'message' => 'Data ujian berhasil dihapus'
        ]);
    }
}

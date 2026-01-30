<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PesertaUjianController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return \App\Models\PesertaUjian::with(['ruang', 'kelas'])
            ->orderBy('nama', 'asc')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nisn' => 'required|string|unique:peserta_ujians,nisn',
            'nomor_peserta' => 'required|string|unique:peserta_ujians,nomor_peserta',
            'ruang_id' => 'required|exists:ruangs,id',
            'kelas_id' => 'required|exists:kelas,id',
        ]);

        return \App\Models\PesertaUjian::create($validated);
    }

    public function update(Request $request, string $id)
    {
        $peserta = \App\Models\PesertaUjian::findOrFail($id);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nisn' => 'required|string|unique:peserta_ujians,nisn,' . $id,
            'nomor_peserta' => 'required|string|unique:peserta_ujians,nomor_peserta,' . $id,
            'ruang_id' => 'required|exists:ruangs,id',
            'kelas_id' => 'required|exists:kelas,id',
        ]);

        $peserta->update($validated);
        return $peserta->load(['ruang', 'kelas']);
    }

    public function destroy(string $id)
    {
        $peserta = \App\Models\PesertaUjian::findOrFail($id);
        $peserta->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function meta()
    {
        return response()->json([
            'ruangs' => \App\Models\Ruang::all(),
            'kelases' => \App\Models\Kelas::all(),
        ]);
    }
}

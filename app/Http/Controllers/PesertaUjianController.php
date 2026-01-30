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
        return \App\Models\PesertaUjian::whereHas('jadwalUjians', function ($query) {
            $query->whereHas('ujian', function ($q) {
                $q->where('is_active', true);
            });
        })
            ->orderBy('nama', 'asc')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nisn' => 'required|string|unique:peserta_ujians,nisn',
            'nomor_peserta' => 'required|string|unique:peserta_ujians,nomor_peserta',
            'kelas' => 'required|string|max:255',
            'ruang' => 'nullable|string|max:255',
            'ujian_id' => 'required|exists:ujians,id',
        ]);

        $peserta = \App\Models\PesertaUjian::create($validated);

        // Associate with all schedules in that exam
        $schedules = \App\Models\JadwalUjian::where('ujian_id', $validated['ujian_id'])
            ->get();

        $peserta->jadwalUjians()->sync($schedules->pluck('id'));

        return $peserta;
    }

    public function update(Request $request, string $id)
    {
        $peserta = \App\Models\PesertaUjian::findOrFail($id);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nisn' => 'required|string|unique:peserta_ujians,nisn,' . $id,
            'nomor_peserta' => 'required|string|unique:peserta_ujians,nomor_peserta,' . $id,
            'kelas' => 'required|string|max:255',
            'ruang' => 'nullable|string|max:255',
            'ujian_id' => 'required|exists:ujians,id',
        ]);

        $peserta->update($validated);

        // Update associations
        $schedules = \App\Models\JadwalUjian::where('ujian_id', $validated['ujian_id'])
            ->get();

        $peserta->jadwalUjians()->sync($schedules->pluck('id'));

        return $peserta;
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
            'kelases' => \App\Models\Kelas::all(),
            'ujians' => \App\Models\Ujian::where('is_active', true)->get(),
        ]);
    }
}

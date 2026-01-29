<?php

namespace App\Http\Controllers;

use App\Models\JadwalUjian;
use Illuminate\Http\Request;

class JadwalUjianController extends Controller
{
    public function index()
    {
        return response()->json(
            JadwalUjian::with(['ujian', 'pengawas', 'ruang', 'mataPelajaran.kelas', 'sesi'])
                ->whereHas('ujian', function ($q) {
                    $q->where('is_active', true);
                })
                ->orderBy('mulai_ujian', 'desc')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ujian_id' => 'required|exists:ujians,id',
            'pengawas_id' => 'required|exists:pengawas,id',
            'ruang_id' => 'required|exists:ruangs,id',
            'mapel_id' => 'required|exists:mata_pelajarans,id',
            'sesi_id' => 'required|exists:sesis,id',
            'mulai_ujian' => 'required|date',
            'ujian_berakhir' => 'required|date|after:mulai_ujian',
            'total_siswa' => 'required|integer|min:1',
        ]);

        $jadwal = JadwalUjian::create($validated);

        return response()->json([
            'message' => 'Jadwal ujian berhasil ditambahkan',
            'data' => $jadwal->load(['ujian', 'pengawas', 'ruang', 'mataPelajaran.kelas', 'sesi'])
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $jadwal = JadwalUjian::findOrFail($id);

        $validated = $request->validate([
            'ujian_id' => 'required|exists:ujians,id',
            'pengawas_id' => 'required|exists:pengawas,id',
            'ruang_id' => 'required|exists:ruangs,id',
            'mapel_id' => 'required|exists:mata_pelajarans,id',
            'sesi_id' => 'required|exists:sesis,id',
            'mulai_ujian' => 'required|date',
            'ujian_berakhir' => 'required|date|after:mulai_ujian',
            'total_siswa' => 'required|integer|min:1',
        ]);

        $jadwal->update($validated);

        return response()->json([
            'message' => 'Jadwal ujian berhasil diperbarui',
            'data' => $jadwal->load(['ujian', 'pengawas', 'ruang', 'mataPelajaran.kelas', 'sesi'])
        ]);
    }

    public function destroy($id)
    {
        $jadwal = JadwalUjian::findOrFail($id);
        $jadwal->delete();

        return response()->json([
            'message' => 'Jadwal ujian berhasil dihapus'
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\JadwalUjian;
use Illuminate\Http\Request;

class JadwalUjianController extends Controller
{
    public function index()
    {
        return response()->json(
            JadwalUjian::with(['ujian', 'pengawas', 'mataPelajaran.kelas', 'sesi'])
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
            'mapel_id' => 'required|exists:mata_pelajarans,id',
            'sesi_id' => 'required|exists:sesis,id',
            'mulai_ujian' => 'required|date',
            'ujian_berakhir' => 'required|date|after:mulai_ujian',
            'total_siswa' => 'required|integer|min:1',
        ]);

        try {
            $jadwal = \DB::transaction(function () use ($validated) {
                // Check for existing schedule to prevent duplicates
                $existing = JadwalUjian::where('ujian_id', $validated['ujian_id'])
                    ->where('pengawas_id', $validated['pengawas_id'])
                    ->where('mapel_id', $validated['mapel_id'])
                    ->where('sesi_id', $validated['sesi_id'])
                    ->where('mulai_ujian', $validated['mulai_ujian'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw new \Exception('Jadwal dengan kombinasi yang sama sudah ada.');
                }

                return JadwalUjian::create($validated);
            });

            return response()->json([
                'message' => 'Jadwal ujian berhasil ditambahkan',
                'data' => $jadwal->load(['ujian', 'pengawas', 'mataPelajaran.kelas', 'sesi'])
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violation
            if ($e->getCode() == 23000) {
                return response()->json([
                    'message' => 'Jadwal dengan kombinasi yang sama sudah ada.'
                ], 409);
            }
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 409);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'ujian_id' => 'required|exists:ujians,id',
            'pengawas_id' => 'required|exists:pengawas,id',
            'mapel_id' => 'required|exists:mata_pelajarans,id',
            'sesi_id' => 'required|exists:sesis,id',
            'mulai_ujian' => 'required|date',
            'ujian_berakhir' => 'required|date|after:mulai_ujian',
            'total_siswa' => 'required|integer|min:1',
        ]);

        try {
            $jadwal = \DB::transaction(function () use ($id, $validated) {
                // Use pessimistic locking to prevent concurrent updates
                $jadwal = JadwalUjian::lockForUpdate()->findOrFail($id);
                $jadwal->update($validated);
                return $jadwal;
            });

            return response()->json([
                'message' => 'Jadwal ujian berhasil diperbarui',
                'data' => $jadwal->load(['ujian', 'pengawas', 'mataPelajaran.kelas', 'sesi'])
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violation
            if ($e->getCode() == 23000) {
                return response()->json([
                    'message' => 'Jadwal dengan kombinasi yang sama sudah ada.'
                ], 409);
            }
            throw $e;
        }
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

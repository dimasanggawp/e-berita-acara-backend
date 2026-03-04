<?php

namespace App\Http\Controllers;

use App\Models\LaporanUjian;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    /**
     * List all reports with eager-loaded relationships.
     */
    public function index(Request $request)
    {
        $query = LaporanUjian::with(['ujian', 'pengawas', 'kelas'])
            ->orderByDesc('created_at');

        if ($request->has('ujian_id') && $request->ujian_id) {
            $query->where('ujian_id', $request->ujian_id);
        }

        $reports = $query->get()->map(function ($report) {
            $jadwal = \App\Models\JadwalUjian::with(['ruang', 'mataPelajaran.kelas'])
                ->where('ujian_id', $report->ujian_id)
                ->where(function ($q) use ($report) {
                    $q->where('pengawas_id', $report->pengawas_id)
                        ->orWhere('pengawas_pengganti_id', $report->pengawas_id);
                })
                ->first();

            // Calculate kelas_name
            $kelasName = '-';
            if ($report->kelas) {
                $kelasName = $report->kelas->nama_kelas;
            } elseif ($jadwal && $jadwal->mataPelajaran && $jadwal->mataPelajaran->kelas) {
                $kelasName = $jadwal->mataPelajaran->kelas->nama_kelas;
            } else {
                // If it's a mixed class, check the peserta assigned
                $peserta = \App\Models\PesertaUjian::with('kelas')->where('ujian_id', $report->ujian_id)
                    ->where(function ($q) use ($jadwal) {
                        if ($jadwal?->ruang_id)
                            $q->where('ruang_id', $jadwal->ruang_id);
                        if ($jadwal?->sesi)
                            $q->where('sesi', $jadwal->sesi);
                    })->get();
                if ($peserta->isNotEmpty()) {
                    $kelasNames = $peserta->pluck('kelas')->unique()->filter();
                    if ($kelasNames->count() > 1) {
                        $kelasName = 'Gabungan (' . $kelasNames->count() . ' Kelas)';
                    } elseif ($kelasNames->count() === 1) {
                        $kelasName = $kelasNames->first();
                    }
                }
            }

            return [
                'id' => $report->id,
                'ujian' => $report->ujian,
                'pengawas' => $report->pengawas,
                'kelas' => $report->kelas,
                'kelas_name' => $kelasName,
                'nama_mapel' => $jadwal->nama_mapel ?? ($jadwal->mataPelajaran->nama_mapel ?? '-'),
                'ruang' => $jadwal->ruang->nama_ruang ?? '-',
                'kampus' => $jadwal->ruang->kampus ?? '-',
                'sesi' => $jadwal->sesi ?? '-',
                'mulai_ujian' => $report->mulai_ujian,
                'ujian_berakhir' => $report->ujian_berakhir,
                'total_expected' => $report->total_expected,
                'total_present' => $report->total_present,
                'total_absent' => $report->total_absent,
                'absent_details' => $report->absent_details,
                'notes' => $report->notes,
                'signature_path' => $report->signature_path,
                'signature_url' => $report->signature_path
                    ? asset('storage/' . $report->signature_path)
                    : null,
                'created_at' => $report->created_at,
            ];
        });

        return response()->json($reports);
    }

    /**
     * Get a single report by ID.
     */
    public function show(string $id)
    {
        $report = LaporanUjian::with(['ujian', 'pengawas', 'kelas'])->findOrFail($id);

        $jadwal = \App\Models\JadwalUjian::with(['ruang', 'mataPelajaran.kelas'])
            ->where('ujian_id', $report->ujian_id)
            ->where(function ($q) use ($report) {
                $q->where('pengawas_id', $report->pengawas_id)
                    ->orWhere('pengawas_pengganti_id', $report->pengawas_id);
            })
            ->first();

        // Calculate kelas_name
        $kelasName = '-';
        if ($report->kelas) {
            $kelasName = $report->kelas->nama_kelas;
        } elseif ($jadwal && $jadwal->mataPelajaran && $jadwal->mataPelajaran->kelas) {
            $kelasName = $jadwal->mataPelajaran->kelas->nama_kelas;
        } else {
            // Mixed class fallback
            $peserta = \App\Models\PesertaUjian::with('kelas')->where('ujian_id', $report->ujian_id)
                ->where(function ($q) use ($jadwal) {
                    if ($jadwal?->ruang_id)
                        $q->where('ruang_id', $jadwal->ruang_id);
                    if ($jadwal?->sesi)
                        $q->where('sesi', $jadwal->sesi);
                })->get();
            if ($peserta->isNotEmpty()) {
                $kelasNames = $peserta->pluck('kelas')->unique()->filter();
                if ($kelasNames->count() > 1) {
                    $kelasName = 'Gabungan (' . $kelasNames->count() . ' Kelas)';
                } elseif ($kelasNames->count() === 1) {
                    $kelasName = $kelasNames->first();
                }
            }
        }

        return response()->json([
            'id' => $report->id,
            'ujian' => $report->ujian,
            'pengawas' => $report->pengawas,
            'kelas' => $report->kelas,
            'kelas_name' => $kelasName,
            'nama_mapel' => $jadwal->nama_mapel ?? ($jadwal->mataPelajaran->nama_mapel ?? '-'),
            'ruang' => $jadwal->ruang->nama_ruang ?? '-',
            'kampus' => $jadwal->ruang->kampus ?? '-',
            'sesi' => $jadwal->sesi ?? '-',
            'mulai_ujian' => $report->mulai_ujian,
            'ujian_berakhir' => $report->ujian_berakhir,
            'total_expected' => $report->total_expected,
            'total_present' => $report->total_present,
            'total_absent' => $report->total_absent,
            'absent_details' => $report->absent_details,
            'notes' => $report->notes,
            'signature_path' => $report->signature_path,
            'signature_url' => $report->signature_path
                ? asset('storage/' . $report->signature_path)
                : null,
            'created_at' => $report->created_at,
        ]);
    }

    /**
     * Delete a report.
     */
    public function destroy(string $id)
    {
        $report = LaporanUjian::findOrFail($id);

        // Optionally delete the signature file if it exists
        if ($report->signature_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($report->signature_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($report->signature_path);
        }

        $report->delete();

        return response()->json(['message' => 'Laporan berhasil dihapus']);
    }
}

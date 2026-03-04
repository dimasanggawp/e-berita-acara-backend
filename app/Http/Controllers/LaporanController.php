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
            // Get the jadwal for this pengawas + ujian to extract ruang & sesi
            $jadwal = \App\Models\JadwalUjian::with('ruang')
                ->where('ujian_id', $report->ujian_id)
                ->where(function ($q) use ($report) {
                    $q->where('pengawas_id', $report->pengawas_id)
                        ->orWhere('pengawas_pengganti_id', $report->pengawas_id);
                })
                ->first();

            return [
                'id' => $report->id,
                'ujian' => $report->ujian,
                'pengawas' => $report->pengawas,
                'kelas' => $report->kelas,
                'nama_mapel' => $jadwal->nama_mapel ?? '-',
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

        $jadwal = \App\Models\JadwalUjian::with('ruang')
            ->where('ujian_id', $report->ujian_id)
            ->where(function ($q) use ($report) {
                $q->where('pengawas_id', $report->pengawas_id)
                    ->orWhere('pengawas_pengganti_id', $report->pengawas_id);
            })
            ->first();

        return response()->json([
            'id' => $report->id,
            'ujian' => $report->ujian,
            'pengawas' => $report->pengawas,
            'kelas' => $report->kelas,
            'nama_mapel' => $jadwal->nama_mapel ?? '-',
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
            'signature_url' => $report->signature_path
                ? asset('storage/' . $report->signature_path)
                : null,
            'created_at' => $report->created_at,
        ]);
    }
}

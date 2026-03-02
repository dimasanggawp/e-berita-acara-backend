<?php

namespace App\Http\Controllers;

use App\Models\PesertaUjian;
use App\Models\PresensiPeserta;
use App\Models\JadwalUjian;
use App\Models\Ujian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function attendanceStats(Request $request)
    {
        $ujianId = $request->query('ujian_id');
        $date = $request->query('date'); // Y-m-d format

        // Get all active ujians for the dropdown
        $ujians = Ujian::where('is_active', true)->orderByDesc('created_at')->get(['id', 'nama_ujian', 'is_active']);

        // If no ujian_id provided, use the first active one
        if (!$ujianId && $ujians->isNotEmpty()) {
            $activeUjian = $ujians->firstWhere('is_active', true) ?? $ujians->first();
            $ujianId = $activeUjian->id;
        }

        if (!$ujianId) {
            return response()->json([
                'ujians' => $ujians,
                'selected_ujian_id' => null,
                'total_students' => 0,
                'attended' => 0,
                'not_attended' => 0,
                'percentage' => 0,
                'available_dates' => [],
            ]);
        }

        // Total students registered for this ujian
        $totalStudents = PesertaUjian::where('ujian_id', $ujianId)->count();

        // Get available dates from jadwal_ujians for this ujian
        $availableDates = JadwalUjian::where('ujian_id', $ujianId)
            ->select(DB::raw('DATE(mulai_ujian) as exam_date'))
            ->distinct()
            ->orderBy('exam_date')
            ->pluck('exam_date')
            ->toArray();

        // Count attended students (unique kode_peserta in presensi_pesertas)
        $attendanceQuery = PresensiPeserta::where('ujian_id', $ujianId)
            ->whereNotNull('waktu_datang');

        if ($date) {
            $attendanceQuery->whereDate('waktu_datang', $date);
        }

        $attended = $attendanceQuery->distinct('kode_peserta')->count('kode_peserta');

        // For date-specific queries, also count total students assigned to that date's schedules
        $totalForContext = $totalStudents;
        if ($date) {
            // Count students assigned to jadwal on this date
            $jadwalIds = JadwalUjian::where('ujian_id', $ujianId)
                ->whereDate('mulai_ujian', $date)
                ->pluck('id');

            if ($jadwalIds->isNotEmpty()) {
                $totalForContext = DB::table('jadwal_peserta')
                    ->whereIn('jadwal_ujian_id', $jadwalIds)
                    ->distinct('peserta_ujian_id')
                    ->count('peserta_ujian_id');

                // If no students assigned via pivot, fallback to total
                if ($totalForContext === 0) {
                    $totalForContext = $totalStudents;
                }
            }
        }

        $notAttended = max(0, $totalForContext - $attended);
        $percentage = $totalForContext > 0 ? round(($attended / $totalForContext) * 100, 1) : 0;

        return response()->json([
            'ujians' => $ujians,
            'selected_ujian_id' => (int) $ujianId,
            'total_students' => $totalForContext,
            'attended' => $attended,
            'not_attended' => $notAttended,
            'percentage' => $percentage,
            'available_dates' => $availableDates,
        ]);
    }
}

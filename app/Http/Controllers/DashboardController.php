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

    public function attendanceByCampus(Request $request)
    {
        $ujianId = $request->query('ujian_id');
        $date = $request->query('date');

        if (!$ujianId) {
            return response()->json([]);
        }

        $campuses = ['Kampus 1', 'Kampus 2'];
        $results = [];

        foreach ($campuses as $campus) {
            // Get participants in this campus
            $participants = PesertaUjian::where('ujian_id', $ujianId)
                ->whereHas('ruang', function ($q) use ($campus) {
                    $q->where('kampus', $campus);
                });

            $total = $participants->count();

            // Get attendance for these participants
            $attendanceQuery = PresensiPeserta::where('ujian_id', $ujianId)
                ->whereIn('kode_peserta', function ($q) use ($ujianId, $campus) {
                    $q->select('nomor_peserta')
                        ->from('peserta_ujians')
                        ->where('ujian_id', $ujianId)
                        ->whereIn('ruang_id', function ($sq) use ($campus) {
                            $sq->select('id')->from('ruangs')->where('kampus', $campus);
                        });
                })
                ->whereNotNull('waktu_datang');

            if ($date) {
                $attendanceQuery->whereDate('waktu_datang', $date);
            }

            $attended = $attendanceQuery->distinct('kode_peserta')->count('kode_peserta');

            $results[] = [
                'kampus' => $campus,
                'total' => $total,
                'attended' => $attended,
                'not_attended' => max(0, $total - $attended),
                'percentage' => $total > 0 ? round(($attended / $total) * 100, 1) : 0,
            ];
        }

        return response()->json($results);
    }

    public function attendanceByClass(Request $request)
    {
        $ujianId = $request->query('ujian_id');
        $date = $request->query('date');
        $campus = $request->query('kampus');

        if (!$ujianId || !$campus) {
            return response()->json([]);
        }

        $classes = PesertaUjian::where('ujian_id', $ujianId)
            ->whereHas('ruang', function ($q) use ($campus) {
                $q->where('kampus', $campus);
            })
            ->select('kelas')
            ->distinct()
            ->pluck('kelas');

        $results = [];

        foreach ($classes as $kelas) {
            $participants = PesertaUjian::where('ujian_id', $ujianId)
                ->where('kelas', $kelas)
                ->whereHas('ruang', function ($q) use ($campus) {
                    $q->where('kampus', $campus);
                });

            $total = $participants->count();

            $attendanceQuery = PresensiPeserta::where('ujian_id', $ujianId)
                ->whereIn('kode_peserta', function ($q) use ($ujianId, $kelas, $campus) {
                    $q->select('nomor_peserta')
                        ->from('peserta_ujians')
                        ->where('ujian_id', $ujianId)
                        ->where('kelas', $kelas)
                        ->whereIn('ruang_id', function ($sq) use ($campus) {
                            $sq->select('id')->from('ruangs')->where('kampus', $campus);
                        });
                })
                ->whereNotNull('waktu_datang');

            if ($date) {
                $attendanceQuery->whereDate('waktu_datang', $date);
            }

            $attended = $attendanceQuery->distinct('kode_peserta')->count('kode_peserta');

            $results[] = [
                'kelas' => $kelas,
                'total' => $total,
                'attended' => $attended,
                'not_attended' => max(0, $total - $attended),
                'percentage' => $total > 0 ? round(($attended / $total) * 100, 1) : 0,
            ];
        }

        // Sort by percentage/name if needed
        usort($results, fn($a, $b) => $b['percentage'] <=> $a['percentage'] ?: $a['kelas'] <=> $b['kelas']);

        return response()->json($results);
    }

    public function attendanceStudents(Request $request)
    {
        $ujianId = $request->query('ujian_id');
        $date = $request->query('date');
        $kelas = $request->query('kelas');
        $campus = $request->query('kampus');

        if (!$ujianId || !$kelas || !$campus) {
            return response()->json([]);
        }

        $students = PesertaUjian::where('ujian_id', $ujianId)
            ->where('kelas', $kelas)
            ->whereHas('ruang', function ($q) use ($campus) {
                $q->where('kampus', $campus);
            })
            ->with(['ruang'])
            ->get();

        $results = $students->map(function ($student) use ($ujianId, $date) {
            $attendanceQuery = PresensiPeserta::where('ujian_id', $ujianId)
                ->where('kode_peserta', $student->nomor_peserta)
                ->whereNotNull('waktu_datang');

            if ($date) {
                $attendanceQuery->whereDate('waktu_datang', $date);
            }

            $presensi = $attendanceQuery->first();

            return [
                'id' => $student->id,
                'nama' => $student->nama,
                'nomor_peserta' => $student->nomor_peserta,
                'kelas' => $student->kelas,
                'nama_ruang' => $student->ruang->nama_ruang ?? '-',
                'waktu_datang' => $presensi ? $presensi->waktu_datang : null,
                'status' => $presensi ? 'Hadir' : 'Tidak Hadir',
            ];
        });

        return response()->json($results);
    }
}

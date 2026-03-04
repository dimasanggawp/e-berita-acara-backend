<?php

namespace App\Services;

use App\Models\PesertaUjian;
use App\Models\PresensiPeserta;
use App\Models\JadwalUjian;
use App\Models\Ujian;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get overall attendance statistics for an exam.
     */
    public function getAttendanceStats(?string $ujianId, ?string $date): array
    {
        $ujians = Ujian::where('is_active', true)->orderByDesc('created_at')->get(['id', 'nama_ujian', 'is_active']);

        if (!$ujianId && $ujians->isNotEmpty()) {
            $activeUjian = $ujians->firstWhere('is_active', true) ?? $ujians->first();
            $ujianId = $activeUjian->id;
        }

        if (!$ujianId) {
            return [
                'ujians' => $ujians,
                'selected_ujian_id' => null,
                'total_students' => 0,
                'attended' => 0,
                'not_attended' => 0,
                'percentage' => 0,
                'available_dates' => [],
            ];
        }

        $totalStudents = PesertaUjian::where('ujian_id', $ujianId)->count();

        $availableDates = JadwalUjian::where('ujian_id', $ujianId)
            ->select(DB::raw('DATE(mulai_ujian) as exam_date'))
            ->distinct()
            ->orderBy('exam_date')
            ->pluck('exam_date')
            ->toArray();

        $attendanceQuery = PresensiPeserta::where('ujian_id', $ujianId)
            ->whereNotNull('waktu_datang');

        if ($date) {
            $attendanceQuery->whereDate('waktu_datang', $date);
        }

        $attended = $attendanceQuery->distinct('kode_peserta')->count('kode_peserta');

        $totalForContext = $this->getTotalForContext($ujianId, $date, $totalStudents);

        $notAttended = max(0, $totalForContext - $attended);
        $percentage = $totalForContext > 0 ? round(($attended / $totalForContext) * 100, 1) : 0;

        return [
            'ujians' => $ujians,
            'selected_ujian_id' => (int) $ujianId,
            'total_students' => $totalForContext,
            'attended' => $attended,
            'not_attended' => $notAttended,
            'percentage' => $percentage,
            'available_dates' => $availableDates,
        ];
    }

    /**
     * Get attendance broken down by campus.
     */
    public function getAttendanceByCampus(?string $ujianId, ?string $date): array
    {
        if (!$ujianId) {
            return [];
        }

        $campuses = ['Kampus 1', 'Kampus 2'];
        $results = [];

        foreach ($campuses as $campus) {
            $total = PesertaUjian::where('ujian_id', $ujianId)
                ->whereHas('ruang', fn($q) => $q->where('kampus', $campus))
                ->count();

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

        return $results;
    }

    /**
     * Get attendance broken down by class within a campus.
     */
    public function getAttendanceByClass(?string $ujianId, ?string $date, ?string $campus): array
    {
        if (!$ujianId || !$campus) {
            return [];
        }

        $classes = PesertaUjian::where('ujian_id', $ujianId)
            ->whereHas('ruang', fn($q) => $q->where('kampus', $campus))
            ->select('kelas')
            ->distinct()
            ->pluck('kelas');

        $results = [];

        foreach ($classes as $kelas) {
            $total = PesertaUjian::where('ujian_id', $ujianId)
                ->where('kelas', $kelas)
                ->whereHas('ruang', fn($q) => $q->where('kampus', $campus))
                ->count();

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

        usort($results, fn($a, $b) => $b['percentage'] <=> $a['percentage'] ?: $a['kelas'] <=> $b['kelas']);

        return $results;
    }

    /**
     * Get individual student attendance data for a specific class and campus.
     */
    public function getAttendanceStudents(?string $ujianId, ?string $date, ?string $kelas, ?string $campus): array
    {
        if (!$ujianId || !$kelas || !$campus) {
            return [];
        }

        $students = PesertaUjian::where('ujian_id', $ujianId)
            ->where('kelas', $kelas)
            ->whereHas('ruang', fn($q) => $q->where('kampus', $campus))
            ->with(['ruang'])
            ->get();

        return $students->map(function ($student) use ($ujianId, $date) {
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
        })->toArray();
    }

    /**
     * Calculate total students for a specific date context.
     */
    private function getTotalForContext(string $ujianId, ?string $date, int $totalStudents): int
    {
        if (!$date) {
            return $totalStudents;
        }

        $jadwalIds = JadwalUjian::where('ujian_id', $ujianId)
            ->whereDate('mulai_ujian', $date)
            ->pluck('id');

        if ($jadwalIds->isEmpty()) {
            return $totalStudents;
        }

        $totalForContext = DB::table('jadwal_peserta')
            ->whereIn('jadwal_ujian_id', $jadwalIds)
            ->distinct('peserta_ujian_id')
            ->count('peserta_ujian_id');

        return $totalForContext > 0 ? $totalForContext : $totalStudents;
    }
}

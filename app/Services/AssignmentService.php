<?php

namespace App\Services;

use App\Models\JadwalUjian;
use App\Models\PesertaUjian;
use App\Models\Ruang;

class AssignmentService
{
    /**
     * Get the assignment data for a proctor in a specific exam.
     *
     * @param int $ujianId
     * @param int $pengawasId
     * @param string|null $sesi
     * @return array
     */
    public function getAssignment(int $ujianId, int $pengawasId, ?string $sesi = null): array
    {
        $allJadwals = JadwalUjian::with(['mataPelajaran.kelas'])
            ->where('ujian_id', $ujianId)
            ->where(function ($q) use ($pengawasId) {
                $q->where('pengawas_id', $pengawasId)
                    ->orWhere('pengawas_pengganti_id', $pengawasId);
            })
            ->get();

        if ($allJadwals->isEmpty()) {
            return ['status' => 404, 'data' => ['message' => 'Jadwal tidak ditemukan']];
        }

        // Get all available sessions for this proctor
        $available_sesi = $allJadwals->pluck('sesi')->unique()->filter()->values()->toArray();

        // Filter schedules by session if selected
        $jadwals = $allJadwals;
        if ($sesi) {
            $jadwals = $allJadwals->where('sesi', $sesi)->values();
        }

        if ($jadwals->isEmpty()) {
            return ['status' => 404, 'data' => ['message' => 'Jadwal untuk sesi tersebut tidak ditemukan']];
        }

        // Aggregate all subjects for this exam
        $mata_pelajarans = $this->aggregateSubjects($ujianId);

        $kelas_names = $jadwals->pluck('mataPelajaran.kelas.nama_kelas')->unique()->implode(', ');
        $total_siswa = $jadwals->sum('total_siswa');
        $first = $jadwals->first();

        // Get participants matching room + session
        $jadwalIds = $jadwals->pluck('id')->toArray();
        $ruangIds = $jadwals->pluck('ruang_id')->unique()->filter()->values()->toArray();
        $sesiList = $jadwals->pluck('sesi')->unique()->filter()->values()->toArray();

        $ruangan_names = $this->getRuanganNames($ruangIds);
        $peserta = $this->getParticipants($ujianId, $ruangIds, $sesiList, $jadwalIds);

        // Determine kelas_id based on participants
        $kelas_id = null;
        $kelas_name = '-';
        if (!empty($peserta) && $peserta->isNotEmpty()) {
            $uniqueKelasIds = $peserta->pluck('kelas_id')->unique()->filter()->values();
            if ($uniqueKelasIds->count() === 1) {
                // Semua peserta dari 1 kelas yang sama (rombel utuh)
                $kelas_id = $uniqueKelasIds->first();
                $firstPeserta = $peserta->first();
                $kelas_name = $firstPeserta->kelas?->nama_kelas ?? '-';
            } else if ($uniqueKelasIds->count() > 1) {
                // Peserta campuran dari beberapa kelas
                $kelas_id = null;
                $kelas_name = 'Gabungan (' . $uniqueKelasIds->count() . ' Kelas)';
            }
        }

        // Use schedule's default kelas name if not determined from participants
        if ($kelas_name === '-' && $kelas_names) {
            $kelas_name = $kelas_names;
        }

        return [
            'status' => 200,
            'data' => [
                'jadwal' => [
                    'mata_pelajarans' => $mata_pelajarans,
                    'mata_pelajaran' => !empty($mata_pelajarans) ? $mata_pelajarans[0]['nama'] : '-',
                    'mapel_id' => !empty($mata_pelajarans) ? $mata_pelajarans[0]['id'] : $first->id,
                    'kelas' => $kelas_name,
                    'ruangan' => $ruangan_names,
                    'kelas_id' => $kelas_id,
                    'total_siswa' => $total_siswa,
                    'mulai_ujian' => $first->mulai_ujian,
                    'ujian_berakhir' => $first->ujian_berakhir,
                ],
                'peserta' => $peserta,
                'available_sesi' => $available_sesi,
            ]
        ];
    }

    /**
     * Aggregate all subjects for a given exam.
     */
    private function aggregateSubjects(int $ujianId): array
    {
        $semuaJadwalUjian = JadwalUjian::with('mataPelajaran')
            ->where('ujian_id', $ujianId)
            ->get();

        return $semuaJadwalUjian->map(function ($jadwal) {
            $namaMapel = '-';
            if ($jadwal->mataPelajaran) {
                $namaMapel = $jadwal->mataPelajaran->nama_mapel;
            } elseif (!empty($jadwal->nama_mapel)) {
                $namaMapel = $jadwal->nama_mapel;
            }

            $sesiStr = $jadwal->sesi ? " (Sesi {$jadwal->sesi})" : "";
            return [
                'id' => $jadwal->id,
                'mapel_id' => $jadwal->mapel_id,
                'nama' => $namaMapel . $sesiStr
            ];
        })->unique('nama')->values()->toArray();
    }

    /**
     * Get room names from IDs.
     */
    private function getRuanganNames(array $ruangIds): string
    {
        if (empty($ruangIds)) {
            return '-';
        }

        return Ruang::whereIn('id', $ruangIds)->pluck('nama_ruang')->implode(', ');
    }

    /**
     * Get participants by room + session, with pivot table fallback.
     */
    private function getParticipants(int $ujianId, array $ruangIds, array $sesiList, array $jadwalIds)
    {
        $peserta = collect();

        if (!empty($ruangIds)) {
            $query = PesertaUjian::where('ujian_id', $ujianId)
                ->whereIn('ruang_id', $ruangIds);

            if (!empty($sesiList)) {
                $query->where(function ($q) use ($sesiList) {
                    $q->whereIn('sesi', $sesiList)
                        ->orWhereNull('sesi');
                });
            }

            $peserta = $query->get();
        }

        // Fallback to pivot table if direct match is empty
        if ($peserta->isEmpty()) {
            $peserta = PesertaUjian::whereHas('jadwalUjians', function ($q) use ($jadwalIds) {
                $q->whereIn('jadwal_ujians.id', $jadwalIds);
            })->get();
        }

        return $peserta;
    }
}

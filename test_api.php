<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$report = \App\Models\LaporanUjian::find(3); // or latest
if (!$report) {
    $report = \App\Models\LaporanUjian::latest()->first();
}

if ($report) {
    echo "Report ID: " . $report->id . "\n";
    echo "Report Kelas ID: " . ($report->kelas_id ?: 'null') . "\n";

    $jadwal = \App\Models\JadwalUjian::with(['mataPelajaran.kelas'])
        ->where('ujian_id', $report->ujian_id)
        ->where(function ($q) use ($report) {
            $q->where('pengawas_id', $report->pengawas_id)
                ->orWhere('pengawas_pengganti_id', $report->pengawas_id);
        })
        ->first();

    if ($jadwal) {
        echo "Jadwal Mapel ID: " . ($jadwal->mapel_id ?: 'null') . "\n";
        echo "Jadwal Mapel Nama: " . ($jadwal->mataPelajaran ? $jadwal->mataPelajaran->nama_mapel : 'null') . "\n";
        echo "Jadwal Mapel Kelas ID: " . ($jadwal->mataPelajaran ? $jadwal->mataPelajaran->kelas_id : 'null') . "\n";
        echo "Jadwal Mapel Kelas Nama: " . ($jadwal->mataPelajaran && $jadwal->mataPelajaran->kelas ? $jadwal->mataPelajaran->kelas->nama_kelas : 'null') . "\n";

        $peserta = \App\Models\PesertaUjian::where('ujian_id', $report->ujian_id)
            ->where('ruang_id', $jadwal?->ruang_id)
            ->where(function ($q) use ($jadwal) {
                if ($jadwal?->sesi)
                    $q->where('sesi', $jadwal->sesi);
            })->get();

        echo "Peserta count in this ruang/sesi: " . $peserta->count() . "\n";
        if ($peserta->count() > 0) {
            $kelasIds = $peserta->pluck('kelas_id')->unique()->filter();
            echo "Unique Kelas IDs: " . implode(', ', $kelasIds->toArray()) . "\n";
            if ($kelasIds->count() === 1) {
                $firstP = clone $peserta->first();
                $firstP->load('kelas');
                echo "Peserta First Kelas Nama: " . ($firstP->kelas ? $firstP->kelas->nama_kelas : 'null') . "\n";
            }
        }
    } else {
        echo "Jadwal not found for report.\n";
    }
} else {
    echo "No reports found.\n";
}

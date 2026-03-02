<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = new \Illuminate\Http\Request();
$request->merge(['ujian_id' => 3]);

$semuaJadwalUjian = \App\Models\JadwalUjian::with('mataPelajaran')
    ->where('ujian_id', $request->ujian_id)
    ->get();

$mata_pelajarans = $semuaJadwalUjian->map(function ($jadwal) {
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

echo json_encode($mata_pelajarans, JSON_PRETTY_PRINT);

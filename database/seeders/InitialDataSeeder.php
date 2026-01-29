<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InitialDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Pengawas
        $pengawas = [
            ['name' => 'Budi Santoso, S.Pd.', 'niy' => '198001012000011001'],
            ['name' => 'Siti Aminah, M.Pd.', 'niy' => '198505052010012005'],
            ['name' => 'Agus Setiawan, S.T.', 'niy' => '199010102019031008'],
            ['name' => 'Dewi Lestari, S.Kom.', 'niy' => '199203152022032010'],
            ['name' => 'Eko Prasetyo, M.T.', 'niy' => '197811202005011003'],
        ];
        $proctorModels = [];
        foreach ($pengawas as $p) {
            $proctorModels[] = \App\Models\Pengawas::create($p);
        }

        // 2. Ruangs
        $ruangs = ['Ruang 01', 'Ruang 02', 'Ruang 03', 'Ruang 04', 'Lab Komputer 1', 'Lab Komputer 2'];
        $ruangModels = [];
        foreach ($ruangs as $r) {
            $ruangModels[] = \App\Models\Ruang::create(['name' => $r]);
        }

        // 3. Ujians (Events)
        $u1 = \App\Models\Ujian::create([
            'nama_ujian' => 'Sumatif Akhir Jenjang (SAJ) 2025',
            'is_active' => true,
        ]);
        $u2 = \App\Models\Ujian::create([
            'nama_ujian' => 'Penilaian Tengah Semester (PTS) Genap 2025',
            'is_active' => true,
        ]);

        // 3.0 Kelas
        $kelases = ['XII-RPL 1', 'XII-RPL 2', 'XII-TKJ 1', 'XII-TKJ 2', 'XII-AKL 1', 'XII-AKL 2'];
        $kelasModels = [];
        foreach ($kelases as $k) {
            $kelasModels[$k] = \App\Models\Kelas::create(['nama_kelas' => $k]);
        }

        // 3.1 Mata Pelajaran
        $mapelSetup = [
            ['name' => 'Matematika', 'kelas' => 'XII-RPL 1'],
            ['name' => 'Bahasa Indonesia', 'kelas' => 'XII-TKJ 2'],
            ['name' => 'Produktif RPL', 'kelas' => 'XII-RPL 2'],
            ['name' => 'Produktif TKJ', 'kelas' => 'XII-TKJ 1'],
            ['name' => 'Bahasa Inggris', 'kelas' => 'XII-AKL 1'],
            ['name' => 'Produktif AKL', 'kelas' => 'XII-AKL 2'],
        ];
        $mapelModels = [];
        foreach ($mapelSetup as $ms) {
            $mapelModels[$ms['name']] = \App\Models\MataPelajaran::create([
                'nama_mapel' => $ms['name'],
                'kelas_id' => $kelasModels[$ms['kelas']]->id
            ]);
        }

        // 3.2 Sesi
        $sesis = ['Sesi 01 (07:30 - 09:30)', 'Sesi 02 (10:00 - 12:00)', 'Sesi 03 (13:00 - 15:00)'];
        $sesiModels = [];
        foreach ($sesis as $s) {
            $sesiModels[] = \App\Models\Sesi::create(['nama_sesi' => $s]);
        }

        $todayDate = now()->format('Y-m-d');

        // 4. Jadwal Ujian (Linking everything)
        // Budi di Ruang 1 - Sesi 1 (07:30 - 09:30)
        $j1 = \App\Models\JadwalUjian::create([
            'ujian_id' => $u1->id,
            'pengawas_id' => $proctorModels[0]->id,
            'ruang_id' => $ruangModels[0]->id,
            'mapel_id' => $mapelModels['Matematika']->id,
            'sesi_id' => $sesiModels[0]->id,
            'mulai_ujian' => now()->setTime(7, 30, 0),
            'ujian_berakhir' => now()->setTime(9, 30, 0),
            'total_siswa' => 36,
        ]);

        // Siti di Ruang 2 - Sesi 1 (07:30 - 09:30)
        $j2 = \App\Models\JadwalUjian::create([
            'ujian_id' => $u1->id,
            'pengawas_id' => $proctorModels[1]->id,
            'ruang_id' => $ruangModels[1]->id,
            'mapel_id' => $mapelModels['Bahasa Indonesia']->id,
            'sesi_id' => $sesiModels[0]->id,
            'mulai_ujian' => now()->setTime(7, 30, 0),
            'ujian_berakhir' => now()->setTime(9, 30, 0),
            'total_siswa' => 32,
        ]);

        // Agus di Ruang 3 - 2 Mapel sekaligus (Sesi 1: 07:30 - 09:30)
        $j3 = \App\Models\JadwalUjian::create([
            'ujian_id' => $u1->id,
            'pengawas_id' => $proctorModels[2]->id,
            'ruang_id' => $ruangModels[2]->id,
            'mapel_id' => $mapelModels['Produktif RPL']->id,
            'sesi_id' => $sesiModels[0]->id,
            'mulai_ujian' => now()->setTime(7, 30, 0),
            'ujian_berakhir' => now()->setTime(9, 30, 0),
            'total_siswa' => 18,
        ]);
        $j4 = \App\Models\JadwalUjian::create([
            'ujian_id' => $u1->id,
            'pengawas_id' => $proctorModels[2]->id,
            'ruang_id' => $ruangModels[2]->id,
            'mapel_id' => $mapelModels['Produktif TKJ']->id,
            'sesi_id' => $sesiModels[0]->id,
            'mulai_ujian' => now()->setTime(7, 30, 0),
            'ujian_berakhir' => now()->setTime(9, 30, 0),
            'total_siswa' => 15,
        ]);

        // Dewi di Ruang 4 - Sesi 1 (07:30 - 09:30)
        $j5 = \App\Models\JadwalUjian::create([
            'ujian_id' => $u1->id,
            'pengawas_id' => $proctorModels[3]->id,
            'ruang_id' => $ruangModels[3]->id,
            'mapel_id' => $mapelModels['Bahasa Inggris']->id,
            'sesi_id' => $sesiModels[0]->id,
            'mulai_ujian' => now()->setTime(7, 30, 0),
            'ujian_berakhir' => now()->setTime(9, 30, 0),
            'total_siswa' => 30,
        ]);

        // 5. Peserta Ujian (Sample Students)
        // Students for Ruang 1 (XII-RPL 1) - Linked to $j1
        for ($i = 1; $i <= 36; $i++) {
            $peserta = \App\Models\PesertaUjian::create([
                'nama' => "Siswa RPL-1-$i",
                'nisn' => "00" . (10000000 + $i),
                'nomor_peserta' => "25-001-" . str_pad($i, 3, '0', STR_PAD_LEFT),
                'ruang_id' => $ruangModels[0]->id,
                'kelas_id' => $kelasModels['XII-RPL 1']->id,
            ]);
            $peserta->jadwalUjians()->attach($j1->id);
        }

        // Students for Ruang 2 (XII-TKJ 2) - Linked to $j2
        for ($i = 1; $i <= 32; $i++) {
            $peserta = \App\Models\PesertaUjian::create([
                'nama' => "Siswa TKJ-2-$i",
                'nisn' => "00" . (20000000 + $i),
                'nomor_peserta' => "25-002-" . str_pad($i, 3, '0', STR_PAD_LEFT),
                'ruang_id' => $ruangModels[1]->id,
                'kelas_id' => $kelasModels['XII-TKJ 2']->id,
            ]);
            $peserta->jadwalUjians()->attach($j2->id);
        }

        // Students for Ruang 3 (Mixed) - Linked to $j3 (RPL) and $j4 (TKJ)
        for ($i = 1; $i <= 18; $i++) {
            $peserta = \App\Models\PesertaUjian::create([
                'nama' => "Siswa RPL-2-$i",
                'nisn' => "00" . (30000000 + $i),
                'nomor_peserta' => "25-003-RPL-" . str_pad($i, 2, '0', STR_PAD_LEFT),
                'ruang_id' => $ruangModels[2]->id,
                'kelas_id' => $kelasModels['XII-RPL 2']->id,
            ]);
            $peserta->jadwalUjians()->attach($j3->id);
        }
        for ($i = 1; $i <= 15; $i++) {
            $peserta = \App\Models\PesertaUjian::create([
                'nama' => "Siswa TKJ-1-$i",
                'nisn' => "00" . (40000000 + $i),
                'nomor_peserta' => "25-003-TKJ-" . str_pad($i, 2, '0', STR_PAD_LEFT),
                'ruang_id' => $ruangModels[2]->id,
                'kelas_id' => $kelasModels['XII-TKJ 1']->id,
            ]);
            $peserta->jadwalUjians()->attach($j4->id);
        }
    }
}

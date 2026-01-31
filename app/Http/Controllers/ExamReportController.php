<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ExamReportController extends Controller
{
    public function getInitData()
    {
        return response()->json([
            'pengawas' => \App\Models\Pengawas::all(),
            'ujians' => \App\Models\Ujian::where('is_active', true)->get(),
            'mata_pelajarans' => \App\Models\MataPelajaran::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ujian_id' => 'required|exists:ujians,id',
            'pengawas_id' => 'required|exists:pengawas,id',
            'mapel_id' => 'required|exists:mata_pelajarans,id',
            'mulai_ujian' => 'required|date',
            'ujian_berakhir' => 'required|date|after:mulai_ujian',
            'kelas_id' => 'required|exists:kelas,id',
            'total_expected' => 'required|integer',
            'total_present' => 'required|integer',
            'total_absent' => 'required|integer',
            'absent_details' => 'nullable|string',
            'notes' => 'nullable|string',
            'signature' => 'required|image|max:2048',
        ]);

        $path = null;
        if ($request->hasFile('signature')) {
            $path = $request->file('signature')->store('signatures', 'public');
        }

        $report = \App\Models\LaporanUjian::create([
            'ujian_id' => $validated['ujian_id'],
            'pengawas_id' => $validated['pengawas_id'],
            'mapel_id' => $validated['mapel_id'],
            'mulai_ujian' => $validated['mulai_ujian'],
            'ujian_berakhir' => $validated['ujian_berakhir'],
            'kelas_id' => $validated['kelas_id'],
            'total_expected' => $validated['total_expected'],
            'total_present' => $validated['total_present'],
            'total_absent' => $validated['total_absent'],
            'absent_details' => $validated['absent_details'],
            'notes' => $validated['notes'],
            'signature_path' => $path,
        ]);

        return response()->json(['message' => 'Laporan berhasil disimpan', 'data' => $report], 201);
    }

    public function scanPeserta(Request $request)
    {
        $validated = $request->validate([
            'kode_peserta' => 'required|string',
            'ujian_id' => 'nullable|exists:ujians,id',
        ]);

        $today = now()->startOfDay();

        // Retry logic for handling deadlocks
        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                return \DB::transaction(function () use ($validated, $today) {
                    // [Dual-Mode] Cek apakah yang di-scan adalah kartu Pengawas
                    $pengawas = \App\Models\Pengawas::where('niy', $validated['kode_peserta'])->first();
                    if ($pengawas) {
                        // Use pessimistic locking to prevent race condition
                        $presensiPengawas = \App\Models\PresensiPengawas::where('pengawas_id', $pengawas->id)
                            ->whereDate('created_at', $today)
                            ->lockForUpdate()
                            ->first();

                        if (!$presensiPengawas) {
                            try {
                                $presensiPengawas = \App\Models\PresensiPengawas::create([
                                    'pengawas_id' => $pengawas->id,
                                    'waktu_datang' => now(),
                                ]);
                            } catch (\Illuminate\Database\QueryException $e) {
                                // Handle duplicate entry error (race condition caught by unique constraint)
                                if ($e->getCode() == 23000) {
                                    $presensiPengawas = \App\Models\PresensiPengawas::where('pengawas_id', $pengawas->id)
                                        ->whereDate('created_at', $today)
                                        ->first();
                                } else {
                                    throw $e;
                                }
                            }
                        } else {
                            // Hanya update waktu_pulang jika sudah lewat 5 menit dari waktu_datang
                            if ($presensiPengawas->created_at->diffInMinutes(now()) >= 5) {
                                $presensiPengawas->update(['waktu_pulang' => now()]);
                            }
                        }

                        return response()->json([
                            'type' => 'pengawas',
                            'message' => "Presensi Pengawas [{$pengawas->name}] tercatat.",
                            'presensi' => $presensiPengawas
                        ]);
                    }

                    // Validasi apakah kode_peserta ada di tabel peserta_ujians
                    $pesertaExists = \App\Models\PesertaUjian::where('nomor_peserta', $validated['kode_peserta'])->exists();
                    if (!$pesertaExists) {
                        return response()->json(['message' => "Kode Peserta [{$validated['kode_peserta']}] tidak terdaftar."], 404);
                    }

                    // Cari presensi hari ini untuk kode tersebut dengan pessimistic locking
                    $presensi = \App\Models\PresensiPeserta::where('kode_peserta', $validated['kode_peserta'])
                        ->whereDate('created_at', $today)
                        ->lockForUpdate()
                        ->first();

                    if (!$presensi) {
                        // Jika belum ada, catat sebagai waktu datang
                        try {
                            $presensi = \App\Models\PresensiPeserta::create([
                                'kode_peserta' => $validated['kode_peserta'],
                                'ujian_id' => $validated['ujian_id'] ?? null,
                                'waktu_datang' => now(),
                            ]);
                        } catch (\Illuminate\Database\QueryException $e) {
                            // Handle duplicate entry error (race condition caught by unique constraint)
                            if ($e->getCode() == 23000) {
                                $presensi = \App\Models\PresensiPeserta::where('kode_peserta', $validated['kode_peserta'])
                                    ->whereDate('created_at', $today)
                                    ->first();
                            } else {
                                throw $e;
                            }
                        }
                        return response()->json(['type' => 'peserta', 'message' => 'Waktu datang peserta tercatat', 'data' => $presensi]);
                    } else {
                        // Jika sudah ada, catat sebagai waktu pulang (hanya jika waktu_pulang masih kosong)
                        if (empty($presensi->waktu_pulang)) {
                            $presensi->update([
                                'waktu_pulang' => now(),
                            ]);
                            return response()->json(['type' => 'peserta', 'message' => 'Waktu pulang peserta tercatat', 'data' => $presensi]);
                        } else {
                            return response()->json(['type' => 'peserta', 'message' => 'Peserta sudah melakukan scan datang dan pulang hari ini', 'data' => $presensi], 400);
                        }
                    }
                });
            } catch (\Illuminate\Database\QueryException $e) {
                // Retry on deadlock (error code 40001)
                if ($e->getCode() == 40001 && $attempt < $maxRetries - 1) {
                    $attempt++;
                    usleep(100000 * $attempt); // Exponential backoff: 100ms, 200ms
                    continue;
                }
                throw $e;
            }
        }
    }

    public function getAssignment(Request $request)
    {
        $request->validate([
            'ujian_id' => 'required|exists:ujians,id',
            'pengawas_id' => 'required|exists:pengawas,id',
        ]);

        $jadwals = \App\Models\JadwalUjian::with(['mataPelajaran.kelas'])
            ->where('ujian_id', $request->ujian_id)
            ->where('pengawas_id', $request->pengawas_id)
            ->get();

        if ($jadwals->isEmpty()) {
            return response()->json(['message' => 'Jadwal tidak ditemukan'], 404);
        }

        // Agregasi Data
        $mata_pelajaran_names = $jadwals->pluck('mataPelajaran.nama_mapel')->unique()->implode(' & ');
        $kelas_names = $jadwals->pluck('mataPelajaran.kelas.nama_kelas')->unique()->implode(' & ');
        $total_siswa = $jadwals->sum('total_siswa');

        $first = $jadwals->first();

        // Ambil semua peserta untuk semua kombinasi kelas di jadwal tersebut
        $peserta = \App\Models\PesertaUjian::whereIn('kelas_id', $jadwals->pluck('mataPelajaran.kelas_id'))
            ->get();

        return response()->json([
            'jadwal' => [
                'mata_pelajaran' => $mata_pelajaran_names, // for display
                'mapel_id' => $first->mapel_id,           // for form storage
                'kelas' => $kelas_names,
                'kelas_id' => $first->mataPelajaran->kelas_id,
                'total_siswa' => $total_siswa,
                'mulai_ujian' => $first->mulai_ujian,
                'ujian_berakhir' => $first->ujian_berakhir,
            ],
            'peserta' => $peserta
        ]);
    }

    public function getPresensiToday()
    {
        $today = now()->startOfDay();
        $data = \App\Models\PresensiPeserta::whereDate('created_at', $today)
            ->orderBy('updated_at', 'desc')
            ->get();
        return response()->json($data);
    }

    public function loginNiy(Request $request)
    {
        $request->validate([
            'niy' => 'required|string',
        ]);

        $niy = trim($request->niy);
        \Log::info("NIY Scan Login Attempt: " . $niy);
        $pengawas = \App\Models\Pengawas::where('niy', $niy)->first();

        if (!$pengawas) {
            return response()->json(['message' => "NIY [{$niy}] tidak terdaftar"], 404);
        }

        // Retry logic for handling deadlocks
        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $presensi = \DB::transaction(function () use ($pengawas) {
                    // Catat Presensi Pengawas with pessimistic locking
                    $today = \Carbon\Carbon::today();
                    $presensi = \App\Models\PresensiPengawas::where('pengawas_id', $pengawas->id)
                        ->whereDate('created_at', $today)
                        ->lockForUpdate()
                        ->first();

                    if (!$presensi) {
                        try {
                            $presensi = \App\Models\PresensiPengawas::create([
                                'pengawas_id' => $pengawas->id,
                                'waktu_datang' => now(),
                            ]);
                        } catch (\Illuminate\Database\QueryException $e) {
                            // Handle duplicate entry error (race condition caught by unique constraint)
                            if ($e->getCode() == 23000) {
                                $presensi = \App\Models\PresensiPengawas::where('pengawas_id', $pengawas->id)
                                    ->whereDate('created_at', $today)
                                    ->first();
                            } else {
                                throw $e;
                            }
                        }
                    } else {
                        // Hanya update waktu_pulang jika sudah lewat 5 menit dari waktu_datang
                        // Ini untuk mencegah scan ganda (double trigger) saat login
                        if ($presensi->created_at->diffInMinutes(now()) >= 5) {
                            $presensi->update([
                                'waktu_pulang' => now(),
                            ]);
                        }
                    }

                    return $presensi;
                });

                return response()->json([
                    'message' => 'Login berhasil',
                    'user' => $pengawas,
                    'presensi' => $presensi
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Retry on deadlock (error code 40001)
                if ($e->getCode() == 40001 && $attempt < $maxRetries - 1) {
                    $attempt++;
                    usleep(100000 * $attempt); // Exponential backoff
                    continue;
                }
                throw $e;
            }
        }
    }

    public function healthCheck()
    {
        try {
            \DB::connection()->getPdo();
            return response()->json([
                'status' => 'ok',
                'database' => 'connected',
                'details' => [
                    'engine' => \DB::connection()->getDriverName(),
                    'name' => \DB::connection()->getDriverName() === 'sqlite'
                        ? basename(\DB::connection()->getDatabaseName())
                        : \DB::connection()->getDatabaseName(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'database' => 'disconnected',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

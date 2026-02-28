<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Services\PresensiService;

class ExamReportController extends Controller
{
    protected $presensiService;

    public function __construct(PresensiService $presensiService)
    {
        $this->presensiService = $presensiService;
    }

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

        $result = $this->presensiService->handleScanPeserta(
            $validated['kode_peserta'],
            $validated['ujian_id'] ?? null
        );

        return response()->json($result['data'], $result['status'] ?? 200);
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

        $result = $this->presensiService->handleLoginNiy($niy);

        return response()->json($result['data'], $result['status'] ?? 200);
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

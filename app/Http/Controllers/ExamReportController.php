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
            $file = $request->file('signature');
            $img = @imagecreatefromstring(file_get_contents($file));

            if ($img !== false) {
                // Preserve transparency
                imagealphablending($img, false);
                imagesavealpha($img, true);

                $filename = 'signatures/' . \Illuminate\Support\Str::random(40) . '.png';
                $fullPath = storage_path('app/public/' . $filename);

                // Ensure directory exists
                if (!file_exists(dirname($fullPath))) {
                    mkdir(dirname($fullPath), 0755, true);
                }

                // Save with maximum compression (level 9)
                imagepng($img, $fullPath, 9);
                imagedestroy($img);

                $path = $filename;
            } else {
                // Fallback direct storage if GD initialization fails
                $path = $file->store('signatures', 'public');
            }
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
            'pengawas_id' => 'nullable|exists:pengawas,id',
        ]);

        $result = $this->presensiService->handleScanPeserta(
            $validated['kode_peserta'],
            $validated['ujian_id'] ?? null,
            $validated['pengawas_id'] ?? null
        );

        return response()->json($result['data'], $result['status'] ?? 200);
    }

    public function getAssignment(Request $request)
    {
        $request->validate([
            'ujian_id' => 'required|exists:ujians,id',
            'pengawas_id' => 'required|exists:pengawas,id',
            'sesi' => 'nullable',
        ]);

        $allJadwals = \App\Models\JadwalUjian::with(['mataPelajaran.kelas'])
            ->where('ujian_id', $request->ujian_id)
            ->where(function ($q) use ($request) {
                $q->where('pengawas_id', $request->pengawas_id)
                    ->orWhere('pengawas_pengganti_id', $request->pengawas_id);
            })
            ->get();

        if ($allJadwals->isEmpty()) {
            return response()->json(['message' => 'Jadwal tidak ditemukan'], 404);
        }

        // Dapatkan semua sesi yang tersedia untuk pengawas ini di ujian ini
        $available_sesi = $allJadwals->pluck('sesi')->unique()->filter()->values()->toArray();

        // Filter jadwal berdasarkan sesi jika dipilih
        $jadwals = $allJadwals;
        if ($request->filled('sesi')) {
            $jadwals = $allJadwals->where('sesi', $request->sesi)->values();
        }

        if ($jadwals->isEmpty()) {
            return response()->json(['message' => 'Jadwal untuk sesi tersebut tidak ditemukan'], 404);
        }

        // Agregasi Data Mata Pelajaran (Semua mapel dalam ujian ini)
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

        $kelas_names = $jadwals->pluck('mataPelajaran.kelas.nama_kelas')->unique()->implode(', ');
        $total_siswa = $jadwals->sum('total_siswa');

        $first = $jadwals->first();

        // Ambil peserta: cocokkan berdasarkan ujian_id + ruang + sesi
        $jadwalIds = $jadwals->pluck('id')->toArray();
        $ruangList = $jadwals->pluck('ruang')->unique()->filter()->values()->toArray();
        $sesiList = $jadwals->pluck('sesi')->unique()->filter()->values()->toArray();
        $ruangan_names = !empty($ruangList) ? implode(', ', $ruangList) : '-';

        // Cari peserta berdasarkan ujian_id + ruang + sesi (tanpa perlu pivot)
        $peserta = collect();
        if (!empty($ruangList)) {
            $query = \App\Models\PesertaUjian::where('ujian_id', $request->ujian_id)
                ->whereIn('ruang', $ruangList);

            if (!empty($sesiList)) {
                $query->where(function ($q) use ($sesiList) {
                    $q->whereIn('sesi', $sesiList)
                        ->orWhereNull('sesi');
                });
            }

            $peserta = $query->get();
        }

        // Fallback ke pivot table jika direct match kosong
        if ($peserta->isEmpty()) {
            $peserta = \App\Models\PesertaUjian::whereHas('jadwalUjians', function ($q) use ($jadwalIds) {
                $q->whereIn('jadwal_ujians.id', $jadwalIds);
            })->get();
        }

        return response()->json([
            'jadwal' => [
                'mata_pelajarans' => $mata_pelajarans, // array for dropdown
                'mata_pelajaran' => !empty($mata_pelajarans) ? $mata_pelajarans[0]['nama'] : '-', // fallback display
                'mapel_id' => !empty($mata_pelajarans) ? $mata_pelajarans[0]['id'] : $first->id,   // initial form storage maps to jadwal id now
                'kelas' => $kelas_names ?: '-',
                'ruangan' => $ruangan_names,
                'kelas_id' => $first->mataPelajaran?->kelas_id,
                'total_siswa' => $total_siswa,
                'mulai_ujian' => $first->mulai_ujian,
                'ujian_berakhir' => $first->ujian_berakhir,
            ],
            'peserta' => $peserta,
            'available_sesi' => $available_sesi // array for sesi dropdown
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

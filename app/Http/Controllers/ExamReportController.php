<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Services\PresensiService;
use App\Services\AssignmentService;

class ExamReportController extends Controller
{
    protected $presensiService;
    protected $assignmentService;

    public function __construct(PresensiService $presensiService, AssignmentService $assignmentService)
    {
        $this->presensiService = $presensiService;
        $this->assignmentService = $assignmentService;
    }

    public function getInitData()
    {
        $ujiansQuery = \App\Models\Ujian::where('is_active', true);

        // If authenticated via sanctum (proctor), filter by their assignments
        if (auth('sanctum')->check()) {
            $pengawasId = auth('sanctum')->id();
            $ujiansQuery->whereHas('jadwalUjians', function ($q) use ($pengawasId) {
                $q->where('pengawas_id', $pengawasId)
                    ->orWhere('pengawas_pengganti_id', $pengawasId);
            });
        }

        return response()->json([
            'pengawas' => \App\Models\Pengawas::all(),
            'ujians' => $ujiansQuery->get(),
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
                imagealphablending($img, false);
                imagesavealpha($img, true);

                $filename = 'signatures/' . \Illuminate\Support\Str::random(40) . '.png';
                $fullPath = storage_path('app/public/' . $filename);

                if (!file_exists(dirname($fullPath))) {
                    mkdir(dirname($fullPath), 0755, true);
                }

                imagepng($img, $fullPath, 9);
                imagedestroy($img);

                $path = $filename;
            } else {
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

        $result = $this->assignmentService->getAssignment(
            (int) $request->ujian_id,
            (int) $request->pengawas_id,
            $request->filled('sesi') ? $request->sesi : null
        );

        return response()->json($result['data'], $result['status'] ?? 200);
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
}

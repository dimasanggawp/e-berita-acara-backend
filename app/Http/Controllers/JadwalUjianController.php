<?php

namespace App\Http\Controllers;

use App\Models\JadwalUjian;
use App\Models\Pengawas;
use App\Models\PesertaUjian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class JadwalUjianController extends Controller
{
    public function index()
    {
        return response()->json(
            JadwalUjian::with(['ujian', 'pengawas', 'pengawasPengganti'])
                ->whereHas('ujian', function ($q) {
                    $q->where('is_active', true);
                })
                ->orderBy('mulai_ujian', 'desc')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ujian_id' => 'required|exists:ujians,id',
            'pengawas_id' => 'required|exists:pengawas,id',
            'pengawas_pengganti_id' => 'nullable|exists:pengawas,id',
            'ruang' => 'required|string|max:255',
            'nama_mapel' => 'required|string|max:255',
            'sesi' => 'nullable|string|max:255',
            'mulai_ujian' => 'required|date',
            'ujian_berakhir' => 'required|date|after:mulai_ujian',
        ]);

        // Hitung total siswa secara otomatis berdasarkan Ruang, Ujian, dan Sesi
        $query = PesertaUjian::where('ruang', $validated['ruang'])
            ->where('ujian_id', $validated['ujian_id']);

        if (!empty($validated['sesi'])) {
            $query->where('sesi', $validated['sesi']);
        }

        $validated['total_siswa'] = $query->count();

        try {
            $jadwal = DB::transaction(function () use ($validated) {
                return JadwalUjian::create($validated);
            });

            return response()->json([
                'message' => 'Jadwal ujian berhasil ditambahkan',
                'data' => $jadwal->load(['ujian', 'pengawas'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 409);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'ujian_id' => 'required|exists:ujians,id',
            'pengawas_id' => 'required|exists:pengawas,id',
            'pengawas_pengganti_id' => 'nullable|exists:pengawas,id',
            'ruang' => 'required|string|max:255',
            'nama_mapel' => 'required|string|max:255',
            'sesi' => 'nullable|string|max:255',
            'mulai_ujian' => 'required|date',
            'ujian_berakhir' => 'required|date|after:mulai_ujian',
        ]);

        try {
            $jadwal = DB::transaction(function () use ($id, $validated) {
                $jadwal = JadwalUjian::lockForUpdate()->findOrFail($id);

                // Hitung ulang total siswa
                $query = PesertaUjian::where('ruang', $validated['ruang'])
                    ->where('ujian_id', $validated['ujian_id']);

                if (!empty($validated['sesi'])) {
                    $query->where('sesi', $validated['sesi']);
                }

                $validated['total_siswa'] = $query->count();

                $jadwal->update($validated);
                return $jadwal;
            });

            return response()->json([
                'message' => 'Jadwal ujian berhasil diperbarui',
                'data' => $jadwal->load(['ujian', 'pengawas'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 409);
        }
    }

    public function destroy($id)
    {
        $jadwal = JadwalUjian::findOrFail($id);
        $jadwal->delete();

        return response()->json([
            'message' => 'Jadwal ujian berhasil dihapus'
        ]);
    }

    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set Headers
        $sheet->setCellValue('A1', 'Tanggal (YYYY-MM-DD)');
        $sheet->setCellValue('B1', 'Sesi');
        $sheet->setCellValue('C1', 'Jam (HH:mm-HH:mm)');
        $sheet->setCellValue('D1', 'Mapel');
        $sheet->setCellValue('E1', 'NIY Pengawas');
        $sheet->setCellValue('F1', 'Ruang');
        $sheet->setCellValue('G1', 'NIY Pengganti (Opsional)');

        // Set Example Data
        $sheet->setCellValue('A2', '2026-02-10');
        $sheet->setCellValue('B2', 'Sesi 1');
        $sheet->setCellValue('C2', '07:30-09:30');
        $sheet->setCellValue('D2', 'Bahasa Indonesia');
        $sheet->setCellValue('E2', '12345678');
        $sheet->setCellValue('F2', 'R.01');
        $sheet->setCellValue('G2', '87654321');

        // Auto size columns
        foreach (range('A', 'G') as $colId) {
            $sheet->getColumnDimension($colId)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template_jadwal.xlsx"',
            'Cache-Control' => 'max-age=0',
        ];

        $callback = function () use ($writer) {
            $writer->save('php://output');
        };

        return response()->stream($callback, 200, $headers);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:2048',
            'ujian_id' => 'required|exists:ujians,id',
        ]);

        $file = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membaca file.', 'error' => $e->getMessage()], 400);
        }

        if (count($rows) > 0) {
            array_shift($rows); // Skip header
        }

        $imported = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $data) {
                if (empty(array_filter($data))) {
                    continue; // Skip empty rows
                }

                if (count($data) < 6 || empty($data[0]) || empty($data[2]) || empty($data[3]) || empty($data[4]) || empty($data[5])) {
                    $errors[] = "Baris " . ($index + 2) . ": Data tidak lengkap (Minimal 6 kolom).";
                    continue;
                }

                $tanggalStr = trim($data[0]);
                $sesi = trim($data[1] ?? '');
                $jamMulaiSelesai = trim($data[2]);
                $namaMapel = trim($data[3]);
                $niyPengawas = trim($data[4]);
                $ruang = trim($data[5]);
                $niyPengganti = isset($data[6]) ? trim($data[6]) : null;

                $times = explode('-', $jamMulaiSelesai);
                if (count($times) < 2) {
                    $errors[] = "Baris " . ($index + 2) . ": Format jam salah (harus HH:mm-HH:mm).";
                    continue;
                }

                $mulai = $tanggalStr . ' ' . trim($times[0]);
                $berakhir = $tanggalStr . ' ' . trim($times[1]);

                $pengawas = Pengawas::where('niy', $niyPengawas)->first();
                if (!$pengawas) {
                    $errors[] = "Baris " . ($index + 2) . ": Pengawas utama dengan NIY '$niyPengawas' tidak ditemukan.";
                    continue;
                }

                $pengawasPengganti = null;
                if (!empty($niyPengganti)) {
                    $pengawasPengganti = Pengawas::where('niy', $niyPengganti)->first();
                    if (!$pengawasPengganti) {
                        $errors[] = "Baris " . ($index + 2) . ": Pengawas pengganti dengan NIY '$niyPengganti' tidak ditemukan.";
                        continue;
                    }
                }

                // Hitung total siswa secara otomatis
                $query = PesertaUjian::where('ruang', $ruang)
                    ->where('ujian_id', $request->ujian_id);

                if (!empty($sesi)) {
                    $query->where('sesi', $sesi);
                }

                $totalSiswaCount = $query->count();

                JadwalUjian::create([
                    'ujian_id' => $request->ujian_id,
                    'pengawas_id' => $pengawas->id,
                    'pengawas_pengganti_id' => $pengawasPengganti ? $pengawasPengganti->id : null,
                    'ruang' => $ruang,
                    'nama_mapel' => $namaMapel,
                    'sesi' => $sesi,
                    'mulai_ujian' => $mulai,
                    'ujian_berakhir' => $berakhir,
                    'total_siswa' => $totalSiswaCount,
                ]);

                $imported++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal mengimpor data.', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => "Berhasil mengimpor $imported jadwal.",
            'errors' => $errors
        ]);
    }
}

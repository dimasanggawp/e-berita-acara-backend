<?php

namespace App\Http\Controllers;

use App\Models\JadwalUjian;
use App\Models\Pengawas;
use App\Models\PesertaUjian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JadwalUjianController extends Controller
{
    public function index()
    {
        return response()->json(
            JadwalUjian::with(['ujian', 'pengawas'])
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
        $headers = ['Tanggal (YYYY-MM-DD)', 'Sesi', 'Jam (HH:mm-HH:mm)', 'Mapel', 'NIY Pengawas', 'Ruang'];
        $example = ['2026-02-10', 'Sesi 1', '07:30-09:30', 'Bahasa Indonesia', '12345678', 'R.01'];

        $callback = function () use ($headers, $example) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fputcsv($file, $example); // Contoh pengisian
            fclose($file);
        };
        return response()->stream($callback, 200, [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=template_jadwal.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'ujian_id' => 'required|exists:ujians,id',
        ]);

        $file = $request->file('file');
        $csvData = file_get_contents($file);
        $rows = explode("\n", $csvData);
        $header = array_shift($rows); // Skip header

        $imported = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                if (empty(trim($row)))
                    continue;
                $data = str_getcsv($row);

                if (count($data) < 6) {
                    $errors[] = "Baris " . ($index + 2) . ": Data tidak lengkap (Minimal 6 kolom).";
                    continue;
                }

                $tanggalStr = trim($data[0]);
                $sesi = trim($data[1]);
                $jamMulaiSelesai = trim($data[2]);
                $namaMapel = trim($data[3]);
                $niyPengawas = trim($data[4]);
                $ruang = trim($data[5]);

                $times = explode('-', $jamMulaiSelesai);
                if (count($times) < 2) {
                    $errors[] = "Baris " . ($index + 2) . ": Format jam salah (harus HH:mm-HH:mm).";
                    continue;
                }

                $mulai = $tanggalStr . ' ' . trim($times[0]);
                $berakhir = $tanggalStr . ' ' . trim($times[1]);

                $pengawas = Pengawas::where('niy', $niyPengawas)->first();
                if (!$pengawas) {
                    $errors[] = "Baris " . ($index + 2) . ": Pengawas dengan NIY '$niyPengawas' tidak ditemukan.";
                    continue;
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

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PesertaUjian;
use App\Models\JadwalUjian;
use App\Models\Kelas;
use App\Models\Ujian;
use Illuminate\Support\Facades\Response;

class PesertaUjianController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = PesertaUjian::query();

        if ($request->has('ujian_id')) {
            $query->where('ujian_id', $request->ujian_id);
        } else {
            // Default: only those in active exams
            $query->whereHas('ujian', function ($q) {
                $q->where('is_active', true);
            });
        }

        return $query->with(['ujian', 'jadwalUjians'])
            ->orderBy('nama', 'asc')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nisn' => 'required|string|unique:peserta_ujians,nisn',
            'nomor_peserta' => 'required|string|unique:peserta_ujians,nomor_peserta',
            'kelas' => 'required|string|max:255',
            'ruang' => 'nullable|string|max:255',
            'ujian_id' => 'required|exists:ujians,id',
        ]);

        $peserta = PesertaUjian::create($validated);

        // Associate with all schedules in that exam
        $schedules = JadwalUjian::where('ujian_id', $validated['ujian_id'])
            ->get();

        $peserta->jadwalUjians()->sync($schedules->pluck('id'));

        return $peserta;
    }

    public function update(Request $request, string $id)
    {
        $peserta = PesertaUjian::findOrFail($id);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nisn' => 'required|string|unique:peserta_ujians,nisn,' . $id,
            'nomor_peserta' => 'required|string|unique:peserta_ujians,nomor_peserta,' . $id,
            'kelas' => 'required|string|max:255',
            'ruang' => 'nullable|string|max:255',
            'ujian_id' => 'required|exists:ujians,id',
        ]);

        $peserta->update($validated);

        // Update associations
        $schedules = JadwalUjian::where('ujian_id', $validated['ujian_id'])
            ->get();

        $peserta->jadwalUjians()->sync($schedules->pluck('id'));

        return $peserta;
    }

    public function destroy(string $id)
    {
        $peserta = PesertaUjian::findOrFail($id);
        $peserta->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function meta()
    {
        return response()->json([
            'kelases' => Kelas::all(),
            'ujians' => Ujian::where('is_active', true)->get(),
        ]);
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_peserta.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['nama', 'nisn', 'nomor_peserta', 'kelas', 'ruang']);
            // example row
            fputcsv($file, ['Ahmad Dani', '1234567890', 'U001', 'XII-RPL 1', 'Lab 1']);
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'ujian_id' => 'required|exists:ujians,id',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        // Skip header
        $header = fgetcsv($handle);

        $count = 0;
        $errors = [];

        $schedules = JadwalUjian::where('ujian_id', $request->ujian_id)->get();
        $scheduleIds = $schedules->pluck('id');

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 4)
                continue; // Basic validation

            try {
                $peserta = PesertaUjian::updateOrCreate(
                    [
                        'nisn' => $data[1],
                        'ujian_id' => $request->ujian_id
                    ],
                    [
                        'nama' => $data[0],
                        'nomor_peserta' => $data[2],
                        'kelas' => $data[3],
                        'ruang' => $data[4] ?? '',
                    ]
                );

                $peserta->jadwalUjians()->sync($scheduleIds);
                $count++;
            } catch (\Exception $e) {
                $errors[] = "Error at row " . ($count + 2) . ": " . $e->getMessage();
            }
        }

        fclose($handle);

        return response()->json([
            'message' => "Successfully imported $count records.",
            'errors' => $errors
        ]);
    }
}

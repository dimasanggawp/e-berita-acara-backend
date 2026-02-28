<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PesertaUjian;
use App\Models\JadwalUjian;
use App\Models\Kelas;
use App\Models\Ujian;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
            'sesi' => 'nullable|string|max:255',
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
            'sesi' => 'nullable|string|max:255',
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
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set Headers
        $sheet->setCellValue('A1', 'Nama Lengkap');
        $sheet->setCellValue('B1', 'NISN');
        $sheet->setCellValue('C1', 'Nomor Peserta');
        $sheet->setCellValue('D1', 'Kelas');
        $sheet->setCellValue('E1', 'Ruang');
        $sheet->setCellValue('F1', 'Sesi');

        // Set Example Data
        $sheet->setCellValue('A2', 'Ahmad Dani');
        $sheet->setCellValue('B2', '1234567890');
        $sheet->setCellValue('C2', 'U001');
        $sheet->setCellValue('D2', 'XII-RPL 1');
        $sheet->setCellValue('E2', 'Lab 1');
        $sheet->setCellValue('F2', 'Sesi 1');

        // Auto size columns
        foreach (range('A', 'F') as $colId) {
            $sheet->getColumnDimension($colId)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template_peserta.xlsx"',
            'Cache-Control' => 'max-age=0',
        ];

        $callback = function () use ($writer) {
            $writer->save('php://output');
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:2048',
            'ujian_id' => 'required|exists:ujians,id',
        ]);

        $file = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $dataRows = $sheet->toArray();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membaca file.', 'error' => $e->getMessage()], 400);
        }

        // Remove header
        if (count($dataRows) > 0) {
            array_shift($dataRows);
        }

        $count = 0;
        $errors = [];

        $schedules = JadwalUjian::where('ujian_id', $request->ujian_id)->get();
        $scheduleIds = $schedules->pluck('id');

        foreach ($dataRows as $index => $data) {
            if (empty(array_filter($data))) {
                continue; // Skip empty rows
            }

            // Basic validation
            if (count($data) < 4 || empty($data[0]) || empty($data[1]) || empty($data[2])) {
                continue;
            }

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
                        'sesi' => $data[5] ?? '',
                    ]
                );

                $peserta->jadwalUjians()->sync($scheduleIds);
                $count++;
            } catch (\Exception $e) {
                $errors[] = "Error at row " . ($count + 2) . ": " . $e->getMessage();
            }
        }

        return response()->json([
            'message' => "Successfully imported $count records.",
            'errors' => $errors
        ]);
    }
}

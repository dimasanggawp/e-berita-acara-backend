<?php

namespace App\Http\Controllers;

use App\Models\Ruang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RuangController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Ruang::query();

        if ($request->has('ujian_id') && $request->ujian_id !== null && $request->ujian_id !== '') {
            $query->where('ujian_id', $request->ujian_id);
        }

        return response()->json($query->orderBy('nama_ruang', 'asc')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ujian_id' => 'required|exists:ujians,id',
            'nama_ruang' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('ruangs')->where(function ($query) use ($request) {
                    return $query->where('kampus', $request->kampus)
                        ->where('ujian_id', $request->ujian_id);
                }),
            ],
            'kampus' => 'required|in:Kampus 1,Kampus 2',
        ]);

        $ruang = Ruang::create($validated);

        return response()->json([
            'message' => 'Ruang berhasil ditambahkan',
            'data' => $ruang
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $ruang = Ruang::findOrFail($id);
        return response()->json($ruang);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $ruang = Ruang::findOrFail($id);

        $validated = $request->validate([
            'ujian_id' => 'required|exists:ujians,id',
            'nama_ruang' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('ruangs')->where(function ($query) use ($request) {
                    return $query->where('kampus', $request->kampus)
                        ->where('ujian_id', $request->ujian_id);
                })->ignore($id),
            ],
            'kampus' => 'required|in:Kampus 1,Kampus 2',
        ]);

        $ruang->update($validated);

        return response()->json([
            'message' => 'Ruang berhasil diperbarui',
            'data' => $ruang
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $ruang = Ruang::findOrFail($id);
        $ruang->delete();

        return response()->json([
            'message' => 'Ruang berhasil dihapus'
        ]);
    }

    /**
     * Download template for Excel import.
     */
    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set Headers
        $sheet->setCellValue('A1', 'Nama Ruang');
        $sheet->setCellValue('B1', 'Kampus');

        // Set Example Data
        $sheet->setCellValue('A2', 'LAB. KOMPUTER 1');
        $sheet->setCellValue('B2', 'Kampus 1');
        $sheet->setCellValue('A3', 'RUANG 01');
        $sheet->setCellValue('B3', 'Kampus 1');
        $sheet->setCellValue('A4', 'RUANG 01');
        $sheet->setCellValue('B4', 'Kampus 2');

        // Auto size columns
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template_ruang.xlsx"',
            'Cache-Control' => 'max-age=0',
        ];

        $callback = function () use ($writer) {
            $writer->save('php://output');
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import data class using Excel/CSV file.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
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
            $imported = 0;
            $errors = [];

            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            $successCount = 0;

            DB::beginTransaction();
            try {
                foreach ($rows as $index => $row) {
                    if ($index === 0)
                        continue; // Skip header

                    $namaRuang = $row[0] ?? null;
                    $kampus = $row[1] ?? 'Kampus 1'; // Default to Kampus 1 if empty

                    if (empty($namaRuang)) {
                        $errors[] = "Baris " . ($index + 1) . ": Nama ruang kosong.";
                        continue;
                    }

                    // Validate kampus value
                    $validKampus = ['Kampus 1', 'Kampus 2'];
                    if (!in_array($kampus, $validKampus)) {
                        $kampus = 'Kampus 1'; // Fallback / force default if invalid, or you could add to errors
                    }

                    try {
                        // Find existing by fields AND ujian_id or create
                        $ruang = Ruang::where('nama_ruang', $namaRuang)
                            ->where('kampus', $kampus)
                            ->where('ujian_id', $request->ujian_id)
                            ->first();

                        if ($ruang) {
                            // It exists, nothing to update right now unless we have more fields
                        } else {
                            Ruang::create([
                                'nama_ruang' => $namaRuang,
                                'kampus' => $kampus,
                                'ujian_id' => $request->ujian_id,
                            ]);
                        }
                        $successCount++;
                    } catch (\Exception $e) {
                        $errors[] = "Baris " . ($index + 1) . ": Gagal menyimpan (" . $e->getMessage() . ").";
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => 'Gagal mengimpor data.', 'error' => $e->getMessage()], 500);
            }

            return response()->json([
                'message' => "Berhasil mengimpor $successCount ruang.",
                'errors' => $errors
            ]);
        }

        return response()->json(['message' => 'File kosong.'], 400);
    }
}

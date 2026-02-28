<?php

namespace App\Http\Controllers;

use App\Models\Pengawas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PengawasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $pengawas = Pengawas::with('ujian')->orderBy('name', 'asc')->get();
        return response()->json($pengawas);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'niy' => 'nullable|string|max:50',
            'ujian_id' => 'required|exists:ujians,id',
        ]);

        try {
            $pengawas = \DB::transaction(function () use ($validated) {
                return Pengawas::create($validated);
            });

            return response()->json([
                'message' => 'Data pengawas berhasil ditambahkan',
                'data' => $pengawas
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle duplicate entry or constraint violations
            if ($e->getCode() == 23000) {
                return response()->json([
                    'message' => 'Data pengawas dengan NIY yang sama sudah ada.'
                ], 409);
            }
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $pengawas = Pengawas::findOrFail($id);
        return response()->json($pengawas);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'niy' => 'nullable|string|max:50',
            'ujian_id' => 'required|exists:ujians,id',
        ]);

        try {
            $pengawas = \DB::transaction(function () use ($id, $validated) {
                $pengawas = Pengawas::lockForUpdate()->findOrFail($id);
                $pengawas->update($validated);
                return $pengawas;
            });

            return response()->json([
                'message' => 'Data pengawas berhasil diperbarui',
                'data' => $pengawas
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000) {
                return response()->json([
                    'message' => 'Data pengawas dengan NIY yang sama sudah ada.'
                ], 409);
            }
            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $pengawas = Pengawas::findOrFail($id);
        $pengawas->delete();

        return response()->json([
            'message' => 'Data pengawas berhasil dihapus'
        ]);
    }

    /**
     * Import pengawas from CSV file.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:2048',
            'ujian_id' => 'required|exists:ujians,id'
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membaca file.', 'error' => $e->getMessage()], 400);
        }

        if (count($data) > 0) {
            array_shift($data); // Remove header row
        }

        $count = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                if (empty(array_filter($row))) {
                    continue; // Skip empty rows
                }

                // Assuming format: Name, NIY
                $name = $row[0] ?? null;
                $niy = $row[1] ?? null;
                $ujianId = $request->input('ujian_id');

                if (!$name) {
                    $errors[] = "Baris " . ($index + 2) . ": Nama wajib diisi.";
                    continue;
                }

                if (!$ujianId) {
                    throw new \Exception("Ujian ID wajib dipilih.");
                }

                Pengawas::create([
                    'name' => $name,
                    'niy' => $niy,
                    'ujian_id' => $ujianId
                ]);
                $count++;
            }

            DB::commit();

            return response()->json([
                'message' => "Berhasil mengimpor $count pengawas.",
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengimpor data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download template CSV.
     */
    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set Headers
        $sheet->setCellValue('A1', 'Nama Lengkap');
        $sheet->setCellValue('B1', 'NIY');

        // Set Example Data
        $sheet->setCellValue('A2', 'Drs. Contoh Guru, M.Pd.');
        $sheet->setCellValue('B2', '123456789');
        $sheet->setCellValue('A3', 'Siti Aminah, S.Pd.');
        $sheet->setCellValue('B3', '');

        // Auto size columns
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template_pengawas.xlsx"',
            'Cache-Control' => 'max-age=0',
        ];

        $callback = function () use ($writer) {
            $writer->save('php://output');
        };

        return response()->stream($callback, 200, $headers);
    }
}

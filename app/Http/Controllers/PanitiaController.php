<?php

namespace App\Http\Controllers;

use App\Models\Panitia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PanitiaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Panitia::with('ujian');

        if ($request->has('ujian_id') && $request->ujian_id !== null && $request->ujian_id !== '') {
            $query->where('ujian_id', $request->ujian_id);
        }

        $panitia = $query->orderBy('name', 'asc')->get();
        return response()->json($panitia);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'niy' => 'nullable|string|max:50',
            'jabatan' => 'nullable|string|max:255',
            'ujian_id' => 'required|exists:ujians,id',
        ]);

        try {
            $panitia = DB::transaction(function () use ($validated) {
                return Panitia::create($validated);
            });

            return response()->json([
                'message' => 'Data panitia berhasil ditambahkan',
                'data' => $panitia
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000) {
                return response()->json([
                    'message' => 'Data panitia dengan NIY yang sama sudah ada.'
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
        $panitia = Panitia::findOrFail($id);
        return response()->json($panitia);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'niy' => 'nullable|string|max:50',
            'jabatan' => 'nullable|string|max:255',
            'ujian_id' => 'required|exists:ujians,id',
        ]);

        try {
            $panitia = DB::transaction(function () use ($id, $validated) {
                $panitia = Panitia::lockForUpdate()->findOrFail($id);
                $panitia->update($validated);
                return $panitia;
            });

            return response()->json([
                'message' => 'Data panitia berhasil diperbarui',
                'data' => $panitia
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000) {
                return response()->json([
                    'message' => 'Data panitia dengan NIY yang sama sudah ada.'
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
        $panitia = Panitia::findOrFail($id);
        $panitia->delete();

        return response()->json([
            'message' => 'Data panitia berhasil dihapus'
        ]);
    }

    /**
     * Import panitia from Excel/CSV file.
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

                // Format: Name, NIY, Jabatan
                $name = $row[0] ?? null;
                $niy = $row[1] ?? null;
                $jabatan = $row[2] ?? null;
                $ujianId = $request->input('ujian_id');

                if (!$name) {
                    $errors[] = "Baris " . ($index + 2) . ": Nama wajib diisi.";
                    continue;
                }

                if (!$ujianId) {
                    throw new \Exception("Ujian ID wajib dipilih.");
                }

                Panitia::create([
                    'name' => $name,
                    'niy' => $niy,
                    'jabatan' => $jabatan,
                    'ujian_id' => $ujianId
                ]);
                $count++;
            }

            DB::commit();

            return response()->json([
                'message' => "Berhasil mengimpor $count panitia.",
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import Panitia Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengimpor data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download template Excel.
     */
    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set Headers
        $sheet->setCellValue('A1', 'Nama Lengkap');
        $sheet->setCellValue('B1', 'NIY');
        $sheet->setCellValue('C1', 'Jabatan');

        // Set Example Data
        $sheet->setCellValue('A2', 'Drs. Contoh Guru, M.Pd.');
        $sheet->setCellValue('B2', '123456789');
        $sheet->setCellValue('C2', 'Ketua Panitia');
        $sheet->setCellValue('A3', 'Siti Aminah, S.Pd.');
        $sheet->setCellValue('B3', '');
        $sheet->setCellValue('C3', 'Sekretaris');

        // Auto size columns
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template_panitia.xlsx"',
            'Cache-Control' => 'max-age=0',
        ];

        $callback = function () use ($writer) {
            $writer->save('php://output');
        };

        return response()->stream($callback, 200, $headers);
    }
}

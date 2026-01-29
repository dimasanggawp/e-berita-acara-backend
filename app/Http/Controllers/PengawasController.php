<?php

namespace App\Http\Controllers;

use App\Models\Pengawas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $pengawas = Pengawas::create($validated);

        return response()->json([
            'message' => 'Data pengawas berhasil ditambahkan',
            'data' => $pengawas
        ], 201);
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
        $pengawas = Pengawas::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'niy' => 'nullable|string|max:50',
            'ujian_id' => 'required|exists:ujians,id',
        ]);

        $pengawas->update($validated);

        return response()->json([
            'message' => 'Data pengawas berhasil diperbarui',
            'data' => $pengawas
        ]);
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
            'file' => 'required|file|mimes:csv,txt|max:2048'
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $data = array_map('str_getcsv', file($path));
        $header = array_shift($data); // Remove header row

        // Basic validation of header structure if needed
        // Expecting: Name, NIY

        $count = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                if (count($row) < 1)
                    continue;

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
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_pengawas.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Nama Lengkap', 'NIY']);
            fputcsv($file, ['Drs. Contoh Guru, M.Pd.', '123456789']);
            fputcsv($file, ['Siti Aminah, S.Pd.', '']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

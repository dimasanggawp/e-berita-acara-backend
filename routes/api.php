<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamReportController;
use App\Http\Controllers\JadwalUjianController;
use App\Http\Controllers\UjianController;
use App\Http\Controllers\PengawasController;

// Add a named 'login' route to prevent RouteNotFoundException when Sanctum intercepts unauthenticated requests
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

Route::post('/login', [AuthController::class, 'login']);

Route::get('/pengawas/template-import', [PengawasController::class, 'template']);
Route::get('/jadwal-ujian/template', [JadwalUjianController::class, 'template']);
Route::get('/peserta-ujian/template', [\App\Http\Controllers\PesertaUjianController::class, 'downloadTemplate']);
Route::get('/ruang/template-import', [App\Http\Controllers\RuangController::class, 'template']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // User management
    Route::get('/users', [AuthController::class, 'index']);
    Route::post('/users', [AuthController::class, 'register']);
    Route::put('/users/{id}', [AuthController::class, 'update']);
    Route::delete('/users/{id}', [AuthController::class, 'destroy']);

    // Jadwal Ujian management
    Route::post('/jadwal-ujian/import', [JadwalUjianController::class, 'import']);
    Route::apiResource('jadwal-ujian', JadwalUjianController::class);

    // Ujian (Event) management
    Route::apiResource('ujians', UjianController::class);

    // Pengawas management
    Route::post('/pengawas/import', [PengawasController::class, 'import']);
    Route::apiResource('pengawas', PengawasController::class);
    Route::apiResource('tahun-ajaran', \App\Http\Controllers\TahunAjaranController::class);

    Route::post('/ruang/import', [App\Http\Controllers\RuangController::class, 'import']);
    Route::apiResource('ruang', App\Http\Controllers\RuangController::class);

    Route::post('/peserta-ujian/import', [\App\Http\Controllers\PesertaUjianController::class, 'importCsv']);
    Route::apiResource('peserta-ujian', \App\Http\Controllers\PesertaUjianController::class);
    Route::get('/peserta-ujian-meta', [\App\Http\Controllers\PesertaUjianController::class, 'meta']);
});

Route::get('/dashboard/attendance-stats', [DashboardController::class, 'attendanceStats']);
Route::get('/dashboard/attendance-by-campus', [DashboardController::class, 'attendanceByCampus']);
Route::get('/dashboard/attendance-by-class', [DashboardController::class, 'attendanceByClass']);
Route::get('/dashboard/attendance-students', [DashboardController::class, 'attendanceStudents']);
Route::get('/init-data', [ExamReportController::class, 'getInitData']);
Route::post('/submit-report', [ExamReportController::class, 'store']);
Route::post('/scan-peserta', [ExamReportController::class, 'scanPeserta']);
Route::get('/presensi-today', [ExamReportController::class, 'getPresensiToday']);
Route::get('/get-assignment', [ExamReportController::class, 'getAssignment']);
Route::post('/login-niy', [ExamReportController::class, 'loginNiy']);

// Pengawas auth routes (Sanctum token)
Route::middleware('auth:pengawas')->group(function () {
    Route::get('/pengawas-auth/me', function (Request $request) {
        $pengawas = $request->user('pengawas');
        $presensi = \App\Models\PresensiPengawas::where('pengawas_id', $pengawas->id)
            ->whereDate('created_at', today())
            ->first();
        return response()->json([
            'user' => $pengawas,
            'presensi' => $presensi,
        ]);
    });

    Route::post('/pengawas-auth/logout', function (Request $request) {
        $request->user('pengawas')->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil']);
    });
});

Route::get('/health-check', [ExamReportController::class, 'healthCheck']);

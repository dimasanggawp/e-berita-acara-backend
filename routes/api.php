<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExamReportController;
use App\Http\Controllers\JadwalUjianController;
use App\Http\Controllers\UjianController;
use App\Http\Controllers\PengawasController;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/pengawas/template-import', [PengawasController::class, 'template']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // User management
    Route::get('/users', [AuthController::class, 'index']);
    Route::post('/users', [AuthController::class, 'register']);
    Route::put('/users/{id}', [AuthController::class, 'update']);
    Route::delete('/users/{id}', [AuthController::class, 'destroy']);

    // Jadwal Ujian management
    Route::apiResource('jadwal-ujian', JadwalUjianController::class);

    // Ujian (Event) management
    Route::apiResource('ujians', UjianController::class);

    // Pengawas management
    Route::post('/pengawas/import', [PengawasController::class, 'import']);
    Route::apiResource('pengawas', PengawasController::class);
});

Route::get('/init-data', [ExamReportController::class, 'getInitData']);
Route::post('/submit-report', [ExamReportController::class, 'store']);
Route::post('/scan-peserta', [ExamReportController::class, 'scanPeserta']);
Route::get('/presensi-today', [ExamReportController::class, 'getPresensiToday']);
Route::get('/get-assignment', [ExamReportController::class, 'getAssignment']);
Route::post('/login-niy', [ExamReportController::class, 'loginNiy']);
Route::get('/health-check', [ExamReportController::class, 'healthCheck']);

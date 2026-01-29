<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ExamReportController;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // User management
    Route::get('/users', [AuthController::class, 'index']);
    Route::post('/users', [AuthController::class, 'register']);
    Route::put('/users/{id}', [AuthController::class, 'update']);
    Route::delete('/users/{id}', [AuthController::class, 'destroy']);
});

Route::get('/init-data', [ExamReportController::class, 'getInitData']);
Route::post('/submit-report', [ExamReportController::class, 'store']);
Route::post('/scan-peserta', [ExamReportController::class, 'scanPeserta']);
Route::get('/presensi-today', [ExamReportController::class, 'getPresensiToday']);
Route::get('/get-assignment', [ExamReportController::class, 'getAssignment']);
Route::post('/login-niy', [ExamReportController::class, 'loginNiy']);
Route::get('/health-check', [ExamReportController::class, 'healthCheck']);

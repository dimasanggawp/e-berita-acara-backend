<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\ExamReportController;

Route::get('/init-data', [ExamReportController::class, 'getInitData']);
Route::post('/submit-report', [ExamReportController::class, 'store']);
Route::post('/scan-peserta', [ExamReportController::class, 'scanPeserta']);
Route::get('/presensi-today', [ExamReportController::class, 'getPresensiToday']);
Route::get('/get-assignment', [ExamReportController::class, 'getAssignment']);
Route::post('/login-niy', [ExamReportController::class, 'loginNiy']);

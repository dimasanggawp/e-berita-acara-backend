<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function attendanceStats(Request $request)
    {
        return response()->json(
            $this->dashboardService->getAttendanceStats(
                $request->query('ujian_id'),
                $request->query('date')
            )
        );
    }

    public function attendanceByCampus(Request $request)
    {
        return response()->json(
            $this->dashboardService->getAttendanceByCampus(
                $request->query('ujian_id'),
                $request->query('date')
            )
        );
    }

    public function attendanceByClass(Request $request)
    {
        return response()->json(
            $this->dashboardService->getAttendanceByClass(
                $request->query('ujian_id'),
                $request->query('date'),
                $request->query('kampus')
            )
        );
    }

    public function attendanceStudents(Request $request)
    {
        return response()->json(
            $this->dashboardService->getAttendanceStudents(
                $request->query('ujian_id'),
                $request->query('date'),
                $request->query('kelas'),
                $request->query('kampus')
            )
        );
    }
}

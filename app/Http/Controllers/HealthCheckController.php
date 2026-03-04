<?php

namespace App\Http\Controllers;

class HealthCheckController extends Controller
{
    public function __invoke()
    {
        try {
            \DB::connection()->getPdo();
            return response()->json([
                'status' => 'ok',
                'database' => 'connected',
                'details' => [
                    'engine' => \DB::connection()->getDriverName(),
                    'name' => \DB::connection()->getDriverName() === 'sqlite'
                        ? basename(\DB::connection()->getDatabaseName())
                        : \DB::connection()->getDatabaseName(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'database' => 'disconnected',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

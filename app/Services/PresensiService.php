<?php

namespace App\Services;

use App\Models\Pengawas;
use App\Models\PesertaUjian;
use App\Models\PresensiPengawas;
use App\Models\PresensiPeserta;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PresensiService
{
    /**
     * Handle scan for Peserta or Pengawas.
     *
     * @param string $kodePeserta
     * @param int|null $ujianId
     * @return array
     * @throws \Exception
     */
    public function handleScanPeserta(string $kodePeserta, ?int $ujianId): array
    {
        $today = now()->startOfDay();
        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                return DB::transaction(function () use ($kodePeserta, $ujianId, $today) {
                    // Check if scanned code belongs to Pengawas
                    $pengawas = Pengawas::where('niy', $kodePeserta)->first();
                    if ($pengawas) {
                        return $this->processPengawasAttendance($pengawas, $today);
                    }

                    // Validate if kode_peserta exists in peserta_ujians table
                    $pesertaExists = PesertaUjian::where('nomor_peserta', $kodePeserta)->exists();
                    if (!$pesertaExists) {
                        return [
                            'status' => 404,
                            'data' => ['message' => "Kode Peserta [{$kodePeserta}] tidak terdaftar."]
                        ];
                    }

                    // Process Peserta Attendance
                    return $this->processPesertaAttendance($kodePeserta, $ujianId, $today);
                });
            } catch (\Illuminate\Database\QueryException $e) {
                // Retry on deadlock (error code 40001)
                if ($e->getCode() == 40001 && $attempt < $maxRetries - 1) {
                    $attempt++;
                    usleep(100000 * $attempt); // Exponential backoff: 100ms, 200ms
                    continue;
                }
                throw $e;
            }
        }

        throw new \Exception('Terjadi kesalahan sistem. Silakan coba lagi.');
    }

    /**
     * Handle login via NIY.
     *
     * @param string $niy
     * @return array
     * @throws \Exception
     */
    public function handleLoginNiy(string $niy): array
    {
        $pengawas = Pengawas::where('niy', $niy)->first();

        if (!$pengawas) {
            return [
                'status' => 404,
                'data' => ['message' => "NIY [{$niy}] tidak terdaftar"]
            ];
        }

        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $presensi = DB::transaction(function () use ($pengawas) {
                    $today = Carbon::today();
                    return $this->recordPengawasAttendance($pengawas, $today);
                });

                return [
                    'status' => 200,
                    'data' => [
                        'message' => 'Login berhasil',
                        'user' => $pengawas,
                        'presensi' => $presensi
                    ]
                ];
            } catch (\Illuminate\Database\QueryException $e) {
                // Retry on deadlock (error code 40001)
                if ($e->getCode() == 40001 && $attempt < $maxRetries - 1) {
                    $attempt++;
                    usleep(100000 * $attempt); // Exponential backoff
                    continue;
                }
                throw $e;
            }
        }

        throw new \Exception('Terjadi kesalahan sistem. Silakan coba lagi.');
    }

    /**
     * Process attendance logic for Pengawas.
     */
    private function processPengawasAttendance(Pengawas $pengawas, $today): array
    {
        $presensiPengawas = $this->recordPengawasAttendance($pengawas, $today);

        return [
            'status' => 200,
            'data' => [
                'type' => 'pengawas',
                'message' => "Presensi Pengawas [{$pengawas->name}] tercatat.",
                'presensi' => $presensiPengawas
            ]
        ];
    }

    /**
     * Process attendance logic for Peserta.
     */
    private function processPesertaAttendance(string $kodePeserta, ?int $ujianId, $today): array
    {
        // Require pessimistic locking to prevent race conditions map multiple parallel requests
        $presensi = PresensiPeserta::where('kode_peserta', $kodePeserta)
            ->whereDate('created_at', $today)
            ->lockForUpdate()
            ->first();

        if (!$presensi) {
            try {
                $presensi = PresensiPeserta::create([
                    'kode_peserta' => $kodePeserta,
                    'ujian_id' => $ujianId ?? null,
                    'waktu_datang' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle duplicate entry error (race condition caught by unique constraint)
                if ($e->getCode() == 23000) {
                    $presensi = PresensiPeserta::where('kode_peserta', $kodePeserta)
                        ->whereDate('created_at', $today)
                        ->first();
                } else {
                    throw $e;
                }
            }
            return [
                'status' => 200,
                'data' => ['type' => 'peserta', 'message' => 'Waktu datang peserta tercatat', 'data' => $presensi]
            ];
        } else {
            // Already clocked in, clock out if not yet clocked out
            if (empty($presensi->waktu_pulang)) {
                $presensi->update(['waktu_pulang' => now()]);
                return [
                    'status' => 200,
                    'data' => ['type' => 'peserta', 'message' => 'Waktu pulang peserta tercatat', 'data' => $presensi]
                ];
            } else {
                return [
                    'status' => 400,
                    'data' => ['type' => 'peserta', 'message' => 'Peserta sudah melakukan scan datang dan pulang hari ini', 'data' => $presensi]
                ];
            }
        }
    }

    /**
     * Create or update attendance record for Pengawas.
     */
    private function recordPengawasAttendance(Pengawas $pengawas, $today): PresensiPengawas
    {
        $presensi = PresensiPengawas::where('pengawas_id', $pengawas->id)
            ->whereDate('created_at', $today)
            ->lockForUpdate()
            ->first();

        if (!$presensi) {
            try {
                $presensi = PresensiPengawas::create([
                    'pengawas_id' => $pengawas->id,
                    'waktu_datang' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() == 23000) {
                    $presensi = PresensiPengawas::where('pengawas_id', $pengawas->id)
                        ->whereDate('created_at', $today)
                        ->first();
                } else {
                    throw $e;
                }
            }
        } else {
            // Only clock out if 5 minutes have passed since clock in (avoid double scans)
            if ($presensi->created_at->diffInMinutes(now()) >= 5) {
                $presensi->update(['waktu_pulang' => now()]);
            }
        }

        return $presensi;
    }
}

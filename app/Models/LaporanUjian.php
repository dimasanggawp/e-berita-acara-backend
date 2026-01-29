<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaporanUjian extends Model
{
    protected $table = 'laporan_ujians';
    protected $guarded = [];

    public function ujian()
    {
        return $this->belongsTo(Ujian::class);
    }

    public function ruang()
    {
        return $this->belongsTo(Ruang::class);
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class, 'mapel_id');
    }

    public function sesi()
    {
        return $this->belongsTo(Sesi::class, 'sesi_id');
    }
    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }
}

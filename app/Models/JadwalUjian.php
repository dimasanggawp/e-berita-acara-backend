<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalUjian extends Model
{
    protected $guarded = [];

    public function ujian()
    {
        return $this->belongsTo(Ujian::class);
    }

    public function pengawas()
    {
        return $this->belongsTo(Pengawas::class);
    }

    public function pengawasPengganti()
    {
        return $this->belongsTo(Pengawas::class, 'pengawas_pengganti_id');
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class, 'mapel_id');
    }

    public function pesertaUjians()
    {
        // Link students based on room if needed, or keep the pivot
        return $this->belongsToMany(PesertaUjian::class, 'jadwal_peserta', 'jadwal_ujian_id', 'peserta_ujian_id');
    }
}

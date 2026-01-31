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



    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class, 'mapel_id');
    }

    public function pesertaUjians()
    {
        return $this->belongsToMany(PesertaUjian::class, 'jadwal_peserta', 'jadwal_ujian_id', 'peserta_ujian_id');
    }
}

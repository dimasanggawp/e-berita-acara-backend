<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesertaUjian extends Model
{
    protected $guarded = [];




    public function ujian()
    {
        return $this->belongsTo(Ujian::class);
    }

    public function ruang()
    {
        return $this->belongsTo(Ruang::class, 'ruang_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function jadwalUjians()
    {
        return $this->belongsToMany(JadwalUjian::class, 'jadwal_peserta', 'peserta_ujian_id', 'jadwal_ujian_id');
    }
}

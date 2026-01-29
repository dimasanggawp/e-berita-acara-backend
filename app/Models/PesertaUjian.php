<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesertaUjian extends Model
{
    protected $guarded = [];
    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function jadwalUjians()
    {
        return $this->belongsToMany(JadwalUjian::class, 'jadwal_peserta', 'peserta_ujian_id', 'jadwal_ujian_id');
    }
}

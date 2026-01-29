<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PresensiPengawas extends Model
{
    use HasFactory;

    protected $table = 'presensi_pengawas';
    protected $fillable = ['pengawas_id', 'waktu_datang', 'waktu_pulang'];

    public function pengawas()
    {
        return $this->belongsTo(Pengawas::class);
    }
}

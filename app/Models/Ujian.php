<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ujian extends Model
{
    protected $guarded = [];

    public function jadwalUjians()
    {
        return $this->hasMany(JadwalUjian::class);
    }
}

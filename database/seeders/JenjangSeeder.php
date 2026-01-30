<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JenjangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jenjangs = ['X', 'XI', 'XII'];
        foreach ($jenjangs as $j) {
            \App\Models\Jenjang::create(['nama_jenjang' => $j]);
        }
    }
}

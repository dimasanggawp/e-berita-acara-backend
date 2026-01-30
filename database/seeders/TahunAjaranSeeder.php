<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TahunAjaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $years = [
            ['tahun' => '2023/2024', 'is_active' => false],
            ['tahun' => '2024/2025', 'is_active' => true],
            ['tahun' => '2025/2026', 'is_active' => false],
        ];

        foreach ($years as $y) {
            \App\Models\TahunAjaran::create($y);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RombelTingkatSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('rombel')->truncate();
        DB::table('tingkat')->truncate();

        // Create Tingkat (Kelas 1-6)
        $tingkatData = [];
        for ($i = 1; $i <= 6; $i++) {
            $tingkatData[] = [
                'level' => $i,
                'name' => "Kelas $i",
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('tingkat')->insert($tingkatData);

        // Create Rombel (A, B, C) for each Tingkat
        $tingkats = DB::table('tingkat')->get();
        $rombelData = [];

        foreach ($tingkats as $tingkat) {
            foreach (['A', 'B', 'C'] as $rombelName) {
                $rombelData[] = [
                    'kelas_id' => $tingkat->id,
                    'name' => $rombelName,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('rombel')->insert($rombelData);

        $this->command->info('Seeded 6 Tingkat and 18 Rombel (3 per Tingkat)');
    }
}

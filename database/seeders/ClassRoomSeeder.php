<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClassRoom;

class ClassRoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classRooms = [
            ['name' => 'Kelas 1A', 'grade' => '1'],
            ['name' => 'Kelas 1B', 'grade' => '1'],
            ['name' => 'Kelas 2A', 'grade' => '2'],
            ['name' => 'Kelas 2B', 'grade' => '2'],
            ['name' => 'Kelas 3A', 'grade' => '3'],
            ['name' => 'Kelas 3B', 'grade' => '3'],
            ['name' => 'Kelas 4A', 'grade' => '4'],
            ['name' => 'Kelas 4B', 'grade' => '4'],
            ['name' => 'Kelas 5A', 'grade' => '5'],
            ['name' => 'Kelas 5B', 'grade' => '5'],
            ['name' => 'Kelas 6A', 'grade' => '6'],
            ['name' => 'Kelas 6B', 'grade' => '6'],
        ];

        foreach ($classRooms as $classRoom) {
            ClassRoom::firstOrCreate(['name' => $classRoom['name']], $classRoom);
        }
    }
}

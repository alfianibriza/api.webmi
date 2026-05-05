<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Admin User
        $admin = User::where('email', 'admin@mi-alghazali.sch.id')->first();
        if ($admin) {
            $admin->update(['role' => 'admin']);
        } else {
            User::factory()->create([
                'name' => 'Admin MI Al-Ghazali',
                'email' => 'admin@mi-alghazali.sch.id',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]);
        }

        // Guru User
        $guru = User::where('email', 'guru@mi-alghazali.sch.id')->first();
        if (!$guru) {
            User::factory()->create([
                'name' => 'Guru MI Al-Ghazali',
                'email' => 'guru@mi-alghazali.sch.id',
                'password' => bcrypt('password'),
                'role' => 'guru',
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProfileSection;
use App\Models\PhilosophyItem;

class ProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed Profile Sections
        $sections = [
            [
                'key' => 'sejarah',
                'title' => 'Sejarah',
                'content' => 'MI Al-Ghazali didirikan pada tahun 1963...',
                'image' => null,
            ],
            [
                'key' => 'proker',
                'title' => 'Proker Kepala',
                'content' => 'Program kerja kepala sekolah...',
                'image' => null,
            ],
            [
                'key' => 'visi',
                'title' => 'Visi',
                'content' => 'Terwujudnya Generasi Qurani...',
                'image' => null,
            ],
            [
                'key' => 'misi',
                'title' => 'Misi',
                'content' => '1. Melaksanakan pembelajaran...',
                'image' => null,
            ],
        ];

        foreach ($sections as $section) {
            ProfileSection::firstOrCreate(['key' => $section['key']], $section);
        }

        // Seed Philosophy Items
        $philosophies = [
            [
                'title' => 'Kubah Masjid',
                'description' => 'Melambangkan nilai-nilai keislaman...',
                'image' => 'kuba.png',
                'order' => 1,
            ],
            [
                'title' => 'Pena',
                'description' => 'Melambangkan semangat literasi...',
                'image' => 'pena.png',
                'order' => 2,
            ],
            [
                'title' => 'Kitab',
                'description' => 'Melambangkan Al-Qur\'an dan Hadits...',
                'image' => 'kitab.png',
                'order' => 3,
            ],
            [
                'title' => 'Pita',
                'description' => 'Bertuliskan tahun berdirinya madrasah...',
                'image' => 'pita.png',
                'order' => 4,
            ],
        ];

        foreach ($philosophies as $item) {
            PhilosophyItem::firstOrCreate(['title' => $item['title']], $item);
        }
    }
}

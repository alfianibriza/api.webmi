<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tingkat extends Model
{
    use HasFactory;

    protected $table = 'tingkat';

    protected $fillable = [
        'level',
        'name',
    ];

    // Relationship: Tingkat has many Rombel (Detail Kelas)
    public function rombel()
    {
        return $this->hasMany(Rombel::class, 'kelas_id');
    }

    // Relationship: Tingkat has many Kelas (Kelas Aktif)
    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'tingkat_id');
    }
}

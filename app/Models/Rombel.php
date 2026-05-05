<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Rombel extends Model
{
    use HasFactory;

    protected $table = 'rombel';

    protected $fillable = [
        'kelas_id',
        'name',
        'status',
    ];

    // Relationship: Rombel belongs to Tingkat (parent Kelas)
    public function tingkat()
    {
        return $this->belongsTo(Tingkat::class, 'kelas_id');
    }

    // Relationship: Rombel has many Kelas (Kelas Aktif)
    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'rombel_id');
    }
}

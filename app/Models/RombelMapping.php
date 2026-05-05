<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RombelMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'old_rombel_id',
        'new_rombel_id',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function oldRombel()
    {
        return $this->belongsTo(ClassRoom::class, 'old_rombel_id');
    }

    public function newRombel()
    {
        return $this->belongsTo(ClassRoom::class, 'new_rombel_id');
    }
}

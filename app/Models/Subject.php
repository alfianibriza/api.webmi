<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
    ];

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'subject_teacher')
            ->withTimestamps();
    }

    public function classRooms()
    {
        return $this->belongsToMany(ClassRoom::class, 'class_subjects')
            ->withPivot('weekly_hours')
            ->withTimestamps();
    }

    public function slotSchedules()
    {
        return $this->hasMany(SlotSchedule::class);
    }
}

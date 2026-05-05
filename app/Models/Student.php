<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'nis',
        'plain_password',
        'nisn',
        'gender',
        'grade',
        'class_room_id',
        'status',
        'graduation_year',
        'admission_year',
        'birth_place',
        'birth_date',
        'birth_date',
        'parent_name', // Kept for legacy/fallback
        'father_name',
        'mother_name',
        'parent_phone',
        'parent_user_id',
        'address',
        'kelas_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function attendances()
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function academicHistories()
    {
        return $this->hasMany(StudentAcademicHistory::class);
    }

    // Relationship: Student belongs to many Classes (History)
    public function classrooms()
    {
        return $this->belongsToMany(Kelas::class, 'classroom_student', 'student_id', 'kelas_id')
            ->withPivot('status')
            ->withTimestamps();
    }

    // Helper: Get Active Class for a specific or current academic year
    // Note: This requires joining with academic_years if we want "current active year" dynamically
    public function currentClass($academicYearId = null)
    {
        return $this->classrooms()
            ->where('classroom_student.status', 'active')
            ->whereHas('academicYear', function ($query) use ($academicYearId) {
                if ($academicYearId) {
                    $query->where('id', $academicYearId);
                } else {
                    $query->where('status', 'active');
                }
            })
            ->first();
    }

    protected $appends = ['kelas'];

    // Legacy relationship accessor (compatibility)
    public function getKelasAttribute()
    {
        // Optimization: If classrooms are eager loaded, pick the active one from memory
        if ($this->relationLoaded('classrooms')) {
            return $this->classrooms->first(function ($classroom) {
                return $classroom->pivot && $classroom->pivot->status === 'active';
            });
        }

        return $this->currentClass();
    }
}


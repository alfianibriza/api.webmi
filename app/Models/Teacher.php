<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'nip',
        'gender',
        'birth_place',
        'birth_date',
        'address',
        'position',
        'status',
        'image',
        'plain_password',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher')
            ->withTimestamps();
    }

    public function slotSchedules()
    {
        return $this->hasMany(SlotSchedule::class);
    }

    public function constraints()
    {
        return $this->hasMany(TeacherClassConstraint::class);
    }

    public function swapRequestsAsRequester()
    {
        return $this->hasMany(SwapRequest::class, 'requester_teacher_id');
    }

    public function swapRequestsAsTarget()
    {
        return $this->hasMany(SwapRequest::class, 'target_teacher_id');
    }
}


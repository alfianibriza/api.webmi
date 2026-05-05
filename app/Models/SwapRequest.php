<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SwapRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'requester_teacher_id',
        'target_teacher_id',
        'schedule_id_1',
        'schedule_id_2',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function requesterTeacher()
    {
        return $this->belongsTo(Teacher::class, 'requester_teacher_id');
    }

    public function targetTeacher()
    {
        return $this->belongsTo(Teacher::class, 'target_teacher_id');
    }

    public function scheduleOne()
    {
        return $this->belongsTo(SlotSchedule::class, 'schedule_id_1');
    }

    public function scheduleTwo()
    {
        return $this->belongsTo(SlotSchedule::class, 'schedule_id_2');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}

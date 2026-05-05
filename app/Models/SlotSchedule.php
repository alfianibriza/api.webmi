<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SlotSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_room_id',
        'subject_id',
        'teacher_id',
        'day',
        'slot_number',
        'status',
        'generated_by',
    ];

    public function classRoom()
    {
        return $this->belongsTo(Kelas::class, 'class_room_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function swapRequestsAsFirst()
    {
        return $this->hasMany(SwapRequest::class, 'schedule_id_1');
    }

    public function swapRequestsAsSecond()
    {
        return $this->hasMany(SwapRequest::class, 'schedule_id_2');
    }
}

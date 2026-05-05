<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClassDaySlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_room_id',
        'day',
        'total_slots',
    ];

    public function classRoom()
    {
        return $this->belongsTo(Kelas::class, 'class_room_id');
    }
}

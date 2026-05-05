<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'title',
        'grade',
        'type',
        'file_path',
        'class_room_id',
        'description',
    ];

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class);
    }
}

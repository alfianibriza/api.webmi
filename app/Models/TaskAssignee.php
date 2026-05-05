<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskAssignee extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'status',
        'submitted_at',
        'completed_at',
        'submission_content',
        'submission_file',
        'admin_feedback',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['submission_file_url'];

    public function getSubmissionFileUrlAttribute()
    {
        return $this->submission_file ? asset('storage/' . $this->submission_file) : null;
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function startUser() // Renamed purely to avoid collision if necessary, but 'user' is standard
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

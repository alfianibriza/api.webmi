<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date', // Keeping date for easier querying
        'type', // masuk, keluar
        'status', // pending, approved, rejected
        'photo',
        'latitude',
        'longitude',
        'location_status', // valid, invalid
        'reason',
        'admin_note',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

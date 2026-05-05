<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PpdbInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'image',
        'brochure_link',
        'is_active',
    ];
}

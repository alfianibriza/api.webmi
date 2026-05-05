<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ppdb extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'nisn',
        'gender',
        'birth_place',
        'birth_date',
        'parent_name',
        'phone',
        'address',
        'status',
    ];
}

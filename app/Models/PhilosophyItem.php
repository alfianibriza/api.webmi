<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhilosophyItem extends Model
{
  use HasFactory;

  protected $fillable = [
    'title',
    'description',
    'image',
    'order',
  ];
}

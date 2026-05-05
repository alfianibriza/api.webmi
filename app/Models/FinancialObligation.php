<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialObligation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'amount',
        'due_date',
        'description',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function studentObligations()
    {
        return $this->hasMany(StudentObligation::class);
    }
}

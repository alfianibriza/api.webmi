<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentObligation extends Model
{
    use HasFactory;

    protected $fillable = [
        'financial_obligation_id',
        'student_id',
        'status',
        'proof_image',
        'amount_paid',
        'paid_at',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
        'amount_paid' => 'decimal:2',
    ];

    public function financialObligation()
    {
        return $this->belongsTo(FinancialObligation::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}

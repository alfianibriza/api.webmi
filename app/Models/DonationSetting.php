<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonationSetting extends Model
{
    protected $fillable = [
        'bank_name',
        'account_number',
        'account_holder',
        'wa_number',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterestRate extends Model
{
    protected $fillable = [
        'effective_date',
        'index_name',
        'value',
    ];
}
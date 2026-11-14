<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CandidateMasterRecord extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'row_data' => 'array',
    ];
}

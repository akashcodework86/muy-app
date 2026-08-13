<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomestaySurveyResponse extends Model
{
    protected $fillable = [
        'phone',
        'phase',
        'source_id',
        'application_no',
        'applicant_name',
        'district',
        'prefill_snapshot',
        'answers',
        'ip_address',
        'user_agent',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'prefill_snapshot' => 'array',
            'answers' => 'array',
            'submitted_at' => 'datetime',
        ];
    }
}

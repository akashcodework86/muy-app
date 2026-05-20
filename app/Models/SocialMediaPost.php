<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMediaPost extends Model
{
    protected $fillable = [
        'submitted_by_user_id',
        'submitted_by_name',
        'posted_on',
        'post_url',
        'platform',
        'thumbnail_url',
        'preview_title',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'posted_on' => 'date',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}

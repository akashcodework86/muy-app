<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StateTaskAttachment extends Model
{
    protected $fillable = [
        'state_task_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(StateTask::class, 'state_task_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function deleteFileIfLocal(): void
    {
        if ($this->disk === 'local' && $this->path !== '') {
            Storage::disk('local')->delete($this->path);
        }
    }
}

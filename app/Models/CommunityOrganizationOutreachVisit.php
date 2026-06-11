<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityOrganizationOutreachVisit extends Model
{
    protected $fillable = [
        'hub_id',
        'hub_name',
        'district_id',
        'district_name',
        'visit_date',
        'organization_name',
        'organization_type',
        'organization_type_other',
        'person_met_name',
        'person_met_designation',
        'poc_name',
        'poc_phone',
        'poc_email',
        'purpose',
        'meeting_mode',
        'outcome',
        'remarks',
        'documents_json',
        'photos_json',
        'follow_up_required',
        'follow_up_date',
        'submitted_by_user_id',
        'submitted_by_name',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'follow_up_date' => 'date',
            'follow_up_required' => 'boolean',
            'documents_json' => 'array',
            'photos_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (CommunityOrganizationOutreachVisit $visit): void {
            $visit->deleteStoredFiles();
        });
    }

    public function deleteStoredFiles(): void
    {
        foreach (['documents_json', 'photos_json'] as $column) {
            foreach ((array) $this->{$column} as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $path = (string) ($item['path'] ?? '');
                if ($path !== '' && \Illuminate\Support\Facades\Storage::exists($path)) {
                    \Illuminate\Support\Facades\Storage::delete($path);
                }
            }
        }
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}

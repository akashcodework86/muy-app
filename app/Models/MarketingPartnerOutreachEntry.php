<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MarketingPartnerOutreachEntry extends Model
{
    public const STATUS_OUTREACH = 'outreach';

    public const STATUS_IN_DISCUSSION = 'in_discussion';

    public const STATUS_ONBOARDED_LOA = 'onboarded_loa';

    public const STATUS_ONBOARDED_LOI = 'onboarded_loi';

    public const STATUS_ONBOARDED_MOU = 'onboarded_mou';

    public const STATUS_DECLINED = 'declined';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_OUTREACH,
        self::STATUS_IN_DISCUSSION,
        self::STATUS_ONBOARDED_LOA,
        self::STATUS_ONBOARDED_LOI,
        self::STATUS_ONBOARDED_MOU,
        self::STATUS_DECLINED,
    ];

    /** @var list<string> */
    public const ONBOARDED_STATUSES = [
        self::STATUS_ONBOARDED_LOA,
        self::STATUS_ONBOARDED_LOI,
        self::STATUS_ONBOARDED_MOU,
    ];

    protected $fillable = [
        'outreach_date',
        'partner_name',
        'partner_designation',
        'partner_link',
        'cohort_or_sector',
        'cohort_or_sector_other',
        'poc_name',
        'poc_phone',
        'poc_email',
        'remarks',
        'status',
        'onboarding_date',
        'agreement_document_disk',
        'agreement_document_path',
        'agreement_document_original_name',
        'submitted_by_user_id',
        'submitted_by_name',
        'status_updated_by_user_id',
        'status_updated_by_name',
        'status_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'outreach_date' => 'date',
            'onboarding_date' => 'date',
            'status_updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (MarketingPartnerOutreachEntry $entry): void {
            $entry->deleteStoredAgreementDocument();
        });
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function statusUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_updated_by_user_id');
    }

    public function isOnboarded(): bool
    {
        return in_array((string) $this->status, self::ONBOARDED_STATUSES, true);
    }

    public function countsForOutreachDeliverable(): bool
    {
        return in_array((string) $this->status, self::STATUSES, true);
    }

    public function countsForOnboardedDeliverable(): bool
    {
        return $this->isOnboarded();
    }

    public function hasAgreementDocument(): bool
    {
        return is_string($this->agreement_document_path) && $this->agreement_document_path !== '';
    }

    public function deleteStoredAgreementDocument(): void
    {
        $path = (string) ($this->agreement_document_path ?? '');
        if ($path === '') {
            return;
        }

        $disk = (string) ($this->agreement_document_disk ?: config('filesystems.default'));
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}

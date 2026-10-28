<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerSellerMeetParticipant extends Model
{
    public const KIND_LINKED = 'linked';

    public const KIND_MANUAL = 'manual';

    public const ROLE_BUYER = 'buyer';

    public const ROLE_SELLER = 'seller';

    protected $fillable = [
        'buyer_seller_meet_entry_id',
        'participant_kind',
        'role',
        'incubatee_key',
        'cfa_submission_id',
        'legacy_application_id',
        'legacy_phase1_application_id',
        'participant_name',
        'application_no',
        'phone',
        'sort_order',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(BuyerSellerMeetEntry::class, 'buyer_seller_meet_entry_id');
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_BUYER => 'Buyer',
            self::ROLE_SELLER => 'Seller',
            default => ucfirst((string) $this->role),
        };
    }
}

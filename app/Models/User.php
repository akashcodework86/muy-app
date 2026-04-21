<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'legacy_user_id',
        'cfa_submission_id',
        'name',
        'email',
        'phone',
        'avatar_path',
        'password',
        'role',
        'designation_id',
        'hub_id',
        'district_id',
        'referral_token',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function designationRecord(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function staffMonthlyTargets(): HasMany
    {
        return $this->hasMany(StaffMonthlyTarget::class);
    }

    public function cfaSubmissions(): HasMany
    {
        return $this->hasMany(CfaSubmission::class, 'referral_user_id');
    }

    public function cfaSubmission(): BelongsTo
    {
        return $this->belongsTo(CfaSubmission::class);
    }

    /**
     * Districts this user is SPOC for (only meaningful when role === 'state_staff').
     */
    public function spocDistrictAssignments(): HasMany
    {
        return $this->hasMany(DistrictServiceSpoc::class, 'state_staff_user_id');
    }

    public function referralApplyUrl(): ?string
    {
        if (! $this->referral_token) {
            return null;
        }

        return route('cfa.apply', ['token' => $this->referral_token]);
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        $version = $this->updated_at?->getTimestamp() ?? $this->id;

        if (auth()->check()) {
            // Self: use the self route (cheaper, no model binding).
            if ((int) auth()->id() === (int) $this->id) {
                return route('account.avatar.show', ['v' => $version]);
            }

            // Other users: stream through an auth-gated route so images work
            // even when the public/storage symlink is missing on the server.
            return route('account.user.avatar.show', [
                'user' => $this->id,
                'v' => $version,
            ]);
        }

        // Unauthenticated fallback (rarely used): public disk URL, requires storage:link.
        return Storage::disk('public')->url($this->avatar_path);
    }
}

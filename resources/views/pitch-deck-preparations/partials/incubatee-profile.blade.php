@php
    $profile = $profile ?? [];
    $dash = static function ($value): string {
        $v = trim((string) ($value ?? ''));

        return $v !== '' ? $v : '—';
    };
@endphp
<div class="pdp-profile">
    <div class="pdp-profile__badges">
        @if (!empty($profile['is_onboarded']))
            <span class="pdp-pill pdp-pill--ok">Onboarded</span>
        @elseif (($profile['onboarding_status'] ?? '') === '—')
            <span class="pdp-pill pdp-pill--muted">—</span>
        @else
            <span class="pdp-pill pdp-pill--muted">Not onboarded</span>
        @endif
        @if (!empty($profile['source']))
            <span class="pdp-pill">{{ $profile['source'] }}</span>
        @endif
    </div>
    <h4 class="pdp-profile__title">{{ $profile['name'] ?? 'Incubatee' }}</h4>
    <div class="pdp-profile__grid">
        <div class="pdp-profile__item">
            <div class="pdp-profile__label">Application no.</div>
            <div class="pdp-profile__value">{{ $dash($profile['application_no'] ?? '') }}</div>
        </div>
        <div class="pdp-profile__item">
            <div class="pdp-profile__label">Phone</div>
            <div class="pdp-profile__value">{{ $dash($profile['phone'] ?? '') }}</div>
        </div>
        <div class="pdp-profile__item">
            <div class="pdp-profile__label">District</div>
            <div class="pdp-profile__value">{{ $dash($profile['district'] ?? '') }}</div>
        </div>
        <div class="pdp-profile__item">
            <div class="pdp-profile__label">Hub</div>
            <div class="pdp-profile__value">{{ $dash($profile['hub'] ?? '') }}</div>
        </div>
        <div class="pdp-profile__item">
            <div class="pdp-profile__label">Block</div>
            <div class="pdp-profile__value">{{ $dash($profile['block'] ?? '') }}</div>
        </div>
        <div class="pdp-profile__item">
            <div class="pdp-profile__label">Village</div>
            <div class="pdp-profile__value">{{ $dash($profile['village'] ?? '') }}</div>
        </div>
        <div class="pdp-profile__item">
            <div class="pdp-profile__label">Gender</div>
            <div class="pdp-profile__value">{{ $dash($profile['gender'] ?? '') }}</div>
        </div>
        <div class="pdp-profile__item">
            <div class="pdp-profile__label">Business category</div>
            <div class="pdp-profile__value">{{ $dash($profile['business_category'] ?? '') }}</div>
        </div>
        <div class="pdp-profile__item">
            <div class="pdp-profile__label">Onboarding status</div>
            <div class="pdp-profile__value">{{ $dash($profile['onboarding_status'] ?? '') }}</div>
        </div>
        <div class="pdp-profile__item">
            <div class="pdp-profile__label">Onboarding batch</div>
            <div class="pdp-profile__value">{{ $dash($profile['onboarding_batch_name'] ?? '') }}</div>
        </div>
    </div>
</div>

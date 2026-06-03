@extends('layouts.admin')

@section('title', 'EAP / EDP session entry')
@section('heading', 'EAP / EDP sessions (combined)')

@push('styles')
<style>
    .tp-show-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .tp-show-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .tp-show-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.2rem 1.35rem; }
    .tp-show-card__title { margin:0 0 0.9rem; font-size:0.98rem; font-weight:800; color:#0f172a; }
    .tp-show-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:0.75rem 0.95rem; }
    .tp-show-field { min-width:0; }
    .tp-show-field--full { grid-column: 1 / -1; }
    .tp-show-field__label { display:block; font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.22rem; }
    .tp-show-field__value { font-size:0.9rem; font-weight:700; color:#0f172a; line-height:1.45; }
    .tp-show-actions { margin-top:1rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; }
    .tp-show-btn {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:0.55rem 0.95rem;
        border-radius:9px;
        font-size:0.84rem;
        font-weight:700;
        text-decoration:none;
        border:1px solid transparent;
    }
    .tp-show-btn--primary { background:#4f46e5; color:#fff; box-shadow:0 6px 16px rgba(79,70,229,0.18); }
    .tp-show-btn--primary:hover { background:#4338ca; color:#fff; }
    .tp-show-btn--secondary { background:#fff; color:#334155; border-color:#cbd5e1; }
    .tp-show-btn--secondary:hover { background:#f8fafc; }
    .tp-show-btn--danger { background:#fff; color:#b91c1c; border-color:#fecaca; }
    .tp-show-btn--danger:hover { background:#fef2f2; color:#991b1b; }
    .tp-show-delete-form { display:inline; }
    .ees-ws-pill {
        display:inline-flex;
        align-items:center;
        padding:0.22rem 0.6rem;
        border-radius:999px;
        font-size:0.78rem;
        font-weight:800;
        letter-spacing:0.02em;
    }
    .ees-ws-pill--virtual { background:#e0f2fe; color:#0369a1; border:1px solid #7dd3fc; }
    .ees-ws-pill--physical { background:#ecfdf5; color:#047857; border:1px solid #6ee7b7; }
    .ees-att-strip {
        display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center;
    }
    .ees-att-chip {
        display:inline-flex; align-items:center; gap:0.35rem;
        padding:0.35rem 0.65rem; border-radius:10px; font-size:0.84rem; font-weight:800;
        border:1px solid #e2e8f0; background:#f8fafc; color:#0f172a;
    }
    .ees-att-chip strong { color:#4f46e5; font-weight:900; }
</style>
@endpush

@section('content')
@php
    $attachmentRoute = match ($currentRole) {
        'state_admin' => 'admin.eap-edp-sessions.attachment',
        'state_staff' => 'spoc.eap-edp-sessions.attachment',
        default => 'staff.eap-edp-sessions.attachment',
    };
    $photoRoute = match ($currentRole) {
        'state_admin' => 'admin.eap-edp-sessions.photo',
        'state_staff' => 'spoc.eap-edp-sessions.photo',
        default => 'staff.eap-edp-sessions.photo',
    };
    $m = (int) ($row->attendance_male_count ?? 0);
    $f = (int) ($row->attendance_female_count ?? 0);
    $t = (int) ($row->attendance_total_count ?? ($m + $f));
    $firstParticipant = ($row->participantRows()[0] ?? []);
    $blockName = trim((string) ($firstParticipant['block_name'] ?? ''));
    $gpName = trim((string) ($firstParticipant['gram_panchayat_name'] ?? ''));
@endphp
<div class="tp-show-shell">
    @if (session('status'))
        <div class="tp-show-alert">
            {{ session('status') }}
        </div>
    @endif

    <div class="tp-show-card">
        <h3 class="tp-show-card__title">Event Details</h3>
        <div class="tp-show-grid">
            <div class="tp-show-field">
                <span class="tp-show-field__label">Date of Session</span>
                <span class="tp-show-field__value">{{ $row->event_date?->format('d M Y') ?: 'NA' }}</span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Session Taken By</span>
                <span class="tp-show-field__value">{{ $row->submitted_by_name }}</span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">District</span>
                <span class="tp-show-field__value">{{ $row->district_name ?: ($row->district?->name ?? 'NA') }}</span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Block</span>
                <span class="tp-show-field__value">{{ $blockName !== '' ? $blockName : '—' }}</span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Gram panchayat</span>
                <span class="tp-show-field__value">{{ $gpName !== '' ? $gpName : '—' }}</span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Workshop format</span>
                <span class="tp-show-field__value">
                    <span class="ees-ws-pill {{ ($row->workshop_mode ?? '') === 'virtual' ? 'ees-ws-pill--virtual' : 'ees-ws-pill--physical' }}">
                        {{ $row->formatted_workshop_mode }}
                    </span>
                </span>
            </div>
            <div class="tp-show-field tp-show-field--full">
                <span class="tp-show-field__label">Venue address</span>
                <span class="tp-show-field__value">{{ $row->display_venue ?: '—' }}</span>
            </div>
            <div class="tp-show-field tp-show-field--full">
                <span class="tp-show-field__label">Notes</span>
                <span class="tp-show-field__value">{{ $row->notes ?: '—' }}</span>
            </div>
            <div class="tp-show-field tp-show-field--full">
                <span class="tp-show-field__label">Attendance (headcount)</span>
                <span class="tp-show-field__value">
                    <span class="ees-att-strip">
                        <span class="ees-att-chip">Male <strong>{{ number_format($m) }}</strong></span>
                        <span class="ees-att-chip">Female <strong>{{ number_format($f) }}</strong></span>
                        <span class="ees-att-chip">Total <strong>{{ number_format($t) }}</strong></span>
                    </span>
                </span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Submitted At</span>
                <span class="tp-show-field__value">{{ $row->created_at?->format('d M Y h:i A') ?: 'NA' }}</span>
            </div>
        </div>
        <div class="tp-show-actions">
            <a class="tp-show-btn tp-show-btn--primary" href="{{ match ($currentRole) {
                'state_admin' => route('admin.eap-edp-sessions.export-single', $row),
                'state_staff' => route('spoc.eap-edp-sessions.export-single', $row),
                default => route('staff.eap-edp-sessions.export-single', $row),
            } }}">Excel Export</a>
            @if ($canEdit)
                <a class="tp-show-btn tp-show-btn--secondary" href="{{ route('staff.eap-edp-sessions.edit', $row) }}">Edit Entry</a>
                <form
                    class="tp-show-delete-form"
                    method="post"
                    action="{{ route('staff.eap-edp-sessions.destroy', $row) }}"
                    onsubmit="return confirm('Delete this EAP/EDP session entry permanently? This cannot be undone.');"
                >
                    @csrf
                    @method('delete')
                    <button type="submit" class="tp-show-btn tp-show-btn--danger">Delete Entry</button>
                </form>
            @endif
            <a class="tp-show-btn tp-show-btn--secondary" href="{{ match ($currentRole) {
                'state_admin' => route('admin.eap-edp-sessions.dashboard'),
                'state_staff' => route('spoc.eap-edp-sessions.dashboard'),
                default => route('staff.eap-edp-sessions.dashboard'),
            } }}">Back to dashboard</a>
        </div>
    </div>

    @include('staff.partials.workshop-participants.register-readonly', [
        'record' => $row,
        'participantRows' => $row->participantRows(),
        'title' => 'Participant register',
    ])

    <div class="tp-show-card">
        <h3 class="tp-show-card__title">Session photos</h3>
        @php
            $sessionPhotos = collect(is_array($row->session_photos_json ?? null) ? $row->session_photos_json : [])
                ->filter(fn ($p): bool => is_array($p) && (string) ($p['path'] ?? '') !== '')
                ->values();
        @endphp
        @if ($sessionPhotos->isEmpty())
            <p style="margin:0;color:#64748b;font-size:0.88rem;">No session photos uploaded.</p>
        @else
            <div class="ees-photo-existing-grid">
                @foreach ($sessionPhotos as $idx => $photo)
                    @php
                        $photoName = (string) ($photo['original_name'] ?? ('Photo '.($idx + 1)));
                        $viewQuery = array_filter(['index' => $idx > 0 ? $idx : null, 'inline' => 1]);
                        $viewUrl = route($photoRoute, $row).'?'.http_build_query($viewQuery);
                        $dlQuery = $idx > 0 ? ['index' => $idx] : [];
                        $downloadUrl = route($photoRoute, $row).($dlQuery !== [] ? '?'.http_build_query($dlQuery) : '');
                    @endphp
                    <button
                        type="button"
                        class="js-tt-media-open"
                        style="padding:0;border:none;background:none;"
                        data-view-url="{{ $viewUrl }}"
                        data-download-url="{{ $downloadUrl }}"
                        data-media-kind="image"
                        data-media-name="{{ $photoName }}"
                        aria-label="View {{ $photoName }}"
                    >
                        <img class="ees-photo-existing-thumb" src="{{ $viewUrl }}" alt="{{ $photoName }}" loading="lazy">
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    <div class="tp-show-card">
        <h3 class="tp-show-card__title">Attendance sheet (uploaded)</h3>
        @include('staff.technical-trainings.partials.attendance-media-preview', [
            'mediaItems' => (array) $row->attendance_media_json,
            'attachmentRoute' => $attachmentRoute,
            'record' => $row,
        ])
    </div>

    @include('staff.eap-edp-sessions.partials.photo-upload-scripts')
</div>
@endsection

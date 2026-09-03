@extends('layouts.admin')

@section('title', 'Training Package Entry')
@section('heading', 'Training Package Entry Details')

@push('styles')
<style>
    .tp-show-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .tp-show-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .tp-show-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.2rem 1.35rem; }
    .tp-show-card__title { margin:0 0 0.9rem; font-size:0.98rem; font-weight:800; color:#0f172a; }
    .tp-show-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:0.75rem 0.95rem; }
    .tp-show-field { min-width:0; }
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
    .tp-show-media { display:flex; flex-wrap:wrap; gap:0.65rem; }
    .tp-show-media img { width:140px; height:96px; object-fit:cover; border-radius:10px; border:1px solid #e2e8f0; }
    .tp-show-media a { display:inline-flex; align-items:center; padding:0.4rem 0.65rem; border:1px solid #cbd5e1; border-radius:8px; text-decoration:none; color:#334155; font-size:0.82rem; font-weight:600; }
    .tp-show-table-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:auto; }
    .tp-show-table-head { padding:0.95rem 1.1rem; border-bottom:1px solid #e2e8f0; }
    .tp-show-table-head h3 { margin:0; font-size:0.98rem; font-weight:800; color:#0f172a; }
    .tp-show-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
    .tp-show-table thead tr { background:#f8fafc; }
    .tp-show-table th,
    .tp-show-table td { text-align:left; padding:0.7rem 0.8rem; border-bottom:1px solid #e2e8f0; vertical-align:top; }
    .tp-show-table tbody tr:last-child td { border-bottom:none; }
    .tp-show-phone { display:inline-flex; align-items:center; gap:0.35rem; padding:0.18rem 0.5rem; border-radius:999px; background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8; font-weight:800; text-decoration:none; }
    .tp-show-phone:hover { background:#dbeafe; }
    .tp-show-source { display:inline-flex; align-items:center; padding:0.16rem 0.5rem; border-radius:999px; font-size:0.72rem; font-weight:800; }
    .tp-show-source--phase3 { background:#eef2ff; color:#3730a3; }
    .tp-show-source--legacy { background:#fff7ed; color:#9a3412; }
    .tp-show-empty { padding:1rem; color:#64748b; }
</style>
@endpush

@section('content')
@php
    $modules = (array) ($row->training_packages ?? [$row->training_package]);
    $moduleLabel = strtoupper(implode(', ', array_values(array_filter($modules))));
    $sessionName = $row->monthSession?->session_name ?: '—';
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
                <span class="tp-show-field__label">Session Name</span>
                <span class="tp-show-field__value">
                    {{ $sessionName }}
                    @if ($row->monthSession?->is_extra)
                        <span class="tp-show-source tp-show-source--legacy">Extra</span>
                    @endif
                </span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Training Modules</span>
                <span class="tp-show-field__value">{{ $moduleLabel !== '' ? $moduleLabel : 'NA' }}</span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Training Batch</span>
                <span class="tp-show-field__value">{{ $row->training_batch_name ?: '—' }}</span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Workshop format</span>
                <span class="tp-show-field__value">
                    @if (($row->workshop_delivery ?? '') === 'virtual')
                        Virtual workshop
                    @elseif (($row->workshop_delivery ?? '') === 'physical')
                        Physical workshop
                    @else
                        —
                    @endif
                </span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Status</span>
                <span class="tp-show-field__value">
                    @if ($row->isDraft())
                        <span class="tp-show-source tp-show-source--legacy">Draft — add incubatees until {{ \App\Models\TrainingPackage::MIN_ATTENDEES }} to submit</span>
                    @else
                        Submitted
                    @endif
                </span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Total Selected</span>
                <span class="tp-show-field__value">{{ is_array($row->selected_incubatee_ids) ? count($row->selected_incubatee_ids) : 0 }}</span>
            </div>
            <div class="tp-show-field">
                <span class="tp-show-field__label">Submitted At</span>
                <span class="tp-show-field__value">{{ $row->created_at?->format('d M Y h:i A') ?: 'NA' }}</span>
            </div>
        </div>
        <div class="tp-show-actions">
            <a class="tp-show-btn tp-show-btn--primary" href="{{ match ($currentRole) {
                'state_admin' => route('admin.training-packages.export-single', $row),
                'state_staff' => route('spoc.training-packages.export-single', $row),
                default => route('staff.training-packages.export-single', $row),
            } }}">Excel Export</a>
            @if ($canEdit)
                <a class="tp-show-btn tp-show-btn--secondary" href="{{ route('staff.training-packages.edit', $row) }}">{{ $row->isDraft() ? 'Continue Draft' : 'Edit Entry' }}</a>
            @endif
            <a class="tp-show-btn tp-show-btn--secondary" href="{{ match ($currentRole) {
                'state_admin' => route('admin.training-packages.dashboard'),
                'state_staff' => route('spoc.training-packages.dashboard'),
                default => route('staff.training-packages.dashboard'),
            } }}">Back to dashboard</a>
        </div>
    </div>

    <div class="tp-show-card">
        <h3 class="tp-show-card__title">Uploaded Photos / Video / Docs</h3>
        @if (is_array($row->attendance_media_json) && count($row->attendance_media_json))
            <div class="tp-show-media">
                @foreach ($row->attendance_media_json as $idx => $media)
                    @if (is_array($media))
                        @php
                            $mediaPath = (string) ($media['path'] ?? '');
                            $mediaMime = (string) ($media['mime'] ?? '');
                            $mediaName = (string) ($media['original_name'] ?? ('Media '.($idx + 1)));
                        @endphp
                        @if ($mediaPath !== '')
                            @if (str_starts_with($mediaMime, 'image/'))
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($mediaPath) }}" target="_blank" rel="noopener">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($mediaPath) }}" alt="{{ $mediaName }}">
                                </a>
                            @else
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($mediaPath) }}" target="_blank" rel="noopener">
                                    {{ $mediaName }}
                                </a>
                            @endif
                        @endif
                    @endif
                @endforeach
            </div>
        @else
            <p class="tp-show-empty">No media uploaded.</p>
        @endif
    </div>

    <div class="tp-show-table-card">
        <div class="tp-show-table-head">
            <h3>Selected Applicants</h3>
        </div>
        <table class="tp-show-table">
            <thead>
            <tr>
                <th>Sr. No.</th>
                <th>Source</th>
                <th>Name</th>
                <th>Application No</th>
                <th>Phone</th>
                <th>Gender</th>
                <th>Village</th>
                <th>Block</th>
                <th>Onboarding Batch</th>
            </tr>
            </thead>
            <tbody>
            @forelse ((array) ($applicantSnapshots ?? $row->selected_incubatees_snapshot) as $snap)
                @php
                    $phone = trim((string) ($snap['phone'] ?? ''));
                    $isLegacy = ($snap['source'] ?? '') === 'legacy_phase2' || (int) ($snap['incubatee_id'] ?? 0) < 0;
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        <span class="tp-show-source {{ $isLegacy ? 'tp-show-source--legacy' : 'tp-show-source--phase3' }}">
                            {{ $isLegacy ? 'Phase 2 legacy' : 'Phase 3 CFA' }}
                        </span>
                    </td>
                    <td>{{ $snap['name'] ?? 'Unnamed' }}</td>
                    <td>{{ $snap['application_no'] ?? 'NA' }}</td>
                    <td>
                        @if ($phone !== '')
                            <a class="tp-show-phone" href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a>
                        @else
                            NA
                        @endif
                    </td>
                    <td>{{ trim((string) ($snap['gender'] ?? '')) !== '' ? $snap['gender'] : 'NA' }}</td>
                    <td>{{ trim((string) ($snap['village'] ?? '')) !== '' ? $snap['village'] : 'NA' }}</td>
                    <td>{{ trim((string) ($snap['block_name'] ?? '')) !== '' ? $snap['block_name'] : 'NA' }}</td>
                    <td>{{ $snap['onboarding_batch_name'] ?? 'NA' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="tp-show-empty">No selected applicants found in snapshot.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

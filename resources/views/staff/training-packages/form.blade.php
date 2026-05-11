@extends('layouts.admin')

@section('title', 'Training Package Attendance')
@section('heading', 'Training Package Attendance')

@push('styles')
<style>
    .tp-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .tp-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .tp-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .tp-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .tp-alert--error { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .tp-alert--error ul { margin:0.35rem 0 0 1rem; }
    .tp-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .tp-card__title { margin:0 0 1.15rem; font-size:1rem; font-weight:700; color:#0f172a; }
    .tp-section { margin-top:1.35rem; }
    .tp-section__title { margin:0 0 0.75rem; font-size:0.9rem; font-weight:700; color:#0f172a; }
    .tp-section__title--small { margin:0 0 0.55rem; font-size:0.78rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; }
    .tp-plan-panel { padding-bottom:0.15rem; }
    .tp-plan-top {
        display:flex;
        flex-wrap:wrap;
        gap:0.75rem 1rem;
        align-items:flex-start;
        justify-content:space-between;
        margin-bottom:0.85rem;
    }
    .tp-plan-load { flex:1 1 18rem; min-width:0; }
    .tp-plan-load-form {
        display:grid;
        grid-template-columns:minmax(6.5rem, 7.5rem) minmax(9rem, 1fr) auto;
        gap:0.65rem;
        align-items:end;
    }
    .tp-plan-load-form .tp-field--action { align-self:end; }
    .tp-plan-load-form .tp-submit { width:100%; min-width:7.5rem; white-space:nowrap; }
    .tp-month-compact {
        flex:0 1 15.5rem;
        width:100%;
        max-width:15.5rem;
        margin-left:auto;
        padding:0.55rem 0.65rem;
        border-radius:10px;
        border:1px solid #c7d2fe;
        background:linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%);
        box-shadow:0 4px 14px rgba(79,70,229,0.08);
    }
    .tp-month-compact__title {
        margin:0 0 0.35rem;
        font-size:0.66rem;
        font-weight:800;
        color:#4338ca;
        text-transform:uppercase;
        letter-spacing:0.05em;
        line-height:1.3;
    }
    .tp-month-compact__stats {
        display:grid;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:0.35rem;
    }
    .tp-month-compact__stat { min-width:0; }
    .tp-month-compact__label {
        font-size:0.58rem;
        text-transform:uppercase;
        letter-spacing:0.05em;
        font-weight:800;
        color:#6366f1;
        line-height:1.2;
    }
    .tp-month-compact__value {
        margin-top:0.08rem;
        font-size:0.92rem;
        font-weight:800;
        color:#0f172a;
        line-height:1.1;
    }
    .tp-month-compact__progress { margin-top:0.4rem; }
    .tp-month-compact__track { height:0.34rem; border-radius:999px; background:#e0e7ff; overflow:hidden; }
    .tp-month-compact__fill { height:100%; border-radius:999px; background:linear-gradient(90deg, #4f46e5, #22c55e); }
    .tp-month-compact__meta { margin-top:0.22rem; font-size:0.64rem; color:#475569; font-weight:600; line-height:1.3; }
    .tp-form-divider {
        margin:1.15rem 0 1.25rem;
        padding-top:1.15rem;
        border-top:1px solid #e2e8f0;
    }
    .tp-form-divider__title { margin:0 0 0.35rem; font-size:0.95rem; font-weight:800; color:#0f172a; }
    .tp-form-divider__note { margin:0; font-size:0.8rem; color:#64748b; line-height:1.45; }
    .tp-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem 1.1rem; align-items:start; }
    .tp-field { display:flex; flex-direction:column; gap:0.4rem; min-width:0; }
    .tp-field--full { grid-column:1 / -1; }
    .tp-field label { font-size:0.82rem; font-weight:700; color:#0f172a; line-height:1.35; }
    .tp-field input[type="text"],
    .tp-field input[type="date"],
    .tp-field input[type="number"],
    .tp-field input[type="file"],
    .tp-field select { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.58rem 0.7rem; font-size:0.88rem; background:#fff; }
    .tp-field input[type="number"] { -moz-appearance:textfield; appearance:textfield; }
    .tp-field input[type="number"]::-webkit-outer-spin-button,
    .tp-field input[type="number"]::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
    .tp-field input[type="file"] { padding:0.45rem 0.55rem; background:#f8fafc; }
    .tp-readonly { background:#f8fafc; color:#64748b; }
    .tp-checkgrid { display:flex; flex-wrap:wrap; gap:0.55rem 0.85rem; padding:0.15rem 0; }
    .tp-checkgrid label { font-size:0.84rem; display:inline-flex; align-items:center; gap:0.4rem; margin:0; }
    .tp-checkgrid input { margin:0; }
    .tp-two-col { display:grid; grid-template-columns:minmax(0, 1.15fr) minmax(0, 0.85fr); gap:1rem; align-items:start; }
    .tp-col { display:flex; flex-direction:column; gap:0.55rem; min-width:0; }
    .tp-note { margin:0; color:#64748b; font-size:0.8rem; line-height:1.45; }
    .tp-search { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:0.55rem 0.7rem; font-size:0.88rem; box-sizing:border-box; }
    .tp-list { max-height:420px; overflow:auto; border:1px solid #e2e8f0; border-radius:10px; padding:0.55rem; background:#f8fafc; }
    .tp-item { display:flex; gap:0.65rem; align-items:flex-start; border:1px solid #e2e8f0; border-radius:10px; padding:0.6rem 0.65rem; background:#fff; margin-bottom:0.5rem; cursor:pointer; }
    .tp-item:last-child { margin-bottom:0; }
    .tp-item input { margin-top:0.15rem; flex-shrink:0; }
    .tp-item h4 { margin:0; font-size:0.86rem; line-height:1.35; }
    .tp-meta { margin-top:0.25rem; color:#64748b; font-size:0.76rem; line-height:1.4; }
    .tp-pill { display:inline-block; font-size:0.7rem; background:#eef2ff; color:#3730a3; border-radius:999px; padding:0.14rem 0.48rem; margin:0 0.3rem 0.25rem 0; }
    .tp-pill--legacy { background:#fff7ed; color:#9a3412; }
    .tp-right-title { margin:0; font-size:0.86rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:0.45rem; }
    .tp-selected-count {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:1.35rem;
        height:1.35rem;
        padding:0 0.35rem;
        border-radius:999px;
        background:#4f46e5;
        color:#fff;
        font-size:0.72rem;
        font-weight:800;
        box-shadow:0 4px 12px rgba(79,70,229,0.25);
    }
    .tp-selected-empty { color:#64748b; font-size:0.82rem; margin:0; padding:0.35rem 0.15rem; }
    .tp-actions { margin-top:1.35rem; padding-top:1.15rem; border-top:1px solid #e2e8f0; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; }
    .tp-submit { border:none; border-radius:9px; background:#4f46e5; color:#fff; padding:0.62rem 1.05rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .tp-link { display:inline-flex; align-items:center; padding:0.62rem 0.2rem; color:#4f46e5; text-decoration:none; font-size:0.88rem; font-weight:600; }
    .tp-link:hover { text-decoration:underline; }
    .tp-media-preview { margin-top:0.45rem; display:flex; flex-wrap:wrap; gap:0.5rem; align-items:flex-start; }
    .tp-media-preview img { width:110px; height:80px; object-fit:cover; border-radius:8px; border:1px solid #e2e8f0; background:#fff; }
    .tp-media-chip { font-size:0.72rem; padding:0.28rem 0.55rem; border:1px solid #cbd5e1; border-radius:999px; background:#f8fafc; color:#334155; }
    .tp-field-hint { margin:0.35rem 0 0; font-size:0.78rem; color:#64748b; }
    .tp-btn-remove { border:1px solid #fecaca; background:#fff1f2; color:#b91c1c; border-radius:6px; padding:0.22rem 0.55rem; cursor:pointer; margin-top:0.35rem; font-size:0.76rem; }
    .tp-session-board { display:grid; gap:0.55rem; margin-top:0.75rem; }
    .tp-session-board__item {
        border:1px solid #e2e8f0;
        border-radius:10px;
        padding:0.65rem 0.75rem;
        background:#fff;
        display:flex;
        flex-wrap:wrap;
        gap:0.45rem 0.75rem;
        align-items:center;
        justify-content:space-between;
    }
    .tp-session-board__item--filled { border-color:#86efac; background:#f0fdf4; }
    .tp-session-board__name { font-size:0.84rem; font-weight:700; color:#0f172a; }
    .tp-session-board__status { font-size:0.76rem; font-weight:700; color:#4f46e5; }
    .tp-filled-by {
        display:inline-flex;
        align-items:center;
        gap:0.35rem;
        padding:0.22rem 0.55rem;
        border-radius:999px;
        background:#fef3c7;
        border:1px solid #fcd34d;
        color:#92400e;
        font-size:0.76rem;
        font-weight:800;
    }
    .tp-filled-by__name { color:#b45309; }
    .tp-month-compact__extra { margin-top:0.35rem; font-size:0.64rem; font-weight:700; color:#9a3412; line-height:1.3; }
    .tp-session-mode { display:flex; flex-wrap:wrap; gap:0.75rem 1rem; margin-bottom:0.75rem; }
    .tp-session-mode label { display:inline-flex; align-items:center; gap:0.4rem; font-size:0.84rem; font-weight:600; color:#0f172a; margin:0; }
    .tp-session-mode input { margin:0; }
    .tp-session-board__item--extra { border-color:#fed7aa; background:#fff7ed; }
    .tp-session-board__badge {
        display:inline-flex;
        align-items:center;
        padding:0.14rem 0.45rem;
        border-radius:999px;
        background:#ffedd5;
        border:1px solid #fdba74;
        color:#9a3412;
        font-size:0.68rem;
        font-weight:800;
        text-transform:uppercase;
        letter-spacing:0.04em;
    }
    .tp-extra-panel { display:none; }
    .tp-extra-panel.is-active { display:block; }
    .tp-planned-panel.is-hidden { display:none; }
    @media (max-width: 900px) {
        .tp-plan-top { flex-direction:column; }
        .tp-plan-load-form { grid-template-columns:1fr; }
        .tp-month-compact { max-width:none; margin-left:0; }
        .tp-grid { grid-template-columns:1fr; }
        .tp-two-col { grid-template-columns:1fr; }
    }
</style>
@endpush

@section('content')
<div class="tp-shell">
    @php
        $oldSelectedIds = collect((array) old('selected_incubatees', []))
            ->map(fn ($id) => (int) $id)
            ->all();
    @endphp

    @if (!empty($migrationMissing))
        <div class="tp-alert tp-alert--warning">
            <strong>Training packages table not found.</strong> Run <code>php artisan migrate</code> first.
        </div>
    @endif

    @if (session('status'))
        <div class="tp-alert tp-alert--success">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="tp-alert tp-alert--error">
            <strong>Please fix:</strong>
            <ul>
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="tp-card">
        <h3 class="tp-card__title">Submission Form</h3>

        @php
            $monthPlanningEnabled = (bool) ($monthPlanningEnabled ?? false);
            $planYear = (int) ($planYear ?? now()->year);
            $planMonth = (int) ($planMonth ?? now()->month);
            $monthSlots = $monthSlots ?? collect();
            $monthExtraSlots = $monthExtraSlots ?? collect();
            $monthSummary = (array) ($monthSummary ?? ['required' => 0, 'filled' => 0, 'remaining' => 0, 'extra_filled' => 0]);
            $sessionMode = old('session_mode', 'planned');
            $monthOptions = $monthOptions ?? collect(range(1, 12))->mapWithKeys(fn (int $month): array => [$month => now()->setDate($planYear, $month, 1)->format('F')]);
            $monthTarget = (int) ($monthSummary['required'] ?? 0);
            $monthAchievement = (int) ($monthSummary['filled'] ?? 0);
            $monthRemaining = (int) ($monthSummary['remaining'] ?? 0);
            $monthProgressPct = $monthTarget > 0 ? min(100, (int) round(($monthAchievement / $monthTarget) * 100)) : 0;
            $planYearOptions = range((int) now()->year + 1, 2000);
            if (! in_array($planYear, $planYearOptions, true)) {
                $planYearOptions[] = $planYear;
                rsort($planYearOptions);
            }
        @endphp

        @if ($monthPlanningEnabled)
            <div class="tp-section tp-plan-panel" style="margin-top:0;">
                <h4 class="tp-section__title tp-section__title--small">Planned sessions for {{ $monthOptions[$planMonth] ?? $planMonth }} {{ $planYear }}</h4>

                <div class="tp-plan-top">
                    <div class="tp-plan-load">
                        <form method="get" action="{{ route('staff.training-packages.create') }}" class="tp-plan-load-form">
                            <div class="tp-field">
                                <label for="plan_year">Year</label>
                                <select id="plan_year" name="plan_year">
                                    @foreach ($planYearOptions as $yearOption)
                                        <option value="{{ $yearOption }}" @selected((int) $planYear === (int) $yearOption)>{{ $yearOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="tp-field">
                                <label for="plan_month">Month</label>
                                <select id="plan_month" name="plan_month">
                                    @foreach ($monthOptions as $monthValue => $monthLabel)
                                        <option value="{{ $monthValue }}" @selected((int) $planMonth === (int) $monthValue)>{{ $monthLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="tp-field tp-field--action">
                                <button type="submit" class="tp-submit">Load month</button>
                            </div>
                        </form>
                    </div>

                    <aside class="tp-month-compact" aria-label="Monthly session progress">
                        <p class="tp-month-compact__title">{{ $monthOptions[$planMonth] ?? $planMonth }} {{ $planYear }}</p>
                        <div class="tp-month-compact__stats">
                            <div class="tp-month-compact__stat">
                                <div class="tp-month-compact__label">Target</div>
                                <div class="tp-month-compact__value">{{ number_format($monthTarget) }}</div>
                            </div>
                            <div class="tp-month-compact__stat">
                                <div class="tp-month-compact__label">Done</div>
                                <div class="tp-month-compact__value">{{ number_format($monthAchievement) }}</div>
                            </div>
                            <div class="tp-month-compact__stat">
                                <div class="tp-month-compact__label">Left</div>
                                <div class="tp-month-compact__value">{{ number_format($monthRemaining) }}</div>
                            </div>
                        </div>
                        <div class="tp-month-compact__progress">
                            <div class="tp-month-compact__track">
                                <div class="tp-month-compact__fill" style="width:{{ $monthProgressPct }}%;"></div>
                            </div>
                            <div class="tp-month-compact__meta">{{ $monthProgressPct }}% complete</div>
                        </div>
                        @if ((int) ($monthSummary['extra_filled'] ?? 0) > 0)
                            <div class="tp-month-compact__extra">Extra done: {{ number_format((int) ($monthSummary['extra_filled'] ?? 0)) }}</div>
                        @endif
                    </aside>
                </div>

                @if ((int) ($monthSummary['required'] ?? 0) === 0)
                    <div class="tp-alert tp-alert--warning" style="margin-top:0.75rem;">
                        No planned sessions are assigned for this month yet. You can still record an extra session below, or contact the state admin for planned targets.
                    </div>
                @endif
            </div>

            <div class="tp-form-divider">
                <h4 class="tp-form-divider__title">Attendance submission</h4>
                <p class="tp-form-divider__note">Choose a planned session from the monthly target, or record an extra session that does not change the monthly target.</p>
            </div>
        @endif

        <form method="post" action="{{ route('staff.training-packages.store') }}" enctype="multipart/form-data">
            @csrf
            @if ($monthPlanningEnabled)
                <input type="hidden" name="plan_year" value="{{ $planYear }}">
                <input type="hidden" name="plan_month" value="{{ $planMonth }}">
                <div class="tp-section" style="margin-top:0;">
                    <div class="tp-session-mode" role="radiogroup" aria-label="Session type">
                        <label>
                            <input type="radio" name="session_mode" value="planned" @checked($sessionMode === 'planned')>
                            <span>Planned session</span>
                        </label>
                        <label>
                            <input type="radio" name="session_mode" value="extra" @checked($sessionMode === 'extra')>
                            <span>Extra session</span>
                        </label>
                    </div>

                    <div class="tp-planned-panel @if($sessionMode === 'extra') is-hidden @endif" id="tpPlannedPanel">
                        <div class="tp-field">
                            <label for="month_session_id">Planned session *</label>
                            <select id="month_session_id" name="month_session_id" data-plan-disabled="{{ (int) ($monthSummary['required'] ?? 0) === 0 ? '1' : '0' }}" @disabled((int) ($monthSummary['required'] ?? 0) === 0)>
                                <option value="">Select a planned session</option>
                            @foreach ($monthSlots as $slot)
                                @if ($slot->trainingPackage)
                                    <option value="{{ $slot->id }}" disabled>
                                        {{ $slot->session_name }} — Filled by {{ $slot->trainingPackage->submitted_by_name }} on {{ $slot->trainingPackage->event_date?->format('d M Y') ?: 'NA' }}
                                    </option>
                                @else
                                    <option value="{{ $slot->id }}" @selected((int) old('month_session_id') === (int) $slot->id)>
                                        {{ $slot->session_name }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                        @if ($monthSlots->isNotEmpty())
                            <div class="tp-session-board">
                                @foreach ($monthSlots as $slot)
                                    @php $isFilled = $slot->trainingPackage !== null; @endphp
                                    <div class="tp-session-board__item @if($isFilled) tp-session-board__item--filled @endif">
                                        <span class="tp-session-board__name">{{ $slot->session_name }}</span>
                                        @if ($isFilled)
                                            <span class="tp-filled-by">
                                                Filled by
                                                <span class="tp-filled-by__name">{{ $slot->trainingPackage->submitted_by_name }}</span>
                                                on {{ $slot->trainingPackage->event_date?->format('d M Y') ?: 'NA' }}
                                            </span>
                                        @else
                                            <span class="tp-session-board__status">Open for submission</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="tp-extra-panel @if($sessionMode === 'extra') is-active @endif" id="tpExtraPanel">
                        <div class="tp-field">
                            <label for="extra_session_name">Extra session name *</label>
                            <input type="text" id="extra_session_name" name="extra_session_name" value="{{ old('extra_session_name') }}" maxlength="191" placeholder="Name this extra session">
                            <p class="tp-field-hint">Extra sessions are recorded separately and do not change the monthly target.</p>
                        </div>
                    </div>

                    @if ($monthExtraSlots->isNotEmpty())
                        <div class="tp-session-board" style="margin-top:0.75rem;">
                            @foreach ($monthExtraSlots as $slot)
                                @php $isFilled = $slot->trainingPackage !== null; @endphp
                                <div class="tp-session-board__item tp-session-board__item--extra @if($isFilled) tp-session-board__item--filled @endif">
                                    <span class="tp-session-board__name">
                                        <span class="tp-session-board__badge">Extra</span>
                                        {{ $slot->session_name }}
                                    </span>
                                    @if ($isFilled)
                                        <span class="tp-filled-by">
                                            Filled by
                                            <span class="tp-filled-by__name">{{ $slot->trainingPackage->submitted_by_name }}</span>
                                            on {{ $slot->trainingPackage->event_date?->format('d M Y') ?: 'NA' }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
            <div class="tp-grid">
                <div class="tp-field">
                    <label>Session Taken By</label>
                    <input type="text" class="tp-readonly" value="{{ $user->name }}" readonly>
                </div>
                <div class="tp-field">
                    <label>Date of Session *</label>
                    <input type="date" name="session_date" value="{{ old('session_date') }}" required>
                </div>
                <div class="tp-field">
                    <label>District</label>
                    <input type="text" class="tp-readonly" value="{{ $user->district?->name ?? 'Not assigned' }}" readonly>
                </div>
                <div class="tp-field">
                    <label>Training Batch (optional custom)</label>
                    <input type="text" name="training_batch_name" value="{{ old('training_batch_name') }}" placeholder="Optional batch text">
                </div>
                <div class="tp-field tp-field--full">
                    <label>Training Packages (multi-select) *</label>
                    @php $oldModules = (array) old('training_packages', []); @endphp
                    <div class="tp-checkgrid">
                        @foreach (['t1' => 'T1', 't2' => 'T2', 't3' => 'T3', 't4' => 'T4'] as $moduleValue => $moduleLabel)
                            <label>
                                <input type="checkbox" name="training_packages[]" value="{{ $moduleValue }}" @checked(in_array($moduleValue, $oldModules, true))>
                                <span>{{ $moduleLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="tp-section">
                <div class="tp-field">
                    <label>Uploaded attendance sheet (optional)</label>
                    <input id="tpMediaInput" type="file" name="attendance_media[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,.mov,.avi,.mkv,.doc,.docx,.xls,.xlsx" multiple>
                    <p class="tp-field-hint">Each file can be up to 50 MB. PDF, image, video, Word, and Excel files are accepted.</p>
                    <div id="tpMediaPreview" class="tp-media-preview"></div>
                </div>
            </div>

            <div class="tp-section">
                <h4 class="tp-section__title">Manual Attendance Selection * (required)</h4>
                <div class="tp-two-col">
                    <div class="tp-col">
                        <p class="tp-note">
                            rbiphase3 onboarded applicants in district: <strong>{{ (int) ($totalOnboardedCount ?? $incubatees->count()) }}</strong>
                        </p>
                        <input id="tpSearch" class="tp-search" type="text" placeholder="Search rbiphase3 onboarded applicants by name/application/phone">
                        <div class="tp-list" id="tpSourceList">
                            @forelse ($incubatees as $row)
                                <label class="tp-item" data-search="{{ strtolower($row['name'].' '.$row['application_no'].' '.$row['phone']) }}">
                                    <input type="checkbox" class="tp-check" value="{{ $row['incubatee_id'] }}" @checked(in_array((int) $row['incubatee_id'], $oldSelectedIds, true))>
                                    <div>
                                        <h4>{{ $row['name'] ?: 'Unnamed' }}</h4>
                                        <div class="tp-meta">
                                            <span class="tp-pill">App: {{ $row['application_no'] ?: 'NA' }}</span>
                                            <span class="tp-pill">Batch: {{ $row['onboarding_batch_name'] ?: 'NA' }}</span>
                                        </div>
                                        <div class="tp-meta">Phone: {{ $row['phone'] ?: 'NA' }} | Block: {{ $row['block_name'] ?: 'NA' }} | Village: {{ $row['village'] ?: 'NA' }}</div>
                                    </div>
                                </label>
                            @empty
                                <p class="tp-selected-empty">No rbiphase3 onboarded applicants found for your district.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="tp-col">
                        <p class="tp-right-title">Selected Incubatees <span id="tpSelectedCount" class="tp-selected-count">0</span></p>
                        <div class="tp-list" id="tpSelectedPanel"></div>
                    </div>
                </div>
            </div>

            <div id="tpHiddenInputs"></div>
            <div class="tp-actions">
                <button class="tp-submit" type="submit">Submit attendance</button>
                <a class="tp-link" href="{{ route('staff.training-packages.dashboard') }}">View dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const checks = Array.from(document.querySelectorAll('.tp-check'));
    const selectedPanel = document.getElementById('tpSelectedPanel');
    const hiddenInputs = document.getElementById('tpHiddenInputs');
    const search = document.getElementById('tpSearch');
    const sourceList = document.getElementById('tpSourceList');
    const selectedCount = document.getElementById('tpSelectedCount');
    const sessionModeInputs = Array.from(document.querySelectorAll('input[name="session_mode"]'));
    const plannedPanel = document.getElementById('tpPlannedPanel');
    const extraPanel = document.getElementById('tpExtraPanel');
    const monthSessionSelect = document.getElementById('month_session_id');
    const extraSessionNameInput = document.getElementById('extra_session_name');
    const mediaInput = document.getElementById('tpMediaInput');
    const mediaPreview = document.getElementById('tpMediaPreview');
    const selectedMap = new Map();

    function sourceCardForCheckbox(el) {
        return el && el.closest('.tp-item') ? el.closest('.tp-item') : null;
    }

    function syncMapFromCheckbox(el) {
        const id = String(el.value || '');
        if (id === '') return;
        if (el.checked) {
            const card = sourceCardForCheckbox(el);
            if (card) {
                selectedMap.set(id, card.cloneNode(true));
            }
        } else {
            selectedMap.delete(id);
        }
    }

    function renderSelected() {
        hiddenInputs.innerHTML = '';
        if (selectedCount) {
            selectedCount.textContent = String(selectedMap.size);
        }
        if (!selectedMap.size) {
            selectedPanel.innerHTML = '<p class="tp-selected-empty">No incubatee selected yet.</p>';
            return;
        }

        selectedPanel.innerHTML = '';
        selectedMap.forEach((storedCard, selectedId) => {
            const card = storedCard.cloneNode(true);
            const cardCheckbox = card.querySelector('.tp-check');
            if (cardCheckbox) {
                cardCheckbox.remove();
            }

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.textContent = 'Remove';
            removeBtn.className = 'tp-btn-remove';
            removeBtn.addEventListener('click', function () {
                const linkedCheckbox = checks.find((c) => String(c.value || '') === String(selectedId));
                if (linkedCheckbox) {
                    linkedCheckbox.checked = false;
                }
                selectedMap.delete(String(selectedId));
                renderSelected();
            });

            const content = card.querySelector('div');
            if (content) {
                content.appendChild(removeBtn);
            } else {
                card.appendChild(removeBtn);
            }

            selectedPanel.appendChild(card);

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_incubatees[]';
            input.value = String(selectedId);
            hiddenInputs.appendChild(input);
        });
    }

    function syncSessionModeFields() {
        const mode = sessionModeInputs.find((input) => input.checked)?.value || 'planned';
        const isExtra = mode === 'extra';

        if (plannedPanel) {
            plannedPanel.classList.toggle('is-hidden', isExtra);
        }
        if (extraPanel) {
            extraPanel.classList.toggle('is-active', isExtra);
        }
        if (monthSessionSelect) {
            const planDisabled = monthSessionSelect.dataset.planDisabled === '1';
            monthSessionSelect.required = ! isExtra && ! planDisabled;
            monthSessionSelect.disabled = isExtra || planDisabled;
        }
        if (extraSessionNameInput) {
            extraSessionNameInput.required = isExtra;
        }
    }

    sessionModeInputs.forEach((input) => {
        input.addEventListener('change', syncSessionModeFields);
    });
    syncSessionModeFields();

    checks.forEach((el) => {
        if (el.checked) {
            syncMapFromCheckbox(el);
        }
        el.addEventListener('change', function () {
            syncMapFromCheckbox(el);
            renderSelected();
        });
    });

    if (search && sourceList) {
        search.addEventListener('input', function () {
            const term = (this.value || '').toLowerCase().trim();
            sourceList.querySelectorAll('.tp-item').forEach((item) => {
                const hay = item.getAttribute('data-search') || '';
                item.style.display = term === '' || hay.includes(term) ? '' : 'none';
            });
        });
    }

    if (mediaInput && mediaPreview) {
        mediaInput.addEventListener('change', function () {
            mediaPreview.innerHTML = '';
            const files = Array.from(this.files || []);
            if (!files.length) {
                return;
            }
            files.forEach((file) => {
                if ((file.type || '').startsWith('image/')) {
                    const img = document.createElement('img');
                    img.alt = file.name;
                    img.src = URL.createObjectURL(file);
                    mediaPreview.appendChild(img);
                } else {
                    const chip = document.createElement('span');
                    chip.className = 'tp-media-chip';
                    chip.textContent = file.name;
                    mediaPreview.appendChild(chip);
                }
            });
        });
    }

    renderSelected();
}());
</script>
@endpush

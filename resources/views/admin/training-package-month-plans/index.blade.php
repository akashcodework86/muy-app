@extends('layouts.admin')

@section('title', 'Training package monthly sessions')
@section('heading', 'Training package monthly sessions')

@push('styles')
<style>
    .tpmp-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .tpmp-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .tpmp-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .tpmp-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .tpmp-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.35rem; }
    .tpmp-filters { display:flex; flex-wrap:wrap; gap:0.85rem; align-items:flex-end; margin-bottom:1rem; }
    .tpmp-field { display:flex; flex-direction:column; gap:0.35rem; min-width:10rem; }
    .tpmp-field label { font-size:0.78rem; font-weight:700; color:#0f172a; }
    .tpmp-field input, .tpmp-field select { border:1px solid #cbd5e1; border-radius:8px; padding:0.55rem 0.65rem; font-size:0.88rem; }
    .tpmp-summary { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:0.85rem; margin-bottom:1rem; }
    .tpmp-stat { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:0.85rem 1rem; }
    .tpmp-stat__label { font-size:0.74rem; color:#64748b; text-transform:uppercase; letter-spacing:0.06em; font-weight:700; }
    .tpmp-stat__value { margin-top:0.3rem; font-size:1.2rem; font-weight:800; color:#0f172a; }
    .tpmp-district-brief { margin-bottom:1rem; }
    .tpmp-district-brief__title { margin:0 0 0.55rem; font-size:0.86rem; font-weight:800; color:#0f172a; }
    .tpmp-district-brief__rows { display:flex; flex-direction:column; gap:0.45rem; }
    .tpmp-district-brief__grid {
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(7.5rem, 1fr));
        gap:0.4rem;
    }
    .tpmp-district-brief__card {
        border:1px solid #dbeafe;
        border-radius:8px;
        padding:0.42rem 0.5rem;
        background:linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%);
        min-width:0;
    }
    .tpmp-district-brief__name {
        font-size:0.7rem;
        font-weight:800;
        color:#0f172a;
        line-height:1.25;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }
    .tpmp-district-brief__counts {
        margin-top:0.18rem;
        font-size:0.64rem;
        color:#475569;
        font-weight:600;
        line-height:1.3;
    }
    .tpmp-district-brief__ratio { font-size:0.68rem; font-weight:800; color:#0f172a; line-height:1.25; }
    .tpmp-district-brief__pct { margin-top:0.12rem; font-size:0.62rem; font-weight:700; color:#4f46e5; }
    .tpmp-district-brief__status {
        margin-top:0.18rem;
        font-size:0.64rem;
        font-weight:700;
        color:#94a3b8;
        font-style:italic;
        line-height:1.3;
    }
    .tpmp-district-brief__card--unassigned { background:#f8fafc; border-color:#e2e8f0; }
    .tpmp-district-brief__extra { margin-top:0.18rem; font-size:0.62rem; font-weight:700; color:#9a3412; line-height:1.3; }
    .tpmp-plan-section { margin-top:0.55rem; }
    .tpmp-plan-section--extra { margin-top:0.65rem; padding-top:0.55rem; border-top:1px dashed #e2e8f0; }
    .tpmp-plan-section__title { margin:0 0 0.35rem; font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; }
    .tpmp-plan-section__title--required { color:#3730a3; }
    .tpmp-plan-section__title--extra { color:#9a3412; }
    .tpmp-session-item { display:flex; align-items:flex-start; gap:0.42rem; margin-bottom:0.38rem; }
    .tpmp-session-item .tpmp-session-card { flex:1; min-width:0; margin-bottom:0; }
    .tpmp-session-bullet {
        flex:0 0 auto;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:1.15rem;
        height:1.15rem;
        margin-top:0.42rem;
        border-radius:999px;
        font-size:0.62rem;
        font-weight:800;
        line-height:1;
    }
    .tpmp-session-bullet--required { background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3; }
    .tpmp-session-bullet--extra { background:#ffedd5; border:1px solid #fdba74; color:#9a3412; }
    .js-session-list { counter-reset:tpmp-required; }
    .js-session-list > .tpmp-session-item { counter-increment:tpmp-required; }
    .js-session-list > .tpmp-session-item > .tpmp-session-bullet--required::before { content:counter(tpmp-required); }
    .js-extra-session-list { counter-reset:tpmp-extra; }
    .js-extra-session-list > .tpmp-session-item { counter-increment:tpmp-extra; }
    .js-extra-session-list > .tpmp-session-item > .tpmp-session-bullet--extra::before { content:counter(tpmp-extra); }
    .tpmp-session-card {
        display:grid;
        grid-template-columns:minmax(0,1fr) auto;
        gap:0.3rem 0.45rem;
        align-items:center;
        border:1px solid #e2e8f0;
        border-radius:8px;
        padding:0.42rem 0.5rem;
        background:#fff;
        margin-bottom:0.38rem;
    }
    .tpmp-session-card--filled,
    .tpmp-session-card--extra { grid-template-columns:1fr; }
    .tpmp-session-card--filled { border-color:#86efac; background:#f8fdf9; }
    .tpmp-session-card--open { border-color:#dbeafe; background:#fbfdff; }
    .tpmp-session-card--extra { border-color:#fdba74; background:#fffaf5; }
    .tpmp-session-card__lead {
        grid-column:1 / -1;
        display:flex;
        flex-wrap:wrap;
        align-items:center;
        gap:0.3rem 0.45rem;
        margin-bottom:0.05rem;
    }
    .tpmp-session-card__badge {
        display:inline-flex;
        align-items:center;
        padding:0.1rem 0.38rem;
        border-radius:999px;
        font-size:0.6rem;
        font-weight:800;
        text-transform:uppercase;
        letter-spacing:0.03em;
    }
    .tpmp-session-card__badge--required { background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3; }
    .tpmp-session-card__badge--extra { background:#ffedd5; border:1px solid #fdba74; color:#9a3412; }
    .tpmp-session-card__meta { font-size:0.68rem; color:#92400e; font-weight:600; line-height:1.3; }
    .tpmp-session-card__meta strong { font-weight:800; color:#b45309; }
    .tpmp-session-card__name-row {
        grid-column:1 / -1;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:0.35rem;
        min-width:0;
    }
    .tpmp-session-card__name-text {
        min-width:0;
        font-size:0.78rem;
        font-weight:700;
        color:#0f172a;
        line-height:1.3;
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
    }
    .tpmp-session-card__input {
        width:100%;
        box-sizing:border-box;
        border:1px solid #cbd5e1;
        border-radius:6px;
        padding:0.38rem 0.5rem;
        font-size:0.8rem;
        background:#fff;
    }
    .tpmp-session-card__input--name { grid-column:1 / -1; }
    .tpmp-session-card__name-row.is-hidden,
    .tpmp-session-card__input--name.is-hidden { display:none; }
    .tpmp-district-brief__bar { margin-top:0.28rem; height:0.28rem; border-radius:999px; background:#e2e8f0; overflow:hidden; }
    .tpmp-district-brief__bar span { display:block; height:100%; border-radius:999px; background:linear-gradient(90deg, #4f46e5, #22c55e); }
    .tpmp-district-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem; }
    .tpmp-district { border:1px solid #e2e8f0; border-radius:8px; padding:0.55rem; background:#fff; min-width:0; }
    .tpmp-district__head { display:flex; flex-wrap:wrap; gap:0.4rem; justify-content:space-between; align-items:flex-start; margin-bottom:0.4rem; }
    .tpmp-district__title { margin:0; font-size:0.86rem; font-weight:800; color:#0f172a; }
    .tpmp-district__meta { font-size:0.7rem; color:#64748b; margin-top:0.12rem; }
    .tpmp-progress {
        display:inline-flex;
        align-items:center;
        gap:0.25rem;
        margin-top:0.2rem;
        padding:0.16rem 0.45rem;
        border-radius:999px;
        font-size:0.66rem;
        font-weight:800;
        border:1px solid #c7d2fe;
        background:#eef2ff;
        color:#3730a3;
    }
    .tpmp-progress--done { border-color:#86efac; background:#dcfce7; color:#166534; }
    .tpmp-progress--pending { border-color:#fde68a; background:#fffbeb; color:#92400e; }
    .tpmp-btn { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.58rem 0.95rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .tpmp-btn--ghost { background:#fff; color:#334155; border:1px solid #cbd5e1; padding:0.32rem 0.55rem; border-radius:6px; cursor:pointer; font-size:0.72rem; }
    .tpmp-btn--xs { padding:0.12rem 0.38rem; font-size:0.62rem; font-weight:700; line-height:1.2; white-space:nowrap; }
    .tpmp-btn--danger { background:#fff1f2; color:#b91c1c; border:1px solid #fecaca; }
    .tpmp-session-card__actions { grid-column:1 / -1; display:flex; justify-content:flex-end; }
    .tpmp-actions { display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; margin-top:1rem; }
    @media (max-width: 900px) {
        .tpmp-district-brief__grid { grid-template-columns:repeat(auto-fit, minmax(6.5rem, 1fr)); }
        .tpmp-district-grid { grid-template-columns:1fr; }
    }
</style>
@endpush

@section('content')
<div class="tpmp-shell">
    @if (!empty($migrationMissing))
        <div class="tpmp-alert tpmp-alert--warning">
            <strong>Training package month sessions table not found.</strong> Run <code>php artisan migrate</code> first.
        </div>
    @endif

    @if (session('status'))
        <div class="tpmp-alert tpmp-alert--success">
            {{ session('status') }}
        </div>
    @endif

    <div class="tpmp-card">
        <form method="get" action="{{ route('admin.training-package-month-plans.index') }}" class="tpmp-filters">
            <div class="tpmp-field">
                <label for="calendar_year">Year</label>
                <select id="calendar_year" name="calendar_year">
                    @foreach (($yearOptions ?? []) as $yearOption)
                        <option value="{{ $yearOption }}" @selected((int) $calendarYear === (int) $yearOption)>{{ $yearOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tpmp-field">
                <label for="calendar_month">Month</label>
                <select id="calendar_month" name="calendar_month">
                    @foreach ($monthOptions as $monthValue => $monthLabel)
                        <option value="{{ $monthValue }}" @selected((int) $calendarMonth === (int) $monthValue)>{{ $monthLabel }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="tpmp-btn">Load month</button>
        </form>

        <div class="tpmp-summary">
            <div class="tpmp-stat">
                <div class="tpmp-stat__label">Required statewide</div>
                <div class="tpmp-stat__value">{{ number_format((int) ($statewideSummary['required'] ?? 0)) }}</div>
            </div>
            <div class="tpmp-stat">
                <div class="tpmp-stat__label">Filled statewide</div>
                <div class="tpmp-stat__value">{{ number_format((int) ($statewideSummary['filled'] ?? 0)) }}</div>
            </div>
            <div class="tpmp-stat">
                <div class="tpmp-stat__label">Remaining statewide</div>
                <div class="tpmp-stat__value">{{ number_format((int) ($statewideSummary['remaining'] ?? 0)) }}</div>
            </div>
        </div>

        @if ($districtPlans->isNotEmpty())
            <div class="tpmp-district-brief">
                <h3 class="tpmp-district-brief__title">District target and achievement snapshot</h3>
                <div class="tpmp-district-brief__rows">
                    @foreach ($districtPlans->values()->chunk((int) ceil($districtPlans->count() / 2)) as $briefRow)
                        <div class="tpmp-district-brief__grid">
                            @foreach ($briefRow as $districtPlan)
                                @php
                                    $briefDistrict = $districtPlan['district'];
                                    $briefSummary = $districtPlan['summary'];
                                    $briefTarget = (int) ($briefSummary['required'] ?? 0);
                                    $briefFilled = (int) ($briefSummary['filled'] ?? 0);
                                    $briefExtraFilled = (int) ($briefSummary['extra_filled'] ?? 0);
                                    $briefPct = $briefTarget > 0 ? min(100, (int) round(($briefFilled / $briefTarget) * 100)) : 0;
                                @endphp
                                <div class="tpmp-district-brief__card @if($briefTarget === 0) tpmp-district-brief__card--unassigned @endif">
                                    <div class="tpmp-district-brief__name" title="{{ $briefDistrict->name }}">{{ $briefDistrict->name }}</div>
                                    @if ($briefTarget === 0)
                                        <div class="tpmp-district-brief__status">Not assigned yet</div>
                                    @else
                                        <div class="tpmp-district-brief__ratio">{{ number_format($briefFilled) }} out of {{ number_format($briefTarget) }}</div>
                                        <div class="tpmp-district-brief__pct">{{ $briefPct }}% complete</div>
                                        <div class="tpmp-district-brief__bar">
                                            <span style="width:{{ $briefPct }}%;"></span>
                                        </div>
                                        @if ($briefExtraFilled > 0)
                                            <div class="tpmp-district-brief__extra">Extra: {{ number_format($briefExtraFilled) }}</div>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="post" action="{{ route('admin.training-package-month-plans.store') }}" id="tpmp-plan-form">
            @csrf
            <input type="hidden" name="calendar_year" value="{{ $calendarYear }}">
            <input type="hidden" name="calendar_month" value="{{ $calendarMonth }}">

            <div class="tpmp-district-grid">
            @foreach ($districtPlans as $districtIndex => $districtPlan)
                @php
                    $district = $districtPlan['district'];
                    $slots = $districtPlan['slots'];
                    $extraSlots = $districtPlan['extra_slots'] ?? collect();
                    $summary = $districtPlan['summary'];
                    $districtTarget = (int) ($summary['required'] ?? 0);
                    $districtFilled = (int) ($summary['filled'] ?? 0);
                    $districtExtraFilled = (int) ($summary['extra_filled'] ?? 0);
                    $districtPct = $districtTarget > 0 ? min(100, (int) round(($districtFilled / $districtTarget) * 100)) : 0;
                    $progressClass = $districtTarget > 0 && $districtFilled >= $districtTarget
                        ? 'tpmp-progress--done'
                        : ($districtFilled > 0 ? 'tpmp-progress--pending' : '');
                @endphp
                <section class="tpmp-district" data-district-index="{{ $districtIndex }}">
                    <div class="tpmp-district__head">
                        <div>
                            <h3 class="tpmp-district__title">{{ $district->name }}</h3>
                            @if ($district->hub?->name)
                                <div class="tpmp-district__meta">{{ $district->hub->name }}</div>
                            @endif
                            <span class="tpmp-progress {{ $progressClass }}">
                                {{ $districtFilled }} / {{ $districtTarget }} ({{ $districtPct }}%)
                            </span>
                        </div>
                        <button type="button" class="tpmp-btn--ghost js-add-session" data-district-index="{{ $districtIndex }}">Add session</button>
                    </div>

                    <input type="hidden" name="districts[{{ $districtIndex }}][district_id]" value="{{ $district->id }}">

                    <div class="tpmp-plan-section">
                        <h4 class="tpmp-plan-section__title tpmp-plan-section__title--required">Required sessions</h4>
                        <div class="js-session-list" data-district-index="{{ $districtIndex }}">
                            @forelse ($slots as $slotIndex => $slot)
                                @php $filled = $slot->trainingPackage !== null; @endphp
                                <div class="tpmp-session-item js-session-row">
                                    <span class="tpmp-session-bullet tpmp-session-bullet--required" aria-hidden="true"></span>
                                    <div class="tpmp-session-card @if($filled) tpmp-session-card--filled @else tpmp-session-card--open @endif">
                                    <div class="tpmp-session-card__lead">
                                        <span class="tpmp-session-card__badge tpmp-session-card__badge--required">Required</span>
                                        @if ($filled)
                                            <span class="tpmp-session-card__meta">Filled by <strong>{{ $slot->trainingPackage->submitted_by_name }}</strong> · {{ $slot->trainingPackage->event_date?->format('d M Y') ?: 'NA' }}</span>
                                        @endif
                                    </div>
                                    @if ($slot->id)
                                        <input type="hidden" name="districts[{{ $districtIndex }}][sessions][{{ $slotIndex }}][id]" value="{{ $slot->id }}">
                                    @endif
                                    <div class="tpmp-session-card__name-row @if(!$slot->session_name) is-hidden @endif">
                                        <span class="tpmp-session-card__name-text">{{ $slot->session_name ?: 'Unnamed session' }}</span>
                                        <button type="button" class="tpmp-btn--ghost tpmp-btn--xs js-edit-session-name">Edit name</button>
                                    </div>
                                    <input class="tpmp-session-card__input tpmp-session-card__input--name @if($slot->session_name) is-hidden @endif" type="text" name="districts[{{ $districtIndex }}][sessions][{{ $slotIndex }}][session_name]" value="{{ $slot->session_name }}" placeholder="Session name" maxlength="191">
                                    <div class="tpmp-session-card__actions">
                                        @if ($slot->id)
                                            <button type="button" class="tpmp-btn--ghost tpmp-btn--danger tpmp-btn--xs js-delete-session" data-delete-url="{{ route('admin.training-package-month-plans.sessions.destroy', $slot) }}">Delete session</button>
                                        @else
                                            <button type="button" class="tpmp-btn--ghost tpmp-btn--danger tpmp-btn--xs js-remove-session">Remove</button>
                                        @endif
                                    </div>
                                    </div>
                                </div>
                            @empty
                                <div class="tpmp-district__meta">Not assigned yet</div>
                            @endforelse
                        </div>
                    </div>

                    @if ($extraSlots->isNotEmpty())
                        <div class="tpmp-plan-section tpmp-plan-section--extra">
                            <h4 class="tpmp-plan-section__title tpmp-plan-section__title--extra">Extra sessions</h4>
                            <div class="js-extra-session-list" data-district-index="{{ $districtIndex }}">
                            @foreach ($extraSlots as $extraIndex => $extraSlot)
                                @php $extraFilled = $extraSlot->trainingPackage !== null; @endphp
                                <div class="tpmp-session-item">
                                    <span class="tpmp-session-bullet tpmp-session-bullet--extra" aria-hidden="true"></span>
                                    <div class="tpmp-session-card tpmp-session-card--extra">
                                    <div class="tpmp-session-card__lead">
                                        <span class="tpmp-session-card__badge tpmp-session-card__badge--extra">Extra</span>
                                        @if ($extraFilled)
                                            <span class="tpmp-session-card__meta">Filled by <strong>{{ $extraSlot->trainingPackage->submitted_by_name }}</strong> · {{ $extraSlot->trainingPackage->event_date?->format('d M Y') ?: 'NA' }}</span>
                                        @endif
                                    </div>
                                    <input type="hidden" name="districts[{{ $districtIndex }}][extra_sessions][{{ $extraIndex }}][id]" value="{{ $extraSlot->id }}">
                                    <div class="tpmp-session-card__name-row @if(!$extraSlot->session_name) is-hidden @endif">
                                        <span class="tpmp-session-card__name-text">{{ $extraSlot->session_name ?: 'Unnamed session' }}</span>
                                        <button type="button" class="tpmp-btn--ghost tpmp-btn--xs js-edit-session-name">Edit name</button>
                                    </div>
                                    <input class="tpmp-session-card__input tpmp-session-card__input--name @if($extraSlot->session_name) is-hidden @endif" type="text" name="districts[{{ $districtIndex }}][extra_sessions][{{ $extraIndex }}][session_name]" value="{{ $extraSlot->session_name }}" placeholder="Session name" maxlength="191">
                                    <div class="tpmp-session-card__actions">
                                        <button type="button" class="tpmp-btn--ghost tpmp-btn--danger tpmp-btn--xs js-delete-session" data-delete-url="{{ route('admin.training-package-month-plans.sessions.destroy', $extraSlot) }}">Delete session</button>
                                    </div>
                                    </div>
                                </div>
                            @endforeach
                            </div>
                        </div>
                    @endif
                </section>
            @endforeach
            </div>

            <div class="tpmp-actions">
                <button type="submit" class="tpmp-btn" @disabled(!empty($migrationMissing))>Save monthly plan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('tpmp-plan-form');
    if (!form) {
        return;
    }

    function nextSessionIndex(districtIndex) {
        const list = form.querySelector('.js-session-list[data-district-index="' + districtIndex + '"]');
        if (!list) {
            return 0;
        }

        return list.querySelectorAll('.js-session-row').length;
    }

    form.querySelectorAll('.js-add-session').forEach((button) => {
        button.addEventListener('click', function () {
            const districtIndex = this.getAttribute('data-district-index');
            const list = form.querySelector('.js-session-list[data-district-index="' + districtIndex + '"]');
            if (!list) {
                return;
            }

            const sessionIndex = nextSessionIndex(districtIndex);
            const row = document.createElement('div');
            row.className = 'tpmp-session-item js-session-row';
            row.innerHTML =
                '<span class="tpmp-session-bullet tpmp-session-bullet--required" aria-hidden="true"></span>' +
                '<div class="tpmp-session-card tpmp-session-card--open">' +
                    '<div class="tpmp-session-card__lead">' +
                        '<span class="tpmp-session-card__badge tpmp-session-card__badge--required">Required</span>' +
                    '</div>' +
                    '<div class="tpmp-session-card__name-row is-hidden">' +
                        '<span class="tpmp-session-card__name-text">Unnamed session</span>' +
                        '<button type="button" class="tpmp-btn--ghost tpmp-btn--xs js-edit-session-name">Edit name</button>' +
                    '</div>' +
                    '<input class="tpmp-session-card__input tpmp-session-card__input--name" type="text" name="districts[' + districtIndex + '][sessions][' + sessionIndex + '][session_name]" placeholder="Session name" maxlength="191">' +
                    '<div class="tpmp-session-card__actions">' +
                        '<button type="button" class="tpmp-btn--ghost tpmp-btn--danger tpmp-btn--xs js-remove-session">Remove</button>' +
                    '</div>' +
                '</div>';
            list.appendChild(row);
        });
    });

    function submitDeleteRequest(url) {
        if (!url || !confirm('Delete this session and any submitted attendance for it?')) {
            return;
        }

        const tokenInput = form.querySelector('input[name="_token"]');
        if (!(tokenInput instanceof HTMLInputElement) || tokenInput.value === '') {
            return;
        }

        const deleteForm = document.createElement('form');
        deleteForm.method = 'post';
        deleteForm.action = url;
        deleteForm.style.display = 'none';

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = tokenInput.value;
        deleteForm.appendChild(csrf);

        const method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        method.value = 'DELETE';
        deleteForm.appendChild(method);

        document.body.appendChild(deleteForm);
        deleteForm.submit();
    }

    function syncSessionNameDisplay(card) {
        const input = card.querySelector('.tpmp-session-card__input--name');
        const nameRow = card.querySelector('.tpmp-session-card__name-row');
        const nameText = card.querySelector('.tpmp-session-card__name-text');
        if (!(input instanceof HTMLInputElement) || !nameRow || !nameText) {
            return;
        }

        const value = input.value.trim();
        nameText.textContent = value !== '' ? value : 'Unnamed session';
        if (value === '') {
            nameRow.classList.add('is-hidden');
            input.classList.remove('is-hidden');
            return;
        }

        nameRow.classList.remove('is-hidden');
        input.classList.add('is-hidden');
    }

    form.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.classList.contains('js-delete-session')) {
            submitDeleteRequest(target.getAttribute('data-delete-url'));
            return;
        }

        if (target.classList.contains('js-edit-session-name')) {
            const card = target.closest('.tpmp-session-card');
            const input = card ? card.querySelector('.tpmp-session-card__input--name') : null;
            const nameRow = card ? card.querySelector('.tpmp-session-card__name-row') : null;
            if (card && input instanceof HTMLInputElement && nameRow) {
                nameRow.classList.add('is-hidden');
                input.classList.remove('is-hidden');
                input.focus();
                input.select();
            }
            return;
        }

        if (!target.classList.contains('js-remove-session')) {
            return;
        }

        const row = target.closest('.js-session-row');
        if (!row) {
            return;
        }

        row.remove();
    });

    form.addEventListener('focusout', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.classList.contains('tpmp-session-card__input--name')) {
            return;
        }

        const card = target.closest('.tpmp-session-card');
        if (card) {
            syncSessionNameDisplay(card);
        }
    });
}());
</script>
@endpush

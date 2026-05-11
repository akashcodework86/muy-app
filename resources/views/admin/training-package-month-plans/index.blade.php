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
    .tpmp-extra-section { margin-top:0.85rem; padding-top:0.75rem; border-top:1px dashed #e2e8f0; }
    .tpmp-extra-section__title { margin:0 0 0.45rem; font-size:0.78rem; font-weight:800; color:#9a3412; text-transform:uppercase; letter-spacing:0.04em; }
    .tpmp-extra-row { display:flex; flex-wrap:wrap; gap:0.35rem 0.65rem; align-items:center; margin-bottom:0.45rem; font-size:0.82rem; color:#334155; }
    .tpmp-extra-row__badge { display:inline-flex; align-items:center; padding:0.12rem 0.45rem; border-radius:999px; background:#ffedd5; border:1px solid #fdba74; color:#9a3412; font-size:0.68rem; font-weight:800; }
    .tpmp-district-brief__bar { margin-top:0.28rem; height:0.28rem; border-radius:999px; background:#e2e8f0; overflow:hidden; }
    .tpmp-district-brief__bar span { display:block; height:100%; border-radius:999px; background:linear-gradient(90deg, #4f46e5, #22c55e); }
    .tpmp-district-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem; }
    .tpmp-district { border:1px solid #e2e8f0; border-radius:12px; padding:1rem; background:#fff; min-width:0; }
    .tpmp-district__head { display:flex; flex-wrap:wrap; gap:0.75rem; justify-content:space-between; align-items:flex-start; margin-bottom:0.85rem; }
    .tpmp-district__title { margin:0; font-size:0.95rem; font-weight:800; color:#0f172a; }
    .tpmp-district__meta { font-size:0.8rem; color:#64748b; margin-top:0.2rem; }
    .tpmp-progress {
        display:inline-flex;
        align-items:center;
        gap:0.35rem;
        padding:0.28rem 0.6rem;
        border-radius:999px;
        font-size:0.74rem;
        font-weight:800;
        border:1px solid #c7d2fe;
        background:#eef2ff;
        color:#3730a3;
    }
    .tpmp-progress--done { border-color:#86efac; background:#dcfce7; color:#166534; }
    .tpmp-progress--pending { border-color:#fde68a; background:#fffbeb; color:#92400e; }
    .tpmp-session-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:0.65rem; align-items:center; margin-bottom:0.55rem; }
    .tpmp-session-row--filled { grid-template-columns:1fr; }
    .tpmp-session-row input[type="text"] { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:0.55rem 0.65rem; font-size:0.88rem; box-sizing:border-box; }
    .tpmp-session-row input[readonly] { background:#f8fafc; color:#64748b; }
    .tpmp-filled {
        display:inline-flex;
        flex-wrap:wrap;
        align-items:center;
        gap:0.35rem;
        margin-top:0.35rem;
        padding:0.3rem 0.55rem;
        border-radius:999px;
        background:#fef3c7;
        border:1px solid #fcd34d;
        color:#92400e;
        font-size:0.76rem;
        font-weight:800;
    }
    .tpmp-filled__name { color:#b45309; }
    .tpmp-btn { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.58rem 0.95rem; font-weight:700; cursor:pointer; font-size:0.88rem; }
    .tpmp-btn--ghost { background:#fff; color:#334155; border:1px solid #cbd5e1; padding:0.45rem 0.75rem; border-radius:8px; cursor:pointer; font-size:0.82rem; }
    .tpmp-btn--danger { background:#fff1f2; color:#b91c1c; border:1px solid #fecaca; }
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
                            <div class="tpmp-district__meta">
                                {{ $district->hub?->name ? 'Hub: '.$district->hub->name : 'District plan' }}
                            </div>
                            <span class="tpmp-progress {{ $progressClass }}">
                                Progress {{ $districtFilled }} / {{ $districtTarget }} ({{ $districtPct }}%)
                            </span>
                            @if ($districtExtraFilled > 0)
                                <div class="tpmp-district__meta">Extra: {{ number_format($districtExtraFilled) }}</div>
                            @endif
                        </div>
                        <button type="button" class="tpmp-btn--ghost js-add-session" data-district-index="{{ $districtIndex }}">Add session</button>
                    </div>

                    <input type="hidden" name="districts[{{ $districtIndex }}][district_id]" value="{{ $district->id }}">

                    <div class="js-session-list" data-district-index="{{ $districtIndex }}">
                        @forelse ($slots as $slotIndex => $slot)
                            @php $filled = $slot->trainingPackage !== null; @endphp
                            <div class="tpmp-session-row @if($filled) tpmp-session-row--filled @endif js-session-row">
                                @if ($filled)
                                    <input type="hidden" name="districts[{{ $districtIndex }}][sessions][{{ $slotIndex }}][id]" value="{{ $slot->id }}">
                                    <input type="text" value="{{ $slot->session_name }}" readonly>
                                    <div class="tpmp-filled">
                                        Filled by
                                        <span class="tpmp-filled__name">{{ $slot->trainingPackage->submitted_by_name }}</span>
                                        on {{ $slot->trainingPackage->event_date?->format('d M Y') ?: 'NA' }}
                                    </div>
                                @else
                                    <input type="hidden" name="districts[{{ $districtIndex }}][sessions][{{ $slotIndex }}][id]" value="{{ $slot->id }}">
                                    <input type="text" name="districts[{{ $districtIndex }}][sessions][{{ $slotIndex }}][session_name]" value="{{ $slot->session_name }}" placeholder="Session name" maxlength="191">
                                    <button type="button" class="tpmp-btn--ghost tpmp-btn--danger js-remove-session">Remove</button>
                                @endif
                            </div>
                        @empty
                            <div class="tpmp-district__meta">Not assigned yet</div>
                        @endforelse
                    </div>

                    @if ($extraSlots->isNotEmpty())
                        <div class="tpmp-extra-section">
                            <h4 class="tpmp-extra-section__title">Extra sessions</h4>
                            @foreach ($extraSlots as $extraSlot)
                                <div class="tpmp-extra-row">
                                    <span class="tpmp-extra-row__badge">Extra</span>
                                    <span>{{ $extraSlot->session_name }}</span>
                                    @if ($extraSlot->trainingPackage)
                                        <span class="tpmp-filled">
                                            Filled by
                                            <span class="tpmp-filled__name">{{ $extraSlot->trainingPackage->submitted_by_name }}</span>
                                            on {{ $extraSlot->trainingPackage->event_date?->format('d M Y') ?: 'NA' }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
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
            row.className = 'tpmp-session-row js-session-row';
            row.innerHTML =
                '<input type="text" name="districts[' + districtIndex + '][sessions][' + sessionIndex + '][session_name]" placeholder="Session name" maxlength="191">' +
                '<button type="button" class="tpmp-btn--ghost tpmp-btn--danger js-remove-session">Remove</button>';
            list.appendChild(row);
        });
    });

    form.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLElement) || !target.classList.contains('js-remove-session')) {
            return;
        }

        const row = target.closest('.js-session-row');
        if (row) {
            row.remove();
        }
    });
}());
</script>
@endpush

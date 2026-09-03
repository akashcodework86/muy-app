@extends('layouts.admin')

@section('title', 'Business Skills Training Sessions Target')
@section('heading', 'Business Skills Training Sessions Target')

@push('styles')
<style>
    .tpmp-shell { display:flex; flex-direction:column; gap:1rem; max-width:100%; }
    .tpmp-intro { margin:0; font-size:0.88rem; color:#475569; line-height:1.5; max-width:52rem; }
    .tpmp-alert { border-radius:10px; padding:0.75rem 1rem; font-size:0.88rem; }
    .tpmp-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .tpmp-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }

    .tpmp-toolbar {
        display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end; justify-content:space-between;
        background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.15rem;
    }
    .tpmp-toolbar__left { display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end; }
    .tpmp-toolbar__right { display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; }
    .tpmp-field { display:flex; flex-direction:column; gap:0.3rem; min-width:9rem; }
    .tpmp-field label { font-size:0.72rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:0.04em; }
    .tpmp-field select {
        border:1px solid #cbd5e1; border-radius:8px; padding:0.5rem 0.6rem; font-size:0.88rem; background:#fff;
    }

    .tpmp-stats { display:flex; flex-wrap:wrap; gap:0.5rem; }
    .tpmp-stat {
        min-width:7rem; padding:0.5rem 0.75rem; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc;
    }
    .tpmp-stat__label { font-size:0.65rem; color:#64748b; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }
    .tpmp-stat__value { margin-top:0.15rem; font-size:1.1rem; font-weight:800; color:#0f172a; line-height:1.2; }

    .tpmp-btn {
        border:none; border-radius:8px; background:#4f46e5; color:#fff;
        padding:0.55rem 0.95rem; font-weight:700; cursor:pointer; font-size:0.86rem;
    }
    .tpmp-btn:hover { background:#4338ca; }
    .tpmp-btn--ghost {
        background:#fff; color:#334155; border:1px solid #cbd5e1;
        padding:0.5rem 0.8rem; border-radius:8px; cursor:pointer; font-size:0.82rem; font-weight:600;
    }
    .tpmp-btn--ghost:hover { background:#f8fafc; }
    .tpmp-btn--xs { padding:0.2rem 0.45rem; font-size:0.68rem; font-weight:700; }
    .tpmp-btn--danger { background:#fff1f2; color:#b91c1c; border:1px solid #fecaca; }
    .tpmp-btn--danger:hover { background:#ffe4e6; }
    .tpmp-toolbar__actions { display:flex; flex-wrap:wrap; gap:0.45rem; align-items:center; }
    .tpmp-btn--block { width:100%; margin-top:0.5rem; }

    .tpmp-district-grid {
        display:grid;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:1rem;
        margin-top:0.25rem;
    }

    .tpmp-district-card {
        display:flex; flex-direction:column; gap:0.65rem;
        border:2px solid #e2e8f0; border-radius:12px; padding:0.85rem 0.9rem;
        background:#fff; box-shadow:0 1px 3px rgba(15,23,42,0.06);
        min-width:0; transition:border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .tpmp-district-card:hover { box-shadow:0 4px 14px rgba(15,23,42,0.08); }
    .tpmp-district-card--empty {
        border-color:#cbd5e1; background:linear-gradient(180deg, #f8fafc 0%, #fff 100%);
    }
    .tpmp-district-card--progress {
        border-color:#fcd34d; background:linear-gradient(180deg, #fffbeb 0%, #fff 55%);
        box-shadow:0 2px 8px rgba(245,158,11,0.12);
    }
    .tpmp-district-card--complete {
        border-color:#86efac; background:linear-gradient(180deg, #f0fdf4 0%, #fff 55%);
        box-shadow:0 2px 8px rgba(34,197,94,0.12);
    }

    .tpmp-district-card__head { display:flex; flex-direction:column; gap:0.35rem; }
    .tpmp-district-card__title-row { display:flex; align-items:flex-start; justify-content:space-between; gap:0.5rem; }
    .tpmp-district-card__title { margin:0; font-size:0.95rem; font-weight:800; color:#0f172a; line-height:1.25; }
    .tpmp-district-card__hub { font-size:0.72rem; color:#64748b; font-weight:600; margin-top:0.1rem; }
    .tpmp-district-card__badge {
        flex-shrink:0; padding:0.2rem 0.5rem; border-radius:999px; font-size:0.62rem; font-weight:800;
        text-transform:uppercase; letter-spacing:0.03em; white-space:nowrap;
    }
    .tpmp-district-card__badge--empty { background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; }
    .tpmp-district-card__badge--progress { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
    .tpmp-district-card__badge--complete { background:#dcfce7; color:#166534; border:1px solid #86efac; }

    .tpmp-district-card__progress { margin-top:0.15rem; }
    .tpmp-district-card__progress-text {
        display:flex; justify-content:space-between; font-size:0.72rem; font-weight:700; color:#475569; margin-bottom:0.25rem;
    }
    .tpmp-district-card__progress-bar {
        height:0.35rem; border-radius:999px; background:#e2e8f0; overflow:hidden;
    }
    .tpmp-district-card__progress-bar span {
        display:block; height:100%; border-radius:999px;
        background:linear-gradient(90deg, #6366f1, #22c55e);
    }
    .tpmp-district-card--complete .tpmp-district-card__progress-bar span { background:#22c55e; }

    .tpmp-sessions { display:flex; flex-direction:column; gap:0.4rem; }
    .tpmp-sessions__label {
        margin:0; font-size:0.68rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; color:#3730a3;
    }
    .tpmp-sessions__label--extra { color:#9a3412; margin-top:0.35rem; }
    .tpmp-sessions__empty { font-size:0.78rem; color:#94a3b8; font-style:italic; padding:0.35rem 0; }

    .tpmp-session-item { display:flex; align-items:flex-start; gap:0.4rem; }
    .tpmp-session-num {
        flex:0 0 auto; width:1.25rem; height:1.25rem; margin-top:0.35rem;
        display:inline-flex; align-items:center; justify-content:center;
        border-radius:999px; font-size:0.65rem; font-weight:800;
        background:#eef2ff; border:1px solid #c7d2fe; color:#3730a3;
    }
    .tpmp-session-num--extra { background:#ffedd5; border-color:#fdba74; color:#9a3412; }

    .tpmp-session-card {
        flex:1; min-width:0; border:1px solid #e2e8f0; border-radius:8px;
        padding:0.45rem 0.55rem; background:#fff;
    }
    .tpmp-session-card--filled { border-color:#86efac; background:#f8fdf9; }
    .tpmp-session-card--open { border-color:#bfdbfe; background:#f8fbff; }
    .tpmp-session-card--extra { border-color:#fdba74; background:#fffaf5; }

    .tpmp-session-card__meta {
        font-size:0.68rem; color:#b45309; font-weight:600; margin-bottom:0.25rem; line-height:1.35;
    }
    .tpmp-session-card__name-row {
        display:flex; align-items:center; justify-content:space-between; gap:0.35rem; min-width:0;
    }
    .tpmp-session-card__name-text {
        font-size:0.8rem; font-weight:700; color:#0f172a;
        overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-width:0;
    }
    .tpmp-session-card__input {
        width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:6px;
        padding:0.38rem 0.5rem; font-size:0.8rem;
    }
    .tpmp-session-card__name-row.is-hidden,
    .tpmp-session-card__input.is-hidden { display:none; }
    .tpmp-session-card__actions { display:flex; justify-content:flex-end; margin-top:0.3rem; }

    .tpmp-save-bar {
        position:sticky; bottom:0; z-index:5; margin-top:0.5rem;
        padding:0.75rem 1rem; background:rgba(255,255,255,0.95); border:1px solid #e2e8f0;
        border-radius:12px; backdrop-filter:blur(6px);
        display:flex; align-items:center; justify-content:space-between; gap:0.75rem; flex-wrap:wrap;
    }
    .tpmp-save-bar__hint { font-size:0.8rem; color:#64748b; }

    @media (max-width: 1100px) {
        .tpmp-district-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 680px) {
        .tpmp-district-grid { grid-template-columns:1fr; }
        .tpmp-toolbar { flex-direction:column; align-items:stretch; }
        .tpmp-toolbar__right { justify-content:flex-start; }
    }
</style>
@endpush

@section('content')
<div class="tpmp-shell">
    <p class="tpmp-intro">
        Set how many <strong>required BST sessions</strong> each district should run in the selected month.
        These counts appear as <strong>Deliverables → 3.1</strong> targets. Staff submit attendance against each session slot.
    </p>

    @if (!empty($migrationMissing))
        <div class="tpmp-alert tpmp-alert--warning">
            <strong>Training package month sessions table not found.</strong> Run <code>php artisan migrate</code> first.
        </div>
    @endif

    @if (session('status'))
        <div class="tpmp-alert tpmp-alert--success">{{ session('status') }}</div>
    @endif

    <div class="tpmp-toolbar">
        <div class="tpmp-toolbar__left">
            <form method="get" action="{{ route('admin.training-package-month-plans.index') }}" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:flex-end;">
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
                <button type="submit" class="tpmp-btn">Load</button>
            </form>
        </div>
        <div class="tpmp-toolbar__right">
            <div class="tpmp-stats">
                <div class="tpmp-stat">
                    <div class="tpmp-stat__label">Target</div>
                    <div class="tpmp-stat__value">{{ number_format((int) ($statewideSummary['required'] ?? 0)) }}</div>
                </div>
                <div class="tpmp-stat">
                    <div class="tpmp-stat__label">Filled</div>
                    <div class="tpmp-stat__value">{{ number_format((int) ($statewideSummary['filled'] ?? 0)) }}</div>
                </div>
                <div class="tpmp-stat">
                    <div class="tpmp-stat__label">Remaining</div>
                    <div class="tpmp-stat__value">{{ number_format((int) ($statewideSummary['remaining'] ?? 0)) }}</div>
                </div>
            </div>
            <div class="tpmp-toolbar__actions">
                <form method="post" action="{{ route('admin.training-package-month-plans.assign-default-sessions') }}" onsubmit="return confirm('Assign Session 1 and Session 2 to every district without targets for this month?');">
                    @csrf
                    <input type="hidden" name="calendar_year" value="{{ $calendarYear }}">
                    <input type="hidden" name="calendar_month" value="{{ $calendarMonth }}">
                    <button type="submit" class="tpmp-btn--ghost" @disabled(!empty($migrationMissing))>Quick fill: 2 sessions / district</button>
                </form>
                <form method="post" action="{{ route('admin.training-package-month-plans.clear-all-sessions') }}" onsubmit="return confirm('Remove ALL session targets for this month statewide? Filled sessions and their attendance will also be deleted.');">
                    @csrf
                    <input type="hidden" name="calendar_year" value="{{ $calendarYear }}">
                    <input type="hidden" name="calendar_month" value="{{ $calendarMonth }}">
                    <button type="submit" class="tpmp-btn--ghost tpmp-btn--danger" @disabled(!empty($migrationMissing))>Remove all</button>
                </form>
            </div>
        </div>
    </div>

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
                    $districtPct = $districtTarget > 0 ? min(100, (int) round(($districtFilled / $districtTarget) * 100)) : 0;
                    $cardStatus = $districtTarget === 0
                        ? 'empty'
                        : ($districtFilled >= $districtTarget ? 'complete' : ($districtFilled > 0 ? 'progress' : 'empty'));
                    $badgeLabel = match ($cardStatus) {
                        'complete' => 'Complete',
                        'progress' => 'In progress',
                        default => $districtTarget === 0 ? 'No target' : 'Not started',
                    };
                @endphp
                <section class="tpmp-district-card tpmp-district-card--{{ $cardStatus }}" data-district-index="{{ $districtIndex }}">
                    <div class="tpmp-district-card__head">
                        <div class="tpmp-district-card__title-row">
                            <div>
                                <h3 class="tpmp-district-card__title">{{ $district->name }}</h3>
                                @if ($district->hub?->name)
                                    <div class="tpmp-district-card__hub">{{ $district->hub->name }}</div>
                                @endif
                            </div>
                            <span class="tpmp-district-card__badge tpmp-district-card__badge--{{ $cardStatus }}">{{ $badgeLabel }}</span>
                        </div>
                        @if ($districtTarget > 0)
                            <div class="tpmp-district-card__progress">
                                <div class="tpmp-district-card__progress-text">
                                    <span>{{ number_format($districtFilled) }} / {{ number_format($districtTarget) }} filled</span>
                                    <span>{{ $districtPct }}%</span>
                                </div>
                                <div class="tpmp-district-card__progress-bar">
                                    <span style="width:{{ $districtPct }}%;"></span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <input type="hidden" name="districts[{{ $districtIndex }}][district_id]" value="{{ $district->id }}">

                    <div class="tpmp-sessions">
                        <h4 class="tpmp-sessions__label">Required sessions (target)</h4>
                        <div class="js-session-list" data-district-index="{{ $districtIndex }}">
                            @forelse ($slots as $slotIndex => $slot)
                                @php
                                    $slotPackage = $slot->trainingPackage;
                                    $occupied = $slotPackage !== null;
                                    $slotIsDraft = $occupied && (bool) ($slotPackage->is_draft ?? false);
                                    $filled = $occupied && ! $slotIsDraft;
                                @endphp
                                <div class="tpmp-session-item js-session-row">
                                    <span class="tpmp-session-num" aria-hidden="true">{{ $slotIndex + 1 }}</span>
                                    <div class="tpmp-session-card @if($filled) tpmp-session-card--filled @else tpmp-session-card--open @endif">
                                        @if ($occupied)
                                            <div class="tpmp-session-card__meta">
                                                {{ $slotIsDraft ? 'Draft' : 'Filled' }} · {{ $slotPackage->submitted_by_name }} · {{ $slotPackage->event_date?->format('d M Y') ?: 'NA' }}
                                            </div>
                                        @endif
                                        @if ($slot->id)
                                            <input type="hidden" name="districts[{{ $districtIndex }}][sessions][{{ $slotIndex }}][id]" value="{{ $slot->id }}">
                                        @endif
                                        <div class="tpmp-session-card__name-row @if(!$slot->session_name) is-hidden @endif">
                                            <span class="tpmp-session-card__name-text">{{ $slot->session_name ?: 'Session '.($slotIndex + 1) }}</span>
                                            <button type="button" class="tpmp-btn--ghost tpmp-btn--xs js-edit-session-name">Edit</button>
                                        </div>
                                        <input class="tpmp-session-card__input @if($slot->session_name) is-hidden @endif" type="text" name="districts[{{ $districtIndex }}][sessions][{{ $slotIndex }}][session_name]" value="{{ $slot->session_name }}" placeholder="Session {{ $slotIndex + 1 }} name" maxlength="191">
                                        <div class="tpmp-session-card__actions">
                                            @if ($slot->id)
                                                <button type="button" class="tpmp-btn--ghost tpmp-btn--danger tpmp-btn--xs js-delete-session" data-delete-url="{{ route('admin.training-package-month-plans.sessions.destroy', $slot) }}">Delete</button>
                                            @else
                                                <button type="button" class="tpmp-btn--ghost tpmp-btn--danger tpmp-btn--xs js-remove-session">Remove</button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="tpmp-sessions__empty">No sessions assigned — add below or use quick fill.</div>
                            @endforelse
                        </div>

                        @if ($extraSlots->isNotEmpty())
                            <h4 class="tpmp-sessions__label tpmp-sessions__label--extra">Extra sessions</h4>
                            <div class="js-extra-session-list" data-district-index="{{ $districtIndex }}">
                                @foreach ($extraSlots as $extraIndex => $extraSlot)
                                    @php
                                        $extraPackage = $extraSlot->trainingPackage;
                                        $extraOccupied = $extraPackage !== null;
                                        $extraIsDraft = $extraOccupied && (bool) ($extraPackage->is_draft ?? false);
                                    @endphp
                                    <div class="tpmp-session-item">
                                        <span class="tpmp-session-num tpmp-session-num--extra" aria-hidden="true">{{ $extraIndex + 1 }}</span>
                                        <div class="tpmp-session-card tpmp-session-card--extra">
                                            @if ($extraOccupied)
                                                <div class="tpmp-session-card__meta">
                                                    {{ $extraIsDraft ? 'Draft' : 'Filled' }} · {{ $extraPackage->submitted_by_name }} · {{ $extraPackage->event_date?->format('d M Y') ?: 'NA' }}
                                                </div>
                                            @endif
                                            <input type="hidden" name="districts[{{ $districtIndex }}][extra_sessions][{{ $extraIndex }}][id]" value="{{ $extraSlot->id }}">
                                            <div class="tpmp-session-card__name-row @if(!$extraSlot->session_name) is-hidden @endif">
                                                <span class="tpmp-session-card__name-text">{{ $extraSlot->session_name ?: 'Extra '.($extraIndex + 1) }}</span>
                                                <button type="button" class="tpmp-btn--ghost tpmp-btn--xs js-edit-session-name">Edit</button>
                                            </div>
                                            <input class="tpmp-session-card__input @if($extraSlot->session_name) is-hidden @endif" type="text" name="districts[{{ $districtIndex }}][extra_sessions][{{ $extraIndex }}][session_name]" value="{{ $extraSlot->session_name }}" placeholder="Extra session name" maxlength="191">
                                            <div class="tpmp-session-card__actions">
                                                <button type="button" class="tpmp-btn--ghost tpmp-btn--danger tpmp-btn--xs js-delete-session" data-delete-url="{{ route('admin.training-package-month-plans.sessions.destroy', $extraSlot) }}">Delete</button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <button type="button" class="tpmp-btn--ghost tpmp-btn--block js-add-session" data-district-index="{{ $districtIndex }}">+ Add session</button>
                </section>
            @endforeach
        </div>

        <div class="tpmp-save-bar">
            <span class="tpmp-save-bar__hint">Save after editing session targets for {{ $monthOptions[$calendarMonth] ?? $calendarMonth }} {{ $calendarYear }}.</span>
            <button type="submit" class="tpmp-btn" @disabled(!empty($migrationMissing))>Save all districts</button>
        </div>
    </form>
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

            const emptyMsg = list.querySelector('.tpmp-sessions__empty');
            if (emptyMsg) {
                emptyMsg.remove();
            }

            const sessionIndex = nextSessionIndex(districtIndex);
            const row = document.createElement('div');
            row.className = 'tpmp-session-item js-session-row';
            row.innerHTML =
                '<span class="tpmp-session-num" aria-hidden="true">' + (sessionIndex + 1) + '</span>' +
                '<div class="tpmp-session-card tpmp-session-card--open">' +
                    '<div class="tpmp-session-card__name-row is-hidden">' +
                        '<span class="tpmp-session-card__name-text">Session ' + (sessionIndex + 1) + '</span>' +
                        '<button type="button" class="tpmp-btn--ghost tpmp-btn--xs js-edit-session-name">Edit</button>' +
                    '</div>' +
                    '<input class="tpmp-session-card__input" type="text" name="districts[' + districtIndex + '][sessions][' + sessionIndex + '][session_name]" placeholder="Session ' + (sessionIndex + 1) + ' name" maxlength="191">' +
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
        const input = card.querySelector('.tpmp-session-card__input');
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
            const input = card ? card.querySelector('.tpmp-session-card__input') : null;
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
        if (!(target instanceof HTMLInputElement) || !target.classList.contains('tpmp-session-card__input')) {
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

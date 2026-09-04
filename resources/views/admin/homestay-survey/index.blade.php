@extends('layouts.admin')

@section('title', 'Homestay Survey — State Admin')
@section('heading', 'Homestay Survey')

@push('styles')
<style>
    .hs-admin {
        --hs-brand: #26a69a;
        --hs-brand-deep: #00897b;
        --hs-brand-light: #e0f2f1;
        --hs-border: #e8ecf1;
        --hs-muted: #78909c;
        --hs-navy: #263238;
        --hs-radius: 16px;
        font-family: 'DM Sans', system-ui, sans-serif;
        color: #37474f;
    }
    .hs-admin__toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding: 1rem 1.1rem;
        background: #fff;
        border: 1px solid var(--hs-border);
        border-radius: var(--hs-radius);
        box-shadow: 0 2px 12px rgba(55, 71, 79, .06);
    }
    .hs-admin__toolbar h2 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--hs-navy);
    }
    .hs-admin__toolbar p {
        margin: .2rem 0 0;
        font-size: .84rem;
        color: var(--hs-muted);
    }
    .hs-admin__actions { display: flex; flex-wrap: wrap; gap: .45rem; }
    .hs-admin__btn {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .48rem .85rem;
        border-radius: 10px;
        background: linear-gradient(135deg, #00897b, #26a69a);
        color: #fff;
        font-weight: 700;
        font-size: .84rem;
        text-decoration: none;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(38, 166, 154, .22);
    }
    .hs-admin__btn:hover { filter: brightness(1.05); color: #fff; }
    .hs-admin__btn--ghost {
        background: #fff;
        color: var(--hs-brand-deep);
        border: 1px solid #80cbc4;
        box-shadow: none;
    }
    .hs-admin__btn--ghost:hover { background: var(--hs-brand-light); filter: none; }
    .hs-admin__lock {
        background: #fff;
        border: 1px solid var(--hs-border);
        border-radius: var(--hs-radius);
        padding: .9rem 1.1rem;
        margin-bottom: 1rem;
        display: flex;
        flex-wrap: wrap;
        gap: .75rem 1.25rem;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 2px 12px rgba(55, 71, 79, .04);
    }
    .hs-admin__lock-copy strong {
        display: block;
        font-size: .95rem;
        color: var(--hs-navy);
    }
    .hs-admin__lock-copy span {
        font-size: .8rem;
        color: var(--hs-muted);
    }
    .hs-admin__toggle-form { margin: 0; }
    .hs-admin__toggle {
        display: inline-flex;
        align-items: center;
        gap: .7rem;
        cursor: pointer;
        user-select: none;
    }
    .hs-admin__toggle input {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        pointer-events: none;
    }
    .hs-admin__toggle-track {
        position: relative;
        width: 3.1rem;
        height: 1.7rem;
        border-radius: 999px;
        background: #cfd8dc;
        border: 1px solid #b0bec5;
        transition: background .18s ease, border-color .18s ease;
        flex: 0 0 auto;
    }
    .hs-admin__toggle-track::after {
        content: "";
        position: absolute;
        top: 2px;
        left: 2px;
        width: 1.3rem;
        height: 1.3rem;
        border-radius: 999px;
        background: #fff;
        box-shadow: 0 1px 4px rgba(38, 50, 56, .25);
        transition: transform .18s ease;
    }
    .hs-admin__toggle:has(input:checked) .hs-admin__toggle-track {
        background: linear-gradient(135deg, #00897b, #26a69a);
        border-color: #00897b;
    }
    .hs-admin__toggle:has(input:checked) .hs-admin__toggle-track::after {
        transform: translateX(1.35rem);
    }
    .hs-admin__toggle:has(input:focus-visible) .hs-admin__toggle-track {
        box-shadow: 0 0 0 3px rgba(38, 166, 154, .22);
    }
    .hs-admin__toggle-labels {
        display: flex;
        flex-direction: column;
        gap: .1rem;
        min-width: 5.5rem;
    }
    .hs-admin__toggle-state {
        font-size: .9rem;
        font-weight: 700;
        color: var(--hs-navy);
    }
    .hs-admin__toggle-hint {
        font-size: .72rem;
        color: var(--hs-muted);
    }
    .hs-admin__filters {
        background: #fff;
        border: 1px solid var(--hs-border);
        border-radius: var(--hs-radius);
        padding: .85rem 1rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 12px rgba(55, 71, 79, .04);
    }
    .hs-admin__row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: flex-end; }
    .hs-admin__field { display: flex; flex-direction: column; gap: .2rem; min-width: 140px; flex: 1; }
    .hs-admin__field label {
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #607d8b;
    }
    .hs-admin__input {
        padding: .48rem .65rem;
        border: 1px solid var(--hs-border);
        border-radius: 10px;
        font: inherit;
        font-size: .85rem;
        background: #fff;
    }
    .hs-admin__input:focus {
        outline: none;
        border-color: var(--hs-brand);
        box-shadow: 0 0 0 3px rgba(38, 166, 154, .16);
    }
    .hs-admin__table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        border: 1px solid var(--hs-border);
        border-radius: var(--hs-radius);
        overflow: hidden;
        font-size: .88rem;
        box-shadow: 0 2px 12px rgba(55, 71, 79, .04);
    }
    .hs-admin__table th, .hs-admin__table td {
        padding: .65rem .75rem;
        border-bottom: 1px solid var(--hs-border);
        text-align: left;
    }
    .hs-admin__table th {
        background: #f5f7fa;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #607d8b;
    }
    .hs-admin__table a { color: var(--hs-brand-deep); font-weight: 600; text-decoration: none; }
    .hs-admin__table a:hover { text-decoration: underline; }
    .hs-admin__empty {
        padding: 2rem;
        text-align: center;
        color: var(--hs-muted);
        background: #fff;
        border: 1px dashed #cfd8dc;
        border-radius: var(--hs-radius);
    }
    .hs-admin__badge {
        display: inline-block;
        padding: .15rem .45rem;
        border-radius: 999px;
        background: var(--hs-brand-light);
        color: var(--hs-brand-deep);
        font-size: .72rem;
        font-weight: 700;
        border: 1px solid #b2dfdb;
    }
    .hs-admin__badge--locked { background: #ffebee; color: #c62828; border-color: #ffcdd2; }
    .hs-admin__badge--open { background: var(--hs-brand-light); color: var(--hs-brand-deep); border-color: #b2dfdb; }
</style>
@endpush

@section('content')
<div class="hs-admin">
    <div class="hs-admin__toolbar">
        <div>
            @php
                $filteredTotal = $responses->total();
                $hasFilters = collect($filters)->contains(fn ($v) => $v !== '');
            @endphp
            <h2>
                @if ($hasFilters)
                    {{ number_format($filteredTotal) }} of {{ number_format($total) }} response{{ $total === 1 ? '' : 's' }}
                @else
                    {{ number_format($total) }} response{{ $total === 1 ? '' : 's' }}
                @endif
            </h2>
            <p>{{ $publicUrl }}</p>
        </div>
        <div class="hs-admin__actions">
            <a class="hs-admin__btn hs-admin__btn--ghost" href="{{ $publicUrl }}" target="_blank" rel="noopener">Open form</a>
            <a class="hs-admin__btn hs-admin__btn--ghost" href="{{ route('admin.homestay-survey.analysis-export', array_filter($filters)) }}">Analysis Excel</a>
            <a class="hs-admin__btn" href="{{ route('admin.homestay-survey.export', array_filter($filters)) }}">Export</a>
        </div>
    </div>

    <div class="hs-admin__lock">
        <div class="hs-admin__lock-copy">
            <strong>Prefill fields</strong>
            <span>Lock stops applicants editing profile fields loaded from MUY</span>
        </div>
        <form method="post" action="{{ route('admin.homestay-survey.prefill-lock') }}" class="hs-admin__toggle-form" id="hs-prefill-lock-form">
            @csrf
            <input type="hidden" name="prefill_locked" id="hs-prefill-locked-value" value="{{ $prefillLocked ? 1 : 0 }}">
            <label class="hs-admin__toggle">
                <input
                    type="checkbox"
                    id="hs-prefill-lock-toggle"
                    @checked($prefillLocked)
                    aria-label="Lock prefill fields"
                >
                <span class="hs-admin__toggle-track" aria-hidden="true"></span>
                <span class="hs-admin__toggle-labels">
                    <span class="hs-admin__toggle-state" id="hs-prefill-lock-state">{{ $prefillLocked ? 'Locked' : 'Editable' }}</span>
                    <span class="hs-admin__toggle-hint">{{ $prefillLocked ? 'ON' : 'OFF' }}</span>
                </span>
            </label>
        </form>
    </div>

    <form class="hs-admin__filters" method="get" action="{{ route('admin.homestay-survey.index') }}">
        <div class="hs-admin__row">
            <div class="hs-admin__field">
                <label>Search</label>
                <input class="hs-admin__input" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name, phone, app no">
            </div>
            <div class="hs-admin__field">
                <label>Phase</label>
                <select class="hs-admin__input" name="phase">
                    <option value="">All</option>
                    @foreach (['Phase 1', 'Phase 2', 'Phase 3'] as $p)
                        <option value="{{ $p }}" @selected($filters['phase'] === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hs-admin__field">
                <label>District</label>
                <select class="hs-admin__input" name="district">
                    <option value="">All</option>
                    @foreach ($districts as $d)
                        <option value="{{ $d }}" @selected($filters['district'] === $d)>{{ $d }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hs-admin__field">
                <label>Acceleration support</label>
                <select class="hs-admin__input" name="acceleration">
                    <option value="">All</option>
                    @foreach (['Yes', 'No'] as $opt)
                        <option value="{{ $opt }}" @selected($filters['acceleration'] === $opt)>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="hs-admin__btn">Filter</button>
            <a class="hs-admin__btn hs-admin__btn--ghost" href="{{ route('admin.homestay-survey.index') }}">Reset</a>
        </div>
    </form>

    @if ($responses->isEmpty())
        <div class="hs-admin__empty">{{ $hasFilters ? 'No matching responses.' : 'No responses yet.' }}</div>
    @else
        <div style="overflow:auto;">
            <table class="hs-admin__table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Submitted</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>District</th>
                        <th>Phase</th>
                        <th>App no</th>
                        <th>Acceleration</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($responses as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ optional($row->submitted_at)->timezone(config('app.timezone'))->format('d M Y, g:i A') }}</td>
                            <td>{{ $row->applicant_name ?: '—' }}</td>
                            <td>{{ $row->phone }}</td>
                            <td>{{ $row->district ?: '—' }}</td>
                            <td><span class="hs-admin__badge">{{ $row->phase ?: '—' }}</span></td>
                            <td>{{ $row->application_no ?: '—' }}</td>
                            @php
                                $answers = is_array($row->answers) ? $row->answers : [];
                                $acceleration = $answers['acceleration_support'] ?? ($answers['other_support'] ?? '');
                                if (is_array($acceleration)) {
                                    $acceleration = implode(', ', array_map('strval', $acceleration));
                                }
                            @endphp
                            <td>{{ $acceleration !== '' ? $acceleration : '—' }}</td>
                            <td><a href="{{ route('admin.homestay-survey.show', $row) }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;">{{ $responses->links() }}</div>
    @endif
</div>
<script>
(() => {
    const form = document.getElementById('hs-prefill-lock-form');
    const toggle = document.getElementById('hs-prefill-lock-toggle');
    const hidden = document.getElementById('hs-prefill-locked-value');
    const state = document.getElementById('hs-prefill-lock-state');
    const hint = document.querySelector('#hs-prefill-lock-form .hs-admin__toggle-hint');
    if (!form || !toggle || !hidden) return;

    toggle.addEventListener('change', () => {
        const locked = toggle.checked;
        hidden.value = locked ? '1' : '0';
        if (state) state.textContent = locked ? 'Locked' : 'Editable';
        if (hint) hint.textContent = locked ? 'ON' : 'OFF';
        form.submit();
    });
})();
</script>
@endsection

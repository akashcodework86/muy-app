@extends('layouts.admin')

@section('title', 'Team performance')
@section('heading', 'Team performance')

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<style>
    :root {
        --tp-indigo: #4f46e5;
        --tp-violet: #7c3aed;
        --tp-teal: #0d9488;
        --tp-amber: #f59e0b;
        --tp-text: #0f172a;
        --tp-muted: #64748b;
        --tp-border: rgba(148, 163, 184, 0.35);
        --tp-soft: rgba(99, 102, 241, 0.08);
    }
    .tp-shell {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        padding-bottom: 3rem;
        font-family: 'DM Sans', sans-serif;
    }
    .tp-intro {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.08), rgba(124, 58, 237, 0.06) 45%, rgba(13, 148, 136, 0.05));
        border: 1px solid rgba(99, 102, 241, 0.18);
        border-radius: 20px;
        padding: 1.1rem 1.4rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
        justify-content: space-between;
    }
    .tp-intro h2 {
        margin: 0 0 0.2rem;
        font-size: 1.1rem;
        color: var(--tp-text);
        font-weight: 800;
    }
    .tp-intro p { margin: 0; font-size: 0.85rem; color: var(--tp-muted); max-width: 46rem; }
    .tp-intro__fy {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: #fff;
        border: 1px solid var(--tp-border);
        border-radius: 999px;
        padding: 0.35rem 0.85rem;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--tp-text);
    }
    .tp-intro__fy select {
        border: none;
        background: transparent;
        font: inherit;
        color: inherit;
        outline: none;
        cursor: pointer;
    }
    .tp-intro__fy i { color: var(--tp-indigo); }

    .tp-stat-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.75rem;
    }
    .tp-stat {
        background: #fff;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 16px;
        padding: 0.85rem 1rem;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    }
    .tp-stat__label { font-size: 0.72rem; color: var(--tp-muted); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; }
    .tp-stat__value { font-size: 1.45rem; font-weight: 800; color: var(--tp-text); margin-top: 0.15rem; }
    .tp-stat__sub { font-size: 0.72rem; color: var(--tp-muted); margin-top: 0.15rem; }

    /* --- Tree --- */
    .tp-tree {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.4rem;
    }
    .tp-root {
        width: min(100%, 320px);
    }
    .tp-node {
        position: relative;
        background: linear-gradient(135deg, #fff 0%, #fff 60%, rgba(248, 250, 252, 1) 100%);
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 18px;
        padding: 1rem 1.1rem;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
        cursor: pointer;
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        user-select: none;
    }
    .tp-node:hover { transform: translateY(-2px); box-shadow: 0 20px 52px rgba(15, 23, 42, 0.12); }
    .tp-node.is-state {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 45%, #7c3aed 100%);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 24px 60px rgba(79, 70, 229, 0.4);
    }
    .tp-node.is-hub {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.08), rgba(124, 58, 237, 0.06));
        border-color: rgba(99, 102, 241, 0.28);
    }
    .tp-node.is-district {
        background: linear-gradient(135deg, rgba(13, 148, 136, 0.07), rgba(45, 212, 191, 0.05));
        border-color: rgba(13, 148, 136, 0.28);
    }
    .tp-node__row {
        display: flex;
        align-items: center;
        gap: 0.7rem;
    }
    .tp-node__icon {
        width: 2.4rem;
        height: 2.4rem;
        min-width: 2.4rem;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.18);
        color: #fff;
        font-size: 1.05rem;
    }
    .tp-node.is-hub .tp-node__icon { background: rgba(79, 70, 229, 0.14); color: var(--tp-indigo); }
    .tp-node.is-district .tp-node__icon { background: rgba(13, 148, 136, 0.15); color: var(--tp-teal); }
    .tp-node__info { flex: 1; min-width: 0; }
    .tp-node__label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        opacity: 0.78;
        font-weight: 700;
    }
    .tp-node__name {
        font-size: 1.05rem;
        font-weight: 800;
        letter-spacing: -0.01em;
        margin-top: 0.1rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .tp-node.is-state .tp-node__name { font-size: 1.1rem; }
    .tp-node__count {
        text-align: right;
        min-width: 4.5rem;
    }
    .tp-node__count-num {
        font-size: 1.7rem;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.02em;
    }
    .tp-node.is-state .tp-node__count-num { font-size: 2.1rem; }
    .tp-node__count-label { font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.7; margin-top: 0.2rem; font-weight: 700; }
    .tp-node__chevron {
        width: 1.7rem;
        height: 1.7rem;
        min-width: 1.7rem;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, 0.06);
        color: inherit;
        transition: transform 0.25s ease, background 0.15s ease;
        font-size: 0.75rem;
    }
    .tp-node.is-state .tp-node__chevron { background: rgba(255, 255, 255, 0.2); }
    .tp-node[data-open="true"] .tp-node__chevron { transform: rotate(180deg); }

    .tp-node__bar {
        margin-top: 0.7rem;
        height: 5px;
        background: rgba(15, 23, 42, 0.08);
        border-radius: 999px;
        overflow: hidden;
    }
    .tp-node.is-state .tp-node__bar { background: rgba(255, 255, 255, 0.24); }
    .tp-node__bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #7c3aed, #4f46e5);
        border-radius: 999px;
        transition: width 0.6s ease;
    }
    .tp-node.is-state .tp-node__bar-fill { background: linear-gradient(90deg, #fff, rgba(255,255,255,0.7)); }
    .tp-node.is-district .tp-node__bar-fill { background: linear-gradient(90deg, #0d9488, #2dd4bf); }

    .tp-node__meta {
        margin-top: 0.55rem;
        font-size: 0.78rem;
        opacity: 0.85;
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem 1rem;
    }
    .tp-node__meta span i { margin-right: 0.35rem; opacity: 0.75; }

    /* --- Branches --- */
    .tp-branch {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1rem;
        width: 100%;
        position: relative;
        padding-top: 1.6rem;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s ease, padding-top 0.3s ease, opacity 0.25s ease 0.05s;
        opacity: 0;
    }
    .tp-branch[data-open="true"] {
        max-height: 10000px;
        opacity: 1;
    }
    /* Vertical connector from parent to branch */
    .tp-branch::before {
        content: '';
        position: absolute;
        top: 0;
        left: 50%;
        width: 2px;
        height: 1.4rem;
        background: rgba(99, 102, 241, 0.35);
        transform: translateX(-50%);
    }
    .tp-branch > .tp-child-wrap {
        position: relative;
    }
    /* Top tick on each child connecting up */
    .tp-branch > .tp-child-wrap::before {
        content: '';
        position: absolute;
        top: -1.4rem;
        left: 50%;
        width: 2px;
        height: 1.4rem;
        background: rgba(99, 102, 241, 0.35);
        transform: translateX(-50%);
    }

    /* Nested district branch: single-column since we list staff as rows */
    .tp-branch--districts { grid-template-columns: 1fr; gap: 0.8rem; }

    /* Staff list */
    .tp-staff-panel {
        margin-top: 0.9rem;
        background: #fff;
        border: 1px dashed rgba(13, 148, 136, 0.4);
        border-radius: 14px;
        padding: 0.6rem;
        display: none;
    }
    .tp-staff-panel[data-open="true"] {
        display: block;
        animation: tpSlide 0.35s ease;
    }
    @keyframes tpSlide {
        from { opacity: 0; transform: translateY(-6px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .tp-staff-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.55rem 0.7rem;
        border-radius: 10px;
        transition: background 0.15s ease;
    }
    .tp-staff-row + .tp-staff-row { border-top: 1px solid rgba(226, 232, 240, 0.7); }
    .tp-staff-row:hover { background: rgba(13, 148, 136, 0.06); }
    .tp-staff-avatar {
        width: 2rem;
        height: 2rem;
        min-width: 2rem;
        border-radius: 50%;
        background: linear-gradient(135deg, #0d9488, #2dd4bf);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.78rem;
        font-weight: 700;
        overflow: hidden;
    }
    .tp-staff-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .tp-staff-info { flex: 1; min-width: 0; }
    .tp-staff-name {
        font-weight: 700;
        font-size: 0.88rem;
        color: var(--tp-text);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .tp-staff-meta {
        font-size: 0.72rem;
        color: var(--tp-muted);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .tp-staff-row .tp-staff-chip {
        font-size: 0.7rem;
        padding: 0.15rem 0.5rem;
        border-radius: 999px;
        background: rgba(13, 148, 136, 0.1);
        color: #0f766e;
        font-weight: 700;
    }
    .tp-staff-row.is-inactive .tp-staff-avatar { background: #94a3b8; }
    .tp-staff-row.is-inactive .tp-staff-chip { background: rgba(148, 163, 184, 0.2); color: #475569; }
    .tp-staff-count {
        text-align: right;
        min-width: 3.5rem;
    }
    .tp-staff-count-num {
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--tp-text);
    }
    .tp-staff-count-bar {
        height: 3px;
        background: rgba(148, 163, 184, 0.25);
        border-radius: 999px;
        margin-top: 0.25rem;
        overflow: hidden;
    }
    .tp-staff-count-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #0d9488, #2dd4bf);
        border-radius: 999px;
        transition: width 0.5s ease;
    }

    .tp-unassigned {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.5rem 0.75rem;
        margin-top: 0.55rem;
        background: rgba(245, 158, 11, 0.1);
        border: 1px dashed rgba(245, 158, 11, 0.45);
        border-radius: 10px;
        font-size: 0.78rem;
        color: #92400e;
    }
    .tp-unassigned strong { font-weight: 800; }

    .tp-empty {
        padding: 0.65rem 0.85rem;
        color: var(--tp-muted);
        font-size: 0.85rem;
        font-style: italic;
        background: rgba(148, 163, 184, 0.08);
        border-radius: 10px;
        text-align: center;
    }

    .tp-soon {
        margin-top: 1rem;
        padding: 0.65rem 0.85rem;
        background: rgba(99, 102, 241, 0.06);
        border: 1px dashed rgba(99, 102, 241, 0.35);
        border-radius: 12px;
        font-size: 0.78rem;
        color: #3730a3;
        text-align: center;
    }
    .tp-soon i { margin-right: 0.4rem; }

    @media (max-width: 720px) {
        .tp-branch::before, .tp-branch > .tp-child-wrap::before { display: none; }
        .tp-branch { padding-top: 0.8rem; }
        .tp-root { width: 100%; }
    }
</style>
@endpush

@section('content')
@php
    // Indian-style number formatting (1,00,000) without requiring the intl extension.
    $nf = new class {
        public function format($n) {
            $n = (int) $n;
            if ($n < 0) { return '-'.$this->format(-$n); }
            if ($n < 1000) { return (string) $n; }
            $s = (string) $n;
            $last3 = substr($s, -3);
            $rest = substr($s, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            return $rest.','.$last3;
        }
    };
@endphp

<div class="tp-shell">
    <section class="tp-intro">
        <div>
            <h2><i class="fa-solid fa-sitemap" style="color:var(--tp-indigo);"></i> Team performance tree</h2>
            <p>Click the state card to reveal hubs, then a hub for its districts, then a district to see how many CFAs each staff member has brought in. Other services will appear here soon.</p>
        </div>
        <form method="get" action="{{ route('admin.team-performance.index') }}" class="tp-intro__fy">
            <i class="fa-solid fa-calendar-days"></i>
            <label for="fy-select" class="sr-only">Fiscal year</label>
            <select name="fy" id="fy-select" onchange="this.form.submit()">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected($activeFy && $fy->id === $activeFy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </form>
    </section>

    <section class="tp-stat-row">
        <div class="tp-stat">
            <div class="tp-stat__label">Total CFAs</div>
            <div class="tp-stat__value">{{ $nf->format($stateTotal) }}</div>
            <div class="tp-stat__sub">FY {{ $activeFy?->name ?? '—' }}</div>
        </div>
        <div class="tp-stat">
            <div class="tp-stat__label">Hubs</div>
            <div class="tp-stat__value">{{ $totalHubs }}</div>
            <div class="tp-stat__sub">Operating across state</div>
        </div>
        <div class="tp-stat">
            <div class="tp-stat__label">Districts</div>
            <div class="tp-stat__value">{{ $totalDistricts }}</div>
            <div class="tp-stat__sub">Under all hubs</div>
        </div>
        <div class="tp-stat">
            <div class="tp-stat__label">District staff</div>
            <div class="tp-stat__value">{{ $nf->format($totalStaff) }}</div>
            <div class="tp-stat__sub">Referral active &amp; inactive</div>
        </div>
    </section>

    <section class="tp-tree">
        {{-- STATE ROOT --}}
        <div class="tp-root">
            <div class="tp-node is-state" data-tp-toggle="state" data-open="false" role="button" tabindex="0" aria-expanded="false" aria-controls="tp-branch-state">
                <div class="tp-node__row">
                    <div class="tp-node__icon"><i class="fa-solid fa-building-columns"></i></div>
                    <div class="tp-node__info">
                        <div class="tp-node__label">State</div>
                        <div class="tp-node__name">Uttarakhand</div>
                    </div>
                    <div class="tp-node__count">
                        <div class="tp-node__count-num">{{ $nf->format($stateTotal) }}</div>
                        <div class="tp-node__count-label">CFAs</div>
                    </div>
                    <div class="tp-node__chevron"><i class="fa-solid fa-chevron-down"></i></div>
                </div>
                <div class="tp-node__bar"><div class="tp-node__bar-fill" style="width:100%;"></div></div>
                <div class="tp-node__meta">
                    <span><i class="fa-solid fa-layer-group"></i>{{ $totalHubs }} hubs</span>
                    <span><i class="fa-solid fa-map-location-dot"></i>{{ $totalDistricts }} districts</span>
                    <span><i class="fa-solid fa-users"></i>{{ $nf->format($totalStaff) }} staff</span>
                </div>
            </div>

            {{-- HUBS branch --}}
            <div class="tp-branch" id="tp-branch-state" data-open="false">
                @foreach ($hubs as $hub)
                    @php
                        $hubPct = $hub['pct_of_state'];
                        $hubKey = 'hub-'.$hub['id'];
                    @endphp
                    <div class="tp-child-wrap">
                        <div class="tp-node is-hub" data-tp-toggle="{{ $hubKey }}" data-open="false" role="button" tabindex="0" aria-expanded="false" aria-controls="tp-branch-{{ $hubKey }}">
                            <div class="tp-node__row">
                                <div class="tp-node__icon"><i class="fa-solid fa-layer-group"></i></div>
                                <div class="tp-node__info">
                                    <div class="tp-node__label">Hub</div>
                                    <div class="tp-node__name">{{ $hub['name'] }}</div>
                                </div>
                                <div class="tp-node__count">
                                    <div class="tp-node__count-num">{{ $nf->format($hub['cfa_count']) }}</div>
                                    <div class="tp-node__count-label">{{ $hubPct }}% of state</div>
                                </div>
                                <div class="tp-node__chevron"><i class="fa-solid fa-chevron-down"></i></div>
                            </div>
                            <div class="tp-node__bar"><div class="tp-node__bar-fill" style="width:{{ max(2, $hubPct) }}%;"></div></div>
                            <div class="tp-node__meta">
                                <span><i class="fa-solid fa-map-location-dot"></i>{{ $hub['district_count'] }} districts</span>
                                <span><i class="fa-solid fa-users"></i>{{ $hub['staff_count'] }} staff</span>
                            </div>
                        </div>

                        {{-- DISTRICTS branch --}}
                        <div class="tp-branch tp-branch--districts" id="tp-branch-{{ $hubKey }}" data-open="false">
                            @forelse ($hub['districts'] as $district)
                                @php $districtKey = 'district-'.$district['id']; @endphp
                                <div class="tp-child-wrap">
                                    <div class="tp-node is-district" data-tp-toggle="{{ $districtKey }}" data-open="false" role="button" tabindex="0" aria-expanded="false" aria-controls="tp-branch-{{ $districtKey }}">
                                        <div class="tp-node__row">
                                            <div class="tp-node__icon"><i class="fa-solid fa-location-dot"></i></div>
                                            <div class="tp-node__info">
                                                <div class="tp-node__label">District</div>
                                                <div class="tp-node__name">{{ $district['name'] }}</div>
                                            </div>
                                            <div class="tp-node__count">
                                                <div class="tp-node__count-num">{{ $nf->format($district['cfa_count']) }}</div>
                                                <div class="tp-node__count-label">{{ $district['pct_of_hub'] }}% of hub</div>
                                            </div>
                                            <div class="tp-node__chevron"><i class="fa-solid fa-chevron-down"></i></div>
                                        </div>
                                        <div class="tp-node__bar"><div class="tp-node__bar-fill" style="width:{{ max(2, $district['pct_of_hub']) }}%;"></div></div>
                                        <div class="tp-node__meta">
                                            <span><i class="fa-solid fa-user-tie"></i>{{ count($district['staff']) }} staff</span>
                                            @if ($district['unassigned_count'] > 0)
                                                <span><i class="fa-solid fa-circle-question" style="color:#d97706;"></i>{{ $nf->format($district['unassigned_count']) }} unassigned</span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- STAFF panel --}}
                                    <div class="tp-staff-panel" id="tp-branch-{{ $districtKey }}" data-open="false">
                                        @forelse ($district['staff'] as $staff)
                                            @php
                                                $staffMax = $district['cfa_count'] > 0 ? $district['cfa_count'] : 1;
                                                $staffPctBar = min(100, ($staff['cfa_count'] / $staffMax) * 100);
                                                $initials = collect(preg_split('/\s+/', trim($staff['name'])) ?: [])->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
                                            @endphp
                                            <div class="tp-staff-row @if (! $staff['is_active']) is-inactive @endif">
                                                <div class="tp-staff-avatar">
                                                    @if ($staff['avatar_url'])
                                                        <img src="{{ $staff['avatar_url'] }}" alt="">
                                                    @else
                                                        {{ strtoupper($initials ?: 'S') }}
                                                    @endif
                                                </div>
                                                <div class="tp-staff-info">
                                                    <div class="tp-staff-name">{{ $staff['name'] }}</div>
                                                    <div class="tp-staff-meta">{{ $staff['email'] ?? '' }}@if ($staff['phone']) · {{ $staff['phone'] }}@endif</div>
                                                </div>
                                                <span class="tp-staff-chip">{{ $staff['pct_of_district'] }}%</span>
                                                <div class="tp-staff-count">
                                                    <div class="tp-staff-count-num">{{ $nf->format($staff['cfa_count']) }}</div>
                                                    <div class="tp-staff-count-bar"><div class="tp-staff-count-bar-fill" style="width:{{ $staffPctBar }}%;"></div></div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="tp-empty">No staff mapped to this district yet.</div>
                                        @endforelse

                                        @if ($district['unassigned_count'] > 0)
                                            <div class="tp-unassigned">
                                                <i class="fa-solid fa-circle-exclamation"></i>
                                                <span><strong>{{ $nf->format($district['unassigned_count']) }}</strong> CFA{{ $district['unassigned_count'] === 1 ? '' : 's' }} have no active referral mapping (walk-in / direct / ex-staff).</span>
                                            </div>
                                        @endif

                                        <div class="tp-soon">
                                            <i class="fa-solid fa-hourglass-half"></i>
                                            Service delivery numbers (per staff, per service) will appear here once service cases go live.
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="tp-empty">No districts mapped to this hub.</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach

                @if (empty($hubs))
                    <div class="tp-empty">No hubs configured yet.</div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        // Toggle any node -> branch pair
        function toggle(node) {
            const key = node.getAttribute('data-tp-toggle');
            if (!key) return;
            const branch = document.getElementById('tp-branch-' + key);
            if (!branch) return;
            const isOpen = node.getAttribute('data-open') === 'true';
            const next = !isOpen;
            node.setAttribute('data-open', next ? 'true' : 'false');
            node.setAttribute('aria-expanded', next ? 'true' : 'false');
            branch.setAttribute('data-open', next ? 'true' : 'false');
            if (branch.classList.contains('tp-staff-panel') === false) {
                // Smoothly expand height without layout jump
                if (next) {
                    branch.style.maxHeight = branch.scrollHeight + 'px';
                    // After transition, set to auto so nested opens don't get clipped
                    setTimeout(() => {
                        if (branch.getAttribute('data-open') === 'true') {
                            branch.style.maxHeight = 'none';
                        }
                    }, 420);
                } else {
                    // First set a concrete height, then transition to 0
                    branch.style.maxHeight = branch.scrollHeight + 'px';
                    // Force reflow
                    void branch.offsetWidth;
                    branch.style.maxHeight = '0px';
                    // When re-opening later, reset inline style
                    setTimeout(() => {
                        if (branch.getAttribute('data-open') === 'false') {
                            branch.style.maxHeight = '';
                        }
                    }, 420);
                }
            }
        }

        document.querySelectorAll('[data-tp-toggle]').forEach((node) => {
            node.addEventListener('click', (e) => {
                // Avoid double-trigger on nested clicks when target is a link
                if (e.target.closest('a')) return;
                toggle(node);
            });
            node.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggle(node);
                }
            });
        });

        // Auto-open the state root after a brief delay for a polished first impression.
        const stateNode = document.querySelector('[data-tp-toggle="state"]');
        if (stateNode) {
            setTimeout(() => toggle(stateNode), 250);
        }
    })();
</script>
@endpush

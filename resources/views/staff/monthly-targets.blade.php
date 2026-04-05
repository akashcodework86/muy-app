@extends('layouts.admin')

@section('title', 'Monthly activity targets')
@section('heading', 'Monthly activity targets')

@push('styles')
<style>
    .staff-mis-note { font-size:0.85rem; color:#64748b; margin:0 0 0.75rem; line-height:1.5; }
    .staff-mis-legend {
        font-size:0.8rem; color:#334155; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;
        padding:0.55rem 0.75rem; margin-bottom:1rem; line-height:1.45;
    }
    .staff-mis-empty {
        font-size:0.88rem; color:#92400e; background:#fffbeb; border:1px solid #fcd34d; border-radius:8px;
        padding:0.75rem 1rem; margin-bottom:1rem; line-height:1.5;
    }
    .staff-mis-toc {
        display:flex; flex-wrap:wrap; gap:0.35rem 0.65rem; align-items:center; margin-bottom:1.25rem;
        padding:0.65rem 0.75rem; background:#f1f5f9; border-radius:8px; border:1px solid #e2e8f0; font-size:0.78rem;
    }
    .staff-mis-toc > span { font-weight:600; color:#475569; margin-right:0.35rem; }
    .staff-mis-toc a {
        color:#1d4ed8; text-decoration:none; padding:0.15rem 0.4rem; border-radius:4px; background:#fff;
        border:1px solid #dbeafe; max-width:14rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    }
    .staff-mis-toc a:hover { text-decoration:underline; background:#eff6ff; }
    .staff-mis-fy-hint { font-size:0.8rem; color:#64748b; margin-left:0.75rem; }
    .staff-mis-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:0.75rem; margin-bottom:1.25rem; }
    .staff-mis-show-all {
        font-size:0.8rem; font-weight:600; padding:0.4rem 0.85rem; border-radius:8px; border:1px solid #cbd5e1;
        background:#fff; color:#334155; cursor:pointer; font-family:inherit;
    }
    .staff-mis-show-all:hover { border-color:#94a3b8; background:#f8fafc; }
    .staff-mis-footnote { font-size:0.72rem; color:#64748b; margin-top:0.45rem; line-height:1.4; }
    details.staff-mis-card {
        background:#fff; border:1px solid #e4e4e7; border-radius:10px; margin-bottom:1rem;
        overflow:hidden; box-shadow:0 1px 3px rgba(15,23,42,0.06);
    }
    details.staff-mis-card > summary {
        list-style:none; cursor:pointer; padding:0.65rem 1rem;
        background:linear-gradient(180deg,#ecfdf5 0%,#d1fae5 100%);
        border-bottom:1px solid #a7f3d0;
        display:flex; flex-wrap:wrap; align-items:baseline; gap:0.5rem 1rem;
        position:sticky; top:0; z-index:4;
        padding-left:1.75rem;
    }
    details.staff-mis-card > summary::-webkit-details-marker { display:none; }
    details.staff-mis-card > summary::before {
        content:'▸'; position:absolute; left:0.65rem; top:50%; transform:translateY(-50%);
        font-size:0.7rem; color:#047857; transition:transform 0.15s ease;
    }
    details.staff-mis-card[open] > summary::before { transform:translateY(-50%) rotate(90deg); }
    .staff-mis-card__title { font-weight:700; font-size:0.95rem; color:#065f46; margin:0; }
    .staff-mis-card__meta { font-size:0.75rem; color:#047857; max-width:100%; }
    .staff-mis-card__body { padding:0.75rem 1rem 1rem; }
    .staff-mis-summary { font-size:0.82rem; color:#334155; line-height:1.55; margin-bottom:0.75rem; }
    .staff-mis-summary strong { color:#0f172a; }
    .staff-mis-strip { display:flex; align-items:stretch; gap:0.75rem; margin-top:0.35rem; }
    .staff-mis-strip__pin {
        flex:0 0 10.5rem; min-width:9rem; align-self:stretch; display:flex; flex-direction:column; justify-content:flex-end;
        padding-bottom:0.35rem; font-size:0.7rem; font-weight:600; color:#475569; text-transform:uppercase;
        letter-spacing:0.03em; line-height:1.35; border-right:1px solid #e2e8f0; padding-right:0.65rem;
    }
    .staff-mis-scroll { flex:1; min-width:0; overflow-x:auto; -webkit-overflow-scrolling:touch; padding-bottom:0.25rem; }
    .staff-mis-table { border-collapse:separate; border-spacing:4px; font-size:0.75rem; min-width:max-content; }
    .staff-mis-table th {
        background:#d1fae5; color:#065f46; font-weight:700; padding:0.35rem 0.45rem; text-align:center;
        border-radius:4px; white-space:nowrap;
    }
    .staff-mis-table td {
        background:#fdf2f8; color:#831843; text-align:center; padding:0.4rem 0.5rem; border-radius:4px;
        font-weight:600; white-space:nowrap; min-width:4.5rem;
    }
    .staff-mis-table td.staff-mis-na { background:#f1f5f9; color:#64748b; font-weight:500; }
    .staff-mis-table tfoot td {
        background:#e0e7ff; color:#312e81; font-weight:700; font-size:0.78rem; text-align:center;
        padding:0.5rem 0.65rem; border-radius:6px;
    }
    .staff-mis-cfa-link { font-size:0.8rem; margin-top:0.5rem; }
    .staff-mis-cfa-link input { width:100%; max-width:36rem; margin-top:0.35rem; padding:0.4rem; font-size:0.78rem; border:1px solid #d4d4d8; border-radius:6px; }
    .staff-mis-print-meta { display:none; font-size:0.75rem; color:#64748b; margin-top:1.5rem; padding-top:1rem; border-top:1px solid #e2e8f0; }
    @media print {
        .admin-topbar, .admin-page-head, .staff-mis-toolbar, .staff-mis-show-all, .staff-mis-toc { display:none !important; }
        .admin-main { padding:0 !important; max-width:100% !important; }
        details.staff-mis-card { break-inside:avoid; page-break-inside:avoid; border:1px solid #ccc; }
        details.staff-mis-card > summary { position:static; cursor:default; }
        details.staff-mis-card > summary::before { display:none; }
        details.staff-mis-card .staff-mis-card__body { display:block !important; }
        .staff-mis-print-meta { display:block !important; }
    }
</style>
@endpush

@section('content')
    @if (! empty($missingDeliverables))
        <p style="color:#64748b;">No active deliverables are configured. Contact your state admin.</p>
    @elseif (! empty($noDistrict))
        <p style="color:#64748b;">Your account has no district assigned. Contact your state admin.</p>
    @else
        @php
            $pageHasActivity = collect($rows)->contains(function (array $r) {
                return $r['monthlySum'] > 0
                    || $r['achievementAnnual'] > 0
                    || ($r['districtTarget'] ?? 0) > 0;
            });
            $collapsedCount = collect($rows)->where('expandByDefault', false)->count();
            $fyRange = $fiscalYear->starts_on && $fiscalYear->ends_on
                ? $fiscalYear->starts_on->format('j M Y').' – '.$fiscalYear->ends_on->format('j M Y')
                : null;
        @endphp

        <p class="staff-mis-note">
            <strong>Read-only.</strong> Same MIS activities as state / district targets. Monthly allocations are set by the <strong>state admin</strong> (per deliverable). CFA achievement counts your referral submissions in this fiscal year; other activities show “—” until the app records them.
        </p>
        <p class="staff-mis-legend" role="note">
            <strong>How to read each cell:</strong> first number = <strong>achievement</strong> (done this month), second = <strong>your target</strong>, then <strong>(%)</strong> of target met. A dash <strong>—</strong> for achievement means this activity is not tracked in the app yet.
        </p>

        @unless ($pageHasActivity)
            <div class="staff-mis-empty" role="status">
                <strong>No targets or progress yet</strong> for this fiscal year on your account (no district targets, no monthly allocation, and no CFA submissions counted). Contact your state admin if you expect numbers here.
            </div>
        @endunless

        <p style="font-size:0.9rem; color:#52525b; margin-bottom:0.75rem;">
            <strong>{{ $user->name }}</strong>
            <span style="display:inline-block; margin-left:0.35rem; padding:0.15rem 0.45rem; background:#f4f4f5; border-radius:6px; font-size:0.75rem;">district_staff</span>
            · District: <strong>{{ $user->district?->name ?? '—' }}</strong>
            @if ($user->designationRecord?->name)
                · Designation: <strong>{{ $user->designationRecord->name }}</strong>
            @endif
        </p>

        <div class="staff-mis-toolbar">
            <form method="get" action="{{ route('staff.monthly-targets') }}" style="display:flex; flex-wrap:wrap; gap:0.5rem; align-items:flex-end; margin:0;">
                <div>
                    <label for="fy" style="display:block; font-size:0.8rem; font-weight:500; margin-bottom:0.25rem;">Fiscal year</label>
                    <select id="fy" name="fiscal_year_id" onchange="this.form.submit()" style="padding:0.4rem 0.5rem; border-radius:6px; border:1px solid #d4d4d8; min-width:12rem;">
                        @foreach ($fiscalYears as $fy)
                            <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($fyRange)
                    <span class="staff-mis-fy-hint">Covers <strong>{{ $fyRange }}</strong> (M1 = first month of this FY).</span>
                @endif
            </form>
            @if ($collapsedCount > 0)
                <button type="button" class="staff-mis-show-all" id="staffMisExpandAll" aria-expanded="false">
                    Show all activities ({{ $collapsedCount }} collapsed)
                </button>
            @endif
        </div>

        <nav class="staff-mis-toc" aria-label="Jump to activity">
            <span>Jump to:</span>
            @foreach ($rows as $block)
                @php $d = $block['deliverable']; @endphp
                <a href="#mis-del-{{ $d->id }}">{{ $d->sort_order }}. {{ \Illuminate\Support\Str::limit($d->name, 42) }}</a>
            @endforeach
        </nav>

        @foreach ($rows as $block)
            @php
                $d = $block['deliverable'];
                $isCfa = $d->code === 'cfa';
                $open = $block['expandByDefault'];
                $ms = (int) $block['monthlySum'];
                $ach = $block['tracksAchievement'] ? (int) $block['achievementAnnual'] : null;
                $fyPct = ($ms > 0 && $ach !== null) ? min(100, (int) round(($ach / $ms) * 100)) : null;
            @endphp
            <details class="staff-mis-card" id="mis-del-{{ $d->id }}" @if ($open) open @endif>
                <summary class="staff-mis-card__head">
                    <h2 class="staff-mis-card__title">{{ $d->sort_order }}. {{ $d->name }}</h2>
                    <span class="staff-mis-card__meta">{{ $d->mis_entry_label }}</span>
                </summary>
                <div class="staff-mis-card__body">
                    @if ($block['districtTarget'] === null)
                        <p style="font-size:0.85rem; color:#92400e; background:#fffbeb; border:1px solid #fcd34d; padding:0.6rem 0.75rem; border-radius:6px; margin:0 0 0.75rem;">
                            District target for this activity is <strong>not set</strong> for {{ $fiscalYear->name }}. Ask your state admin after district targets are entered.
                        </p>
                    @endif
                    @if ($block['districtTarget'] !== null)
                        <div class="staff-mis-summary">
                            <strong>District target (annual):</strong> {{ number_format($block['districtTarget']) }}
                            · <strong>Other staff in district (annual total):</strong> {{ number_format($block['othersAnnual']) }}
                            · <strong>Your annual allocation (12 months):</strong> {{ number_format($block['slot'] ?? 0) }}
                            @if ($isCfa)
                                · <strong>Your CFA achieved (FY):</strong> {{ number_format($block['achievementAnnual']) }}
                            @endif
                            <br>
                            <strong>Your monthly target sum:</strong> {{ number_format($block['monthlySum']) }}
                            @if ($block['slot'] !== null)
                                @if ($block['monthlySum'] === (int) $block['slot'])
                                    <span style="color:#047857;">(matches allocation)</span>
                                @else
                                    <span style="color:#b45309;">(allocation is {{ number_format($block['slot']) }} — contact state admin if this differs)</span>
                                @endif
                            @endif
                        </div>
                    @endif

                    @if ($isCfa && $applyUrl)
                        <div class="staff-mis-cfa-link">
                            <span style="font-size:0.8rem; color:#52525b;">CFA applicant form link (your referrals only):</span><br>
                            <input type="text" readonly value="{{ $applyUrl }}" onclick="this.select()" aria-label="Referral URL">
                        </div>
                    @endif

                    <p style="font-size:0.72rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.04em; margin:1rem 0 0.35rem;">Monthly target vs achievement</p>
                    <div class="staff-mis-strip">
                        <div class="staff-mis-strip__pin" aria-hidden="true">
                            Achievement / target / %
                        </div>
                        <div class="staff-mis-scroll">
                            <table class="staff-mis-table" role="grid" aria-label="Monthly breakdown for {{ $d->name }}">
                                <thead>
                                    <tr>
                                        @foreach (range(1, 12) as $m)
                                            <th scope="col">{{ $monthLabels[$m] ?? ('M'.$m) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        @foreach (range(1, 12) as $m)
                                            @php
                                                $t = (int) ($block['monthlyTarget'][$m] ?? 0);
                                                $a = $block['monthlyAchievement'][$m];
                                                $pct = ($t > 0 && $a !== null) ? min(100, (int) round(($a / $t) * 100)) : null;
                                            @endphp
                                            <td @class(['staff-mis-na' => $a === null && $t === 0])>
                                                @if ($a === null)
                                                    <span title="Not tracked in app for this activity yet">—</span>
                                                @else
                                                    {{ number_format($a) }}
                                                @endif
                                                / {{ number_format($t) }}
                                                @if ($pct !== null)
                                                    ({{ $pct }}%)
                                                @elseif ($t > 0 && $a === null)
                                                    (—%)
                                                @else
                                                    (0%)
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="12">
                                            <strong>FY total (your numbers):</strong>
                                            @if ($block['tracksAchievement'])
                                                {{ number_format((int) $block['achievementAnnual']) }} / {{ number_format($ms) }}
                                                @if ($fyPct !== null)
                                                    ({{ $fyPct }}% of annual target sum)
                                                @elseif ($ms > 0)
                                                    (—%)
                                                @else
                                                    (0%)
                                                @endif
                                            @else
                                                — / {{ number_format($ms) }}
                                                <span style="font-weight:500;color:#475569;"> (achievement not in app yet)</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <p class="staff-mis-footnote">“—” for monthly achievement means the system does not record that activity per month yet (only CFA is wired today).</p>
                </div>
            </details>
        @endforeach

        <p class="staff-mis-print-meta">Printed {{ now()->timezone(config('app.timezone'))->format('d M Y, H:i') }} IST · {{ config('app.name') }}</p>
    @endif
@endsection

@push('scripts')
<script>
(function () {
    var btn = document.getElementById('staffMisExpandAll');
    if (!btn) return;
    btn.addEventListener('click', function () {
        document.querySelectorAll('details.staff-mis-card').forEach(function (el) {
            el.setAttribute('open', '');
        });
        btn.setAttribute('aria-expanded', 'true');
        btn.textContent = 'All activities expanded';
        btn.disabled = true;
    });
})();
</script>
@endpush

@extends('layouts.admin')

@section('title', 'Monthly targets')
@section('heading', 'Monthly targets')

@push('styles')
<style>
    .staff-mis-empty {
        font-size: 0.85rem;
        color: #92400e;
        background: #fffbeb;
        border: 1px solid #fcd34d;
        border-radius: 8px;
        padding: 0.65rem 0.85rem;
        margin-bottom: 1rem;
        line-height: 1.45;
    }
    .staff-mis-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .staff-mis-show-all {
        font-size: 0.8rem;
        font-weight: 600;
        padding: 0.4rem 0.85rem;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #334155;
        cursor: pointer;
        font-family: inherit;
    }
    .staff-mis-show-all:hover { border-color: #94a3b8; background: #f8fafc; }
    details.staff-mis-card {
        background: #fff;
        border: 1px solid #e4e4e7;
        border-radius: 10px;
        margin-bottom: 0.75rem;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    }
    details.staff-mis-card > summary {
        list-style: none;
        cursor: pointer;
        padding: 0.65rem 1rem 0.65rem 1.75rem;
        background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 100%);
        border-bottom: 1px solid #a7f3d0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.45rem 0.85rem;
        position: sticky;
        top: 0;
        z-index: 4;
    }
    details.staff-mis-card > summary::-webkit-details-marker { display: none; }
    details.staff-mis-card > summary::before {
        content: '▸';
        position: absolute;
        left: 0.65rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.7rem;
        color: #047857;
        transition: transform 0.15s ease;
    }
    details.staff-mis-card > summary { position: sticky; }
    details.staff-mis-card[open] > summary::before { transform: translateY(-50%) rotate(90deg); }
    .staff-mis-card__title {
        font-weight: 700;
        font-size: 0.92rem;
        color: #065f46;
        margin: 0;
        flex: 1 1 12rem;
        min-width: 0;
    }
    .staff-mis-card__badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        margin-left: auto;
    }
    .staff-mis-badge {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 0.2rem 0.5rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.75);
        border: 1px solid rgba(6, 95, 70, 0.2);
        color: #065f46;
        white-space: nowrap;
    }
    .staff-mis-card__body { padding: 0.75rem 1rem 1rem; }
    .staff-mis-warn {
        font-size: 0.82rem;
        color: #92400e;
        background: #fffbeb;
        border: 1px solid #fcd34d;
        padding: 0.5rem 0.65rem;
        border-radius: 6px;
        margin: 0 0 0.65rem;
    }
    .staff-mis-cfa-link { margin-bottom: 0.65rem; }
    .staff-mis-cfa-link input {
        width: 100%;
        max-width: 36rem;
        padding: 0.45rem 0.55rem;
        font-size: 0.78rem;
        border: 1px solid #d4d4d8;
        border-radius: 6px;
        background: #fafafa;
    }
    .staff-mis-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 0.25rem;
    }
    .staff-mis-table {
        border-collapse: separate;
        border-spacing: 4px;
        font-size: 0.75rem;
        min-width: max-content;
        width: 100%;
    }
    .staff-mis-table th {
        background: #d1fae5;
        color: #065f46;
        font-weight: 700;
        padding: 0.35rem 0.45rem;
        text-align: center;
        border-radius: 4px;
        white-space: nowrap;
    }
    .staff-mis-table td {
        background: #fdf2f8;
        color: #831843;
        text-align: center;
        padding: 0.4rem 0.5rem;
        border-radius: 4px;
        font-weight: 600;
        white-space: nowrap;
        min-width: 4.5rem;
    }
    .staff-mis-table td.staff-mis-na { background: #f1f5f9; color: #64748b; font-weight: 500; }
    .staff-mis-table tfoot td {
        background: #e0e7ff;
        color: #312e81;
        font-weight: 700;
        font-size: 0.78rem;
        text-align: center;
        padding: 0.5rem 0.65rem;
        border-radius: 6px;
    }
    .staff-mis-print-meta {
        display: none;
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #e2e8f0;
    }
    @media print {
        .admin-topbar, .admin-page-head, .staff-mis-toolbar, .staff-mis-show-all { display: none !important; }
        .admin-main { padding: 0 !important; max-width: 100% !important; }
        details.staff-mis-card { break-inside: avoid; page-break-inside: avoid; border: 1px solid #ccc; }
        details.staff-mis-card > summary { position: static; cursor: default; }
        details.staff-mis-card > summary::before { display: none; }
        details.staff-mis-card .staff-mis-card__body { display: block !important; }
        .staff-mis-print-meta { display: block !important; }
    }
</style>
@endpush

@section('content')
    @if (! empty($missingDeliverables))
        <p style="color:#64748b;">No active deliverables configured. Contact state admin.</p>
    @elseif (! empty($noDistrict))
        <p style="color:#64748b;">No district assigned. Contact state admin.</p>
    @else
        @php
            $pageHasActivity = collect($rows)->contains(function (array $r) {
                return $r['monthlySum'] > 0
                    || $r['achievementAnnual'] > 0
                    || ($r['districtTarget'] ?? 0) > 0;
            });
            $collapsedCount = collect($rows)->where('expandByDefault', false)->count();
            $fyEndDay = $fiscalYear->ends_on ? \Carbon\Carbon::parse($fiscalYear->ends_on)->endOfDay() : null;
            $fyAlreadyEnded = $fyEndDay && $fyEndDay->isPast();
        @endphp

        @unless ($pageHasActivity)
            <div class="staff-mis-empty" role="status">No targets or progress for this fiscal year.</div>
        @endunless

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
            </form>
            @if ($fyAlreadyEnded)
                <div class="staff-mis-empty" style="margin:0;background:#eff6ff;border-color:#93c5fd;color:#1e3a8a;">
                    FY closed {{ $fiscalYear->ends_on?->format('j M Y') ?? '—' }}. Select latest FY for recent achievements.
                </div>
            @endif
            @if ($collapsedCount > 0)
                <button type="button" class="staff-mis-show-all" id="staffMisExpandAll" aria-expanded="false">
                    Expand all ({{ $collapsedCount }})
                </button>
            @endif
        </div>

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
                    @if ($block['districtTarget'] !== null)
                        <div class="staff-mis-card__badges">
                            @if ($isCfa && $ach !== null)
                                <span class="staff-mis-badge">{{ number_format($ach) }} / {{ number_format($ms) }} FY</span>
                            @elseif ($ms > 0)
                                <span class="staff-mis-badge">Target {{ number_format($ms) }}</span>
                            @endif
                            @if ($fyPct !== null)
                                <span class="staff-mis-badge">{{ $fyPct }}%</span>
                            @endif
                        </div>
                    @endif
                </summary>
                <div class="staff-mis-card__body">
                    @if ($block['districtTarget'] === null)
                        <p class="staff-mis-warn">District target not set for {{ $fiscalYear->name }}.</p>
                    @endif

                    @if ($isCfa && $applyUrl)
                        <div class="staff-mis-cfa-link">
                            <input type="text" readonly value="{{ $applyUrl }}" onclick="this.select()" aria-label="CFA referral link" title="CFA referral link">
                        </div>
                    @endif

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
                                                —
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
                                        @if ($block['tracksAchievement'])
                                            FY: {{ number_format((int) $block['achievementAnnual']) }} / {{ number_format($ms) }}
                                            @if ($fyPct !== null)
                                                ({{ $fyPct }}%)
                                            @endif
                                        @else
                                            FY target: {{ number_format($ms) }}
                                        @endif
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
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
        btn.textContent = 'All expanded';
        btn.disabled = true;
    });
})();
</script>
@endpush

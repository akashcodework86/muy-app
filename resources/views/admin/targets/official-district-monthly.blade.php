@extends('layouts.admin')

@section('title', ($fyTargetsNavLabel ?? 'FY Targets').' — '.($pageTitleSuffix ?? (($hubOnlyPage ?? false) ? 'Hub' : 'District')))
@section('heading', ($fyTargetsNavLabel ?? 'FY Targets').' — '.($pageTitleSuffix ?? (($hubOnlyPage ?? false) ? 'Hub' : 'District')).(($readOnlyAudience ?? false) ? ' (read-only)' : ''))

@section('content')
    @php
        $hubOnlyPage = (bool) ($hubOnlyPage ?? false);
        $pageRouteUrl = route($pageRoute);
        $exportRouteUrl = route($exportRoute, ['fiscal_year_id' => $fiscalYearId]);
        $applyRouteName = $applyRoute ?? null;
        $applyRouteUrl = $applyRouteName ? route($applyRouteName) : null;
        $statePageUrl = route($statePageRoute, ['fiscal_year_id' => $fiscalYearId]);
        $districtPageUrl = route($districtPageRoute, ['fiscal_year_id' => $fiscalYearId]);
        $hubPageUrl = route($hubPageRoute, ['fiscal_year_id' => $fiscalYearId]);
        $displayBlocks = $hubOnlyPage ? ($hubDistributionBlocks ?? []) : ($districtBlocks ?? []);
        $allDistrictBlocks = $displayBlocks;
        $mismatchBlocks = collect($allDistrictBlocks)->filter(function (array $block) {
            $status = (string) (($block['verify_saved'] ?? [])['status'] ?? '');

            return in_array($status, ['over', 'under', 'no_state'], true);
        })->values()->all();
        $matchedBlocks = collect($allDistrictBlocks)->filter(function (array $block) {
            return (string) (($block['verify_saved'] ?? [])['status'] ?? '') === 'match';
        })->count();
        $mismatchCount = count($mismatchBlocks);
        $totalBlocks = count($allDistrictBlocks);
    @endphp

    <div class="odm-page-top">
        <form method="get" action="{{ $pageRouteUrl }}" class="odm-fy-bar">
            <div class="odm-fy-bar__field">
                <label for="fy">Fiscal year</label>
                <select id="fy" name="fiscal_year_id">
                    @foreach ($fiscalYears as $fy)
                        <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="odm-fy-bar__btn">Load</button>
            <a href="{{ $exportRouteUrl }}" class="odm-fy-bar__btn odm-fy-bar__btn--export">⬇ Export .xlsx</a>
            @if ($readOnlyAudience ?? false)
                <a href="{{ $statePageUrl }}" class="odm-fy-bar__btn">State targets</a>
                @if (! $hubOnlyPage)
                    <a href="{{ $hubPageUrl }}" class="odm-fy-bar__btn">Hub distribution</a>
                @else
                    <a href="{{ $districtPageUrl }}" class="odm-fy-bar__btn">District targets</a>
                @endif
            @endif
            <div class="odm-fy-bar__meta">
                <span class="odm-stat-pill odm-stat-pill--neutral">{{ $totalBlocks }} services</span>
                <span class="odm-stat-pill odm-stat-pill--ok" id="align-match-count">{{ $matchedBlocks }} matched</span>
                <span class="odm-stat-pill odm-stat-pill--alert" id="align-mismatch-count" @if ($mismatchCount === 0) style="display:none;" @endif>{{ $mismatchCount }} mismatch{{ $mismatchCount === 1 ? '' : 'es' }}</span>
            </div>
        </form>

        <div id="align-ok-banner" class="odm-align-ok" @if ($mismatchCount > 0) style="display:none;" @endif>
            <div class="odm-align-ok__icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></div>
            <div>
                <div class="odm-align-ok__title">All {{ $hubOnlyPage ? 'hub' : 'district' }} allocations match state targets</div>
                <div class="odm-align-ok__text">Every service block is aligned with targets saved on State target month wise for {{ $fiscalYear->name ?? 'this fiscal year' }}.</div>
            </div>
        </div>

        <section id="mismatch-summary" class="odm-align-alert" aria-live="polite" @if ($mismatchCount === 0) hidden @endif>
            <div class="odm-align-alert__head">
                <div class="odm-align-alert__icon" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="odm-align-alert__copy">
                    <div class="odm-align-alert__title">State vs district alignment issues</div>
                    <div class="odm-align-alert__text">
                        These services do not match targets from
                        <a href="{{ $statePageUrl }}">State targets</a>.
                        Use <strong>Put targets automatically</strong> or edit cells, then <strong>Update targets</strong>.
                    </div>
                </div>
                <div class="odm-align-alert__badge" id="mismatch-count">{{ $mismatchCount }} to fix</div>
            </div>
            <div id="mismatch-cards" class="odm-align-alert__grid">
                @foreach ($mismatchBlocks as $block)
                    @php
                        $verify = (array) ($block['verify_saved'] ?? []);
                        $blockIndex = (int) ($block['block_index'] ?? 0);
                        $serial = (string) ($block['mis_serial'] ?? '');
                        $name = (string) ($block['name'] ?? '');
                        $stateTotal = (int) ($block['state_saved_total'] ?? 0);
                        $districtTotal = (int) ($block['saved_state_total'] ?? 0);
                        $delta = abs($districtTotal - $stateTotal);
                    @endphp
                    <a href="#district-block-{{ $blockIndex }}" class="odm-mismatch-card">
                        <div class="odm-mismatch-card__top">
                            @if ($serial !== '')
                                <span class="odm-mismatch-card__serial">{{ $serial }}</span>
                            @endif
                            <span class="odm-mismatch-card__status odm-mismatch-card__status--{{ $verify['status'] ?? 'over' }}">{{ $verify['label'] ?? 'Mismatch' }}</span>
                        </div>
                        <div class="odm-mismatch-card__name">{{ $name }}</div>
                        <div class="odm-mismatch-card__metrics">
                            <div class="odm-mismatch-card__metric">
                                <span class="odm-mismatch-card__metric-label">State target</span>
                                <span class="odm-mismatch-card__metric-value">{{ number_format($stateTotal) }}</span>
                            </div>
                            <div class="odm-mismatch-card__metric">
                                <span class="odm-mismatch-card__metric-label">District total</span>
                                <span class="odm-mismatch-card__metric-value">{{ number_format($districtTotal) }}</span>
                            </div>
                            @if ($delta > 0)
                                <div class="odm-mismatch-card__metric odm-mismatch-card__metric--delta">
                                    <span class="odm-mismatch-card__metric-label">Difference</span>
                                    <span class="odm-mismatch-card__metric-value">{{ number_format($delta) }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="odm-mismatch-card__foot">
                            Jump to service <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    </div>

    <p style="font-size:0.88rem; color:#52525b; margin:0 0 1rem; max-width:58rem; line-height:1.55;">
        @if ($hubOnlyPage)
            Hub-distributed monthly targets for services allocated only across <strong>Almora</strong> and <strong>Pauri Garhwal</strong> (same layout as the official Excel plan).
            Cells show your <strong>last saved</strong> targets. Use <strong>Put targets automatically</strong> to fill from state targets or the official plan, then <strong>Update targets</strong> to save.
            Totals are cross-checked against
            <a href="{{ $statePageUrl }}" style="color:#1d4ed8; font-weight:600;">State targets</a>.
            Other district services are on
            <a href="{{ $districtPageUrl }}" style="color:#1d4ed8; font-weight:600;">District targets</a>.
        @else
            Official district / hub monthly plan (same layout as <strong>District Target Month Wise</strong> Excel).
            @if (! ($readOnlyAudience ?? false))
            Cells show your <strong>last saved</strong> targets. Use <strong>Put targets automatically</strong> to fill cells from the state target (when set) split across districts using the official plan ratios, or from the Excel plan if no state target exists. Edit if needed, then <strong>Update targets</strong> to save.
            @else
            Read-only view of saved district monthly targets.
            @endif
            District totals are cross-checked against targets saved on
            <a href="{{ $statePageUrl }}" style="color:#1d4ed8; font-weight:600;">State targets</a>.
            Hub-distributed services are on
            <a href="{{ $hubPageUrl }}" style="color:#1d4ed8; font-weight:600;">Hub distribution</a>.
        @endif
    </p>

    @if (session('status'))
        <p style="background:#f0fdf4; border:1px solid #86efac; color:#166534; padding:0.75rem; border-radius:8px; font-size:0.88rem; margin-bottom:1rem;">
            {{ session('status') }}
        </p>
    @endif

    @if ($errors->any())
        <div style="background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; padding:0.75rem; border-radius:8px; font-size:0.88rem; margin-bottom:1rem;">
            <ul style="margin:0.35rem 0 0 1.1rem; padding:0;">
                @foreach ($errors->all() as $error)
                    <li>{{ is_array($error) ? implode('; ', $error) : $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($applyRouteUrl)
    <form method="post" action="{{ $applyRouteUrl }}" id="official-district-form">
        @csrf
        <input type="hidden" name="fiscal_year_id" value="{{ $fiscalYearId }}">
        <input type="hidden" name="district_payload" id="district_payload" value="">
    @else
    <div id="official-district-form">
    @endif

        <div style="background:linear-gradient(135deg,#eff6ff,#f0fdf4); border:1px solid #93c5fd; border-radius:10px; padding:0.85rem 1rem; margin-bottom:1.25rem; font-size:0.88rem; display:flex; flex-wrap:wrap; gap:1rem; align-items:center;">
            <div><strong>Services / blocks:</strong> {{ count($displayBlocks) }}</div>
            @if (! $hubOnlyPage)
                <div><strong>State-only rows:</strong> {{ count($stateOnlyRows) }}</div>
            @endif
            @if ($targetsAllocationEditable ?? false)
            <button type="button" id="btn-auto-fill" style="background:#0369a1; color:#fff; border:none; padding:0.55rem 1rem; border-radius:8px; font-weight:700; cursor:pointer;">
                Put targets automatically
            </button>
            <button type="submit" style="background:#1d4ed8; color:#fff; border:none; padding:0.55rem 1rem; border-radius:8px; font-weight:700; cursor:pointer;"
                onclick="return confirm('Save these district monthly targets to the database?');">
                Update targets
            </button>
            @endif
        </div>

        @foreach ($displayBlocks as $block)
            @include('admin.targets.partials.official-district-block', [
                'block' => $block,
                'monthLabels' => $monthLabels,
                'targetsAllocationEditable' => $targetsAllocationEditable ?? true,
            ])
        @endforeach

        @if (! $hubOnlyPage && $stateOnlyRows !== [])
            <div style="margin-bottom:1rem; background:#fff; border:1px solid #e4e4e7; border-radius:10px; overflow:hidden;">
                <div style="padding:0.65rem 0.9rem; background:#1e3a8a; color:#fff; font-weight:700; font-size:0.9rem;">
                    State-level monthly targets (no district split)
                </div>
                <div style="overflow-x:auto;">
                    <table style="border-collapse:collapse; font-size:0.78rem; min-width:max-content; width:100%;" id="state-only-table">
                        <thead>
                            <tr>
                                <th style="padding:0.45rem 0.65rem; background:#dbeafe; border:1px solid #93c5fd; text-align:left;">S.N.</th>
                                <th style="padding:0.45rem 0.65rem; background:#dbeafe; border:1px solid #93c5fd; text-align:left; min-width:16rem;">Indicator</th>
                                <th style="padding:0.45rem 0.65rem; background:#dbeafe; border:1px solid #93c5fd; text-align:center;">Level</th>
                                @foreach ($monthLabels as $m => $label)
                                    <th style="padding:0.4rem 0.35rem; background:#dbeafe; border:1px solid #93c5fd; text-align:center;">{{ $label }}</th>
                                @endforeach
                                <th style="padding:0.45rem 0.65rem; background:#dbeafe; border:1px solid #93c5fd; text-align:center;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stateOnlyRows as $row)
                                @php
                                    $deliverableId = (int) ($row['deliverable']->id ?? 0);
                                    $mapped = (bool) ($row['mapped'] ?? false);
                                @endphp
                                <tr @if (! $mapped) style="background:#fef2f2;" @endif class="state-only-row" data-leaf="1">
                                    <td style="padding:0.4rem 0.65rem; border:1px solid #e4e4e7;">{{ $row['excel_sn'] ?? '' }}</td>
                                    <td style="padding:0.4rem 0.65rem; border:1px solid #e4e4e7;">
                                        <div style="font-weight:600;">{{ $row['mis_serial'] }} — {{ $row['name'] }}</div>
                                        @if (! $mapped)
                                            <div style="font-size:0.72rem; color:#b91c1c;">{{ $row['map_error'] ?? '' }}</div>
                                        @endif
                                    </td>
                                    <td style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-size:0.72rem; color:#64748b;">{{ $row['level'] ?? '' }}</td>
                                    @foreach (range(1, 12) as $m)
                                        <td style="padding:0.15rem; border:1px solid #e4e4e7; text-align:center;">
                                            <input type="number" min="0" step="1"
                                                value="{{ (int) ($row['saved_months'][$m] ?? 0) }}"
                                                data-official="{{ (int) ($row['official_months'][$m] ?? 0) }}"
                                                data-scope="state_only"
                                                @if ($mapped && $deliverableId > 0)
                                                    data-deliverable-id="{{ $deliverableId }}"
                                                @else
                                                    data-mis-serial="{{ (string) ($row['mis_serial'] ?? '') }}"
                                                    data-indicator-name="{{ (string) ($row['name'] ?? '') }}"
                                                @endif
                                                data-month="{{ $m }}"
                                                class="month-input"
                                                @if (! ($targetsAllocationEditable ?? true)) readonly disabled @endif
                                                style="width:2.75rem; padding:0.2rem; text-align:center; border:1px solid #d4d4d8; border-radius:4px; font-size:0.75rem;@if (! ($targetsAllocationEditable ?? true)) background:#f4f4f5; color:#52525b; @endif">
                                        </td>
                                    @endforeach
                                    <td class="row-total" style="padding:0.4rem; border:1px solid #e4e4e7; text-align:center; font-weight:700;">0</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @if ($applyRouteUrl)
    </form>
    @else
    </div>
    @endif

    @push('styles')
    <style>
        .odm-page-top {
            margin: -0.25rem 0 1.35rem;
            overflow: visible;
        }
        .odm-fy-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 0.75rem 1rem;
            padding: 0.9rem 1.1rem;
            background: #fff;
            border: 1px solid #e4e4e7;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            margin-bottom: 0.85rem;
        }
        .odm-fy-bar__field label {
            display: block;
            font-size: 0.76rem;
            font-weight: 600;
            color: #52525b;
            margin-bottom: 0.28rem;
        }
        .odm-fy-bar__field select {
            padding: 0.48rem 0.6rem;
            border-radius: 8px;
            border: 1px solid #d4d4d8;
            min-width: 11rem;
            font-size: 0.88rem;
            background: #fff;
        }
        .odm-fy-bar__btn {
            background: #18181b;
            color: #fff;
            border: none;
            padding: 0.52rem 0.95rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.86rem;
        }
        .odm-fy-bar__btn--export {
            text-decoration: none;
            background: #065f46;
            display: inline-flex;
            align-items: center;
        }
        .odm-fy-bar__meta {
            margin-left: auto;
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            align-items: center;
        }
        .odm-stat-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.32rem 0.7rem;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            border: 1px solid transparent;
        }
        .odm-stat-pill--neutral {
            background: #f4f4f5;
            color: #3f3f46;
            border-color: #e4e4e7;
        }
        .odm-stat-pill--ok {
            background: #ecfdf5;
            color: #047857;
            border-color: #86efac;
        }
        .odm-stat-pill--alert {
            background: #fef2f2;
            color: #b91c1c;
            border-color: #fca5a5;
        }
        .odm-stat-pill--hub {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #93c5fd;
            text-decoration: none;
        }
        .odm-stat-pill--hub:hover {
            background: #dbeafe;
        }
        .odm-align-ok {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            padding: 1rem 1.1rem;
            border-radius: 12px;
            border: 1px solid #86efac;
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
            box-shadow: 0 1px 2px rgba(4, 120, 87, 0.08);
        }
        .odm-align-ok__icon {
            width: 2.35rem;
            height: 2.35rem;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dcfce7;
            color: #047857;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .odm-align-ok__title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #166534;
        }
        .odm-align-ok__text {
            font-size: 0.82rem;
            color: #15803d;
            margin-top: 0.2rem;
            line-height: 1.45;
        }
        .odm-align-alert {
            border-radius: 14px;
            border: 1px solid #fca5a5;
            background: linear-gradient(180deg, #fff1f2 0%, #fef2f2 45%, #fff7ed 100%);
            box-shadow: 0 8px 24px rgba(185, 28, 28, 0.08);
            overflow: visible;
        }
        .odm-align-alert__head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 0.85rem 1rem;
            padding: 1rem 1.15rem;
            border-bottom: 1px solid #fecaca;
            background: rgba(255, 255, 255, 0.55);
            border-radius: 14px 14px 0 0;
        }
        .odm-align-alert__icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fee2e2;
            color: #dc2626;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .odm-align-alert__copy {
            flex: 1 1 16rem;
            min-width: 0;
        }
        .odm-align-alert__title {
            font-size: 1rem;
            font-weight: 800;
            color: #991b1b;
            letter-spacing: -0.01em;
        }
        .odm-align-alert__text {
            font-size: 0.82rem;
            color: #7f1d1d;
            margin-top: 0.25rem;
            line-height: 1.5;
            max-width: 42rem;
        }
        .odm-align-alert__text a {
            color: #b91c1c;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        .odm-align-alert__badge {
            margin-left: auto;
            align-self: center;
            padding: 0.45rem 0.85rem;
            border-radius: 999px;
            background: #dc2626;
            color: #fff;
            font-size: 0.78rem;
            font-weight: 800;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
        }
        .odm-align-alert__grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 0.85rem;
            padding: 1rem 1.15rem 1.25rem;
            align-items: start;
        }
        .odm-mismatch-card {
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            background: #fff;
            border: 1px solid #fca5a5;
            border-radius: 12px;
            padding: 0.95rem 1rem 1rem;
            height: auto;
            min-height: 0;
            box-sizing: border-box;
            transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
            box-shadow: 0 2px 8px rgba(127, 29, 29, 0.06);
        }
        .odm-mismatch-card:hover {
            transform: translateY(-2px);
            border-color: #f87171;
            box-shadow: 0 10px 24px rgba(185, 28, 28, 0.12);
        }
        .odm-mismatch-card__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.55rem;
        }
        .odm-mismatch-card__serial {
            display: inline-flex;
            align-items: center;
            padding: 0.18rem 0.5rem;
            border-radius: 6px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        .odm-mismatch-card__status {
            display: inline-flex;
            align-items: center;
            padding: 0.18rem 0.55rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 800;
            white-space: nowrap;
        }
        .odm-mismatch-card__status--over {
            background: #fef2f2;
            color: #b91c1c;
        }
        .odm-mismatch-card__status--under {
            background: #fffbeb;
            color: #b45309;
        }
        .odm-mismatch-card__status--no_state {
            background: #fff7ed;
            color: #c2410c;
        }
        .odm-mismatch-card__name {
            font-size: 0.9rem;
            font-weight: 700;
            color: #7f1d1d;
            line-height: 1.35;
            margin-bottom: 0.75rem;
        }
        .odm-mismatch-card__metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.55rem;
            margin-top: 0.5rem;
        }
        .odm-mismatch-card__metric--delta {
            grid-column: 1 / -1;
            background: #fef2f2;
            border-color: #f87171;
        }
        .odm-mismatch-card__metric {
            background: #fff5f5;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 0.5rem 0.6rem;
            min-width: 0;
        }
        .odm-mismatch-card__metric-label {
            display: block;
            font-size: 0.68rem;
            font-weight: 600;
            color: #9f1239;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .odm-mismatch-card__metric-value {
            display: block;
            margin-top: 0.15rem;
            font-size: 0.95rem;
            font-weight: 800;
            color: #991b1b;
        }
        .odm-mismatch-card__foot {
            margin-top: 0.75rem;
            padding-top: 0.65rem;
            border-top: 1px dashed #fecaca;
            font-size: 0.76rem;
            font-weight: 700;
            color: #dc2626;
        }
        .district-block {
            scroll-margin-top: 1.25rem;
        }
        .hub-distribution-group {
            margin-bottom: 1.5rem;
            background: #fff;
            border: 2px solid #3b82f6;
            border-radius: 12px;
            overflow: visible;
            box-shadow: 0 4px 16px rgba(30, 64, 175, 0.12);
            scroll-margin-top: 1.25rem;
        }
        .hub-distribution-group__summary {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem 1rem;
            padding: 0.85rem 1rem;
            cursor: pointer;
            list-style: none;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-bottom: 1px solid #bfdbfe;
            user-select: none;
        }
        .hub-distribution-group__summary::-webkit-details-marker {
            display: none;
        }
        .hub-distribution-group__summary::before {
            content: '▶';
            font-size: 0.7rem;
            color: #1d4ed8;
            transition: transform 0.15s ease;
        }
        .hub-distribution-group[open] > .hub-distribution-group__summary::before {
            content: '▼';
        }
        .hub-distribution-group__title {
            font-size: 0.95rem;
            font-weight: 800;
            color: #1e3a8a;
        }
        .hub-distribution-group__meta {
            font-size: 0.78rem;
            font-weight: 600;
            color: #3b82f6;
        }
        .hub-distribution-group__body {
            padding: 1rem 0.85rem 0.25rem;
            background: #f8fafc;
        }
        .hub-target-section {
            border-top: 1px solid #e4e4e7;
            padding: 0 0.9rem 0.65rem;
        }
        .hub-target-section > summary {
            padding: 0.55rem 0;
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 700;
            color: #1e40af;
            user-select: none;
            list-style: none;
        }
        .hub-target-section > summary::-webkit-details-marker {
            display: none;
        }
        .hub-target-section > summary::before {
            content: '▶ ';
            font-size: 0.7rem;
        }
        .hub-target-section[open] > summary::before {
            content: '▼ ';
        }
        .hub-target-section__count {
            font-weight: 600;
            color: #64748b;
        }
        .hub-target-section__body {
            overflow-x: auto;
            padding-bottom: 0.35rem;
        }
        @media (max-width: 720px) {
            .odm-fy-bar__meta {
                margin-left: 0;
                width: 100%;
            }
            .odm-align-alert__badge {
                margin-left: 0;
            }
            .odm-mismatch-card__metrics {
                grid-template-columns: 1fr;
            }
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        (function () {
            const form = document.getElementById('official-district-form');
            if (!form) return;

            function compareTotals(allocated, stateSaved) {
                if (stateSaved <= 0 && allocated <= 0) {
                    return { status: 'empty', label: 'No targets set', color: '#64748b', bg: '#f1f5f9' };
                }
                if (stateSaved <= 0) {
                    return { status: 'no_state', label: 'State target not set', color: '#b45309', bg: '#fffbeb' };
                }
                if (allocated === stateSaved) {
                    return { status: 'match', label: 'Match', color: '#047857', bg: '#ecfdf5' };
                }
                const delta = Math.abs(allocated - stateSaved);
                if (allocated > stateSaved) {
                    return { status: 'over', label: 'Over by ' + delta.toLocaleString(), color: '#b91c1c', bg: '#fef2f2' };
                }
                return { status: 'under', label: 'Under by ' + delta.toLocaleString(), color: '#b45309', bg: '#fffbeb' };
            }

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function updateMismatchSummary() {
                const summary = document.getElementById('mismatch-summary');
                const cardsEl = document.getElementById('mismatch-cards');
                const countEl = document.getElementById('mismatch-count');
                const okBanner = document.getElementById('align-ok-banner');
                const mismatchPill = document.getElementById('align-mismatch-count');
                const matchPill = document.getElementById('align-match-count');
                if (!summary || !cardsEl) {
                    return;
                }

                const mismatches = [];
                let matched = 0;
                form.querySelectorAll('.district-block[data-block="1"]').forEach(function (block) {
                    const stateTotal = parseInt(block.getAttribute('data-state-total') || '0', 10) || 0;
                    const grand = parseInt((block.querySelector('.footer-grand')?.textContent || '0').replace(/,/g, ''), 10) || 0;
                    const result = compareTotals(grand, stateTotal);
                    if (result.status === 'match') {
                        matched++;
                        return;
                    }
                    if (result.status === 'empty') {
                        return;
                    }

                    mismatches.push({
                        blockId: block.id,
                        serial: block.getAttribute('data-block-serial') || '',
                        name: block.getAttribute('data-block-name') || block.getAttribute('data-block-label') || 'Service',
                        stateTotal: stateTotal,
                        grand: grand,
                        delta: Math.abs(grand - stateTotal),
                        result: result,
                    });
                });

                summary.hidden = mismatches.length === 0;
                if (okBanner) {
                    okBanner.style.display = mismatches.length === 0 ? '' : 'none';
                }
                if (countEl) {
                    countEl.textContent = mismatches.length + ' to fix';
                }
                if (mismatchPill) {
                    mismatchPill.style.display = mismatches.length > 0 ? '' : 'none';
                    mismatchPill.textContent = mismatches.length + ' mismatch' + (mismatches.length === 1 ? '' : 'es');
                }
                if (matchPill) {
                    matchPill.textContent = matched + ' matched';
                }

                cardsEl.innerHTML = mismatches.map(function (item) {
                    const serialHtml = item.serial
                        ? '<span class="odm-mismatch-card__serial">' + escapeHtml(item.serial) + '</span>'
                        : '<span></span>';
                    const deltaHtml = item.delta > 0
                        ? '<div class="odm-mismatch-card__metric odm-mismatch-card__metric--delta">' +
                            '<span class="odm-mismatch-card__metric-label">Difference</span>' +
                            '<span class="odm-mismatch-card__metric-value">' + item.delta.toLocaleString() + '</span>' +
                          '</div>'
                        : '';

                    return '<a href="#' + escapeHtml(item.blockId) + '" class="odm-mismatch-card">' +
                        '<div class="odm-mismatch-card__top">' +
                            serialHtml +
                            '<span class="odm-mismatch-card__status odm-mismatch-card__status--' + escapeHtml(item.result.status) + '">' + escapeHtml(item.result.label) + '</span>' +
                        '</div>' +
                        '<div class="odm-mismatch-card__name">' + escapeHtml(item.name) + '</div>' +
                        '<div class="odm-mismatch-card__metrics">' +
                            '<div class="odm-mismatch-card__metric">' +
                                '<span class="odm-mismatch-card__metric-label">State target</span>' +
                                '<span class="odm-mismatch-card__metric-value">' + item.stateTotal.toLocaleString() + '</span>' +
                            '</div>' +
                            '<div class="odm-mismatch-card__metric">' +
                                '<span class="odm-mismatch-card__metric-label">District total</span>' +
                                '<span class="odm-mismatch-card__metric-value">' + item.grand.toLocaleString() + '</span>' +
                            '</div>' +
                            deltaHtml +
                        '</div>' +
                        '<div class="odm-mismatch-card__foot">Jump to service <i class="fa-solid fa-arrow-down" aria-hidden="true"></i></div>' +
                    '</a>';
                }).join('');
            }

            function updateVerification(block, grand, colTotals) {
                const stateTotal = parseInt(block.getAttribute('data-state-total') || '0', 10) || 0;
                const status = compareTotals(grand, stateTotal);
                const statusEl = block.querySelector('.block-verify-status');
                if (statusEl) {
                    statusEl.textContent = status.label;
                    statusEl.style.color = status.color;
                    statusEl.style.background = status.bg;
                }

                for (let m = 1; m <= 12; m++) {
                    const stateMonth = parseInt(block.getAttribute('data-state-month-' + m) || '0', 10) || 0;
                    const allocated = colTotals[m] || 0;
                    const cell = block.querySelector('.col-total[data-month="' + m + '"]');
                    if (!cell) {
                        continue;
                    }
                    if (stateMonth > 0 && allocated !== stateMonth) {
                        cell.style.background = '#fecaca';
                        cell.style.color = '#991b1b';
                    } else if (stateMonth > 0 && allocated === stateMonth) {
                        cell.style.background = '#bbf7d0';
                        cell.style.color = '#166534';
                    } else {
                        cell.style.background = '';
                        cell.style.color = '';
                    }
                }
            }

            function recalcBlock(block) {
                const colTotals = {};
                for (let m = 1; m <= 12; m++) colTotals[m] = 0;
                let grand = 0;

                block.querySelectorAll('.data-row, tr[data-leaf="1"]').forEach(function (row) {
                    let rowSum = 0;
                    row.querySelectorAll('.month-input').forEach(function (input) {
                        const val = parseInt(input.value, 10) || 0;
                        rowSum += val;
                        const m = parseInt(input.getAttribute('data-month') || '', 10);
                        if (m >= 1 && m <= 12) {
                            colTotals[m] = (colTotals[m] || 0) + val;
                        }
                    });
                    const totalCell = row.querySelector('.row-total');
                    if (totalCell) totalCell.textContent = rowSum.toLocaleString();
                    grand += rowSum;
                });

                for (let m = 1; m <= 12; m++) {
                    const cell = block.querySelector('.col-total[data-month="' + m + '"]');
                    if (cell) cell.textContent = (colTotals[m] || 0).toLocaleString();
                }
                const footer = block.querySelector('.footer-grand');
                if (footer) footer.textContent = grand.toLocaleString();
                const headerTotal = block.querySelector('.block-grand-total');
                if (headerTotal) headerTotal.textContent = grand.toLocaleString();
                updateVerification(block, grand, colTotals);
            }

            function recalcAll() {
                form.querySelectorAll('[data-block="1"]').forEach(recalcBlock);
                const stateOnlyTable = document.getElementById('state-only-table');
                if (stateOnlyTable) recalcBlock(stateOnlyTable);
                updateMismatchSummary();
            }

            form.querySelectorAll('.month-input').forEach(function (input) {
                input.addEventListener('input', recalcAll);
            });

            function distributeMonthTargets(inputs, stateMonth) {
                if (inputs.length === 0) {
                    return;
                }

                stateMonth = Math.max(0, parseInt(stateMonth, 10) || 0);
                if (stateMonth <= 0) {
                    inputs.forEach(function (input) {
                        input.value = input.getAttribute('data-official') || '0';
                    });

                    return;
                }

                const weights = inputs.map(function (input) {
                    return Math.max(0, parseInt(input.getAttribute('data-official') || '0', 10) || 0);
                });
                const weightSum = weights.reduce(function (sum, weight) {
                    return sum + weight;
                }, 0);

                if (weightSum <= 0) {
                    const base = Math.floor(stateMonth / inputs.length);
                    let remainder = stateMonth % inputs.length;
                    inputs.forEach(function (input) {
                        const value = base + (remainder > 0 ? 1 : 0);
                        if (remainder > 0) {
                            remainder--;
                        }
                        input.value = String(value);
                    });

                    return;
                }

                let allocated = 0;
                inputs.forEach(function (input, index) {
                    if (index === inputs.length - 1) {
                        input.value = String(Math.max(0, stateMonth - allocated));

                        return;
                    }

                    const share = Math.round(stateMonth * weights[index] / weightSum);
                    input.value = String(share);
                    allocated += share;
                });
            }

            function autoFillBlock(block) {
                const stateTotal = parseInt(block.getAttribute('data-state-total') || '0', 10) || 0;

                if (stateTotal > 0) {
                    for (let m = 1; m <= 12; m++) {
                        const stateMonth = parseInt(block.getAttribute('data-state-month-' + m) || '0', 10) || 0;
                        const inputs = Array.from(block.querySelectorAll('.month-input[data-month="' + m + '"]'));
                        distributeMonthTargets(inputs, stateMonth);
                    }

                    return;
                }

                block.querySelectorAll('.month-input').forEach(function (input) {
                    input.value = input.getAttribute('data-official') || '0';
                });
            }

            document.getElementById('btn-auto-fill')?.addEventListener('click', function () {
                form.querySelectorAll('[data-block="1"]').forEach(autoFillBlock);
                const stateOnlyTable = document.getElementById('state-only-table');
                if (stateOnlyTable) {
                    stateOnlyTable.querySelectorAll('.month-input').forEach(function (input) {
                        input.value = input.getAttribute('data-official') || '0';
                    });
                }
                recalcAll();
            });

            form.addEventListener('submit', function () {
                const blocks = {};
                const stateOnly = {};
                const unresolvedBlocks = {};
                const unresolvedStateOnly = {};

                function unresolvedKey(scope, misSerial, indicatorName) {
                    return [scope, misSerial || '', indicatorName || ''].join('|');
                }

                form.querySelectorAll('.month-input').forEach(function (input) {
                    const scope = input.getAttribute('data-scope') || '';
                    const deliverableIdAttr = input.getAttribute('data-deliverable-id');
                    const deliverableId = deliverableIdAttr ? parseInt(deliverableIdAttr, 10) : 0;
                    const month = input.getAttribute('data-month');
                    const value = parseInt(input.value, 10) || 0;
                    const misSerial = input.getAttribute('data-mis-serial') || '';
                    const indicatorName = input.getAttribute('data-indicator-name') || '';

                    if (!month) {
                        return;
                    }

                    if (scope === 'state_only') {
                        if (deliverableId > 0) {
                            if (!stateOnly[deliverableId]) {
                                stateOnly[deliverableId] = {};
                            }
                            stateOnly[deliverableId][month] = value;
                        } else {
                            const key = unresolvedKey(scope, misSerial, indicatorName);
                            if (!unresolvedStateOnly[key]) {
                                unresolvedStateOnly[key] = { mis_serial: misSerial, name: indicatorName, months: {} };
                            }
                            unresolvedStateOnly[key].months[month] = value;
                        }

                        return;
                    }

                    let targetBlock = null;
                    if (deliverableId > 0) {
                        if (!blocks[deliverableId]) {
                            blocks[deliverableId] = { districts: {}, hubs: {} };
                        }
                        targetBlock = blocks[deliverableId];
                    } else {
                        const key = unresolvedKey(scope, misSerial, indicatorName);
                        if (!unresolvedBlocks[key]) {
                            unresolvedBlocks[key] = { mis_serial: misSerial, name: indicatorName, districts: {}, hubs: {} };
                        }
                        targetBlock = unresolvedBlocks[key];
                    }

                    if (scope === 'district') {
                        const districtId = input.getAttribute('data-district-id');
                        if (!districtId) {
                            return;
                        }
                        if (!targetBlock.districts[districtId]) {
                            targetBlock.districts[districtId] = {};
                        }
                        targetBlock.districts[districtId][month] = value;
                    } else if (scope === 'hub') {
                        const hubId = input.getAttribute('data-hub-id');
                        if (!hubId) {
                            return;
                        }
                        if (!targetBlock.hubs[hubId]) {
                            targetBlock.hubs[hubId] = {};
                        }
                        targetBlock.hubs[hubId][month] = value;
                    }
                });

                const payloadEl = document.getElementById('district_payload');
                if (payloadEl) {
                    payloadEl.value = JSON.stringify({
                        blocks: blocks,
                        state_only: stateOnly,
                        unresolved_blocks: Object.values(unresolvedBlocks),
                        unresolved_state_only: Object.values(unresolvedStateOnly),
                    });
                }
            });

            recalcAll();
        })();
    </script>
    @endpush
@endsection

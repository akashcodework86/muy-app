@extends('layouts.admin')

@section('title', 'Onboarding Priority')
@section('heading', 'Onboarding Priority List')

@section('page_meta')
    <p class="admin-page-meta">{{ $scopeLabel }} · FY {{ $fiscalYear?->code ?? '—' }} · PMC Priority Matrix (auto score)</p>
@endsection

@section('content')
    @php
        $stageTabs = [
            'seed' => 'Seed ('.number_format($stageCounts['seed'] ?? 0).')',
            'early' => 'Early ('.number_format($stageCounts['early'] ?? 0).')',
            'growth' => 'Growth ('.number_format($stageCounts['growth'] ?? 0).')',
            'all' => 'All stages ('.number_format($stageCounts['all'] ?? 0).')',
        ];
        $queryBase = array_filter([
            'q' => $searchQuery ?: null,
            'district_id' => $districtFilter ?: null,
        ]);
        $showStageRankLabel = $activeStage === 'all';
    @endphp

    <style>
        .op-shell{max-width:1180px}.op-note{margin:0 0 1rem;padding:.75rem 1rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;color:#1e3a5f;font-size:.88rem;line-height:1.5}.op-toolbar{display:flex;flex-wrap:wrap;gap:.55rem;align-items:center;margin:0 0 1rem}.op-tabs{display:flex;flex-wrap:wrap;gap:.45rem;margin:0 0 1rem}.op-tab{border:1px solid #d4d4d8;background:#fff;color:#18181b;padding:.42rem .72rem;border-radius:999px;font-size:.82rem;font-weight:600;text-decoration:none;display:inline-block}.op-tab.is-active{background:#18181b;border-color:#18181b;color:#fff}.op-table-wrap{overflow-x:auto}.op-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:10px;font-size:.84rem}.op-table th,.op-table td{padding:.65rem .7rem;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:top}.op-table th{background:#f8fafc;font-size:.74rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b}.op-score{font-weight:800;color:#0f766e;font-size:1rem}.op-stage{display:inline-block;padding:.15rem .45rem;border-radius:999px;background:#f1f5f9;color:#334155;font-size:.72rem;font-weight:700;text-transform:uppercase}.op-rank{font-weight:700;color:#0f172a;white-space:nowrap}.op-drivers{color:#64748b;font-size:.78rem;line-height:1.45}.op-dim{font-size:.72rem;color:#64748b;white-space:nowrap}.op-btn{display:inline-block;padding:.45rem .8rem;background:#18181b;color:#fff;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;border:0;cursor:pointer}.op-btn--secondary{background:#fff;color:#18181b;border:1px solid #d4d4d8}.op-btn--ghost{background:transparent;color:#475569;border:1px solid #cbd5e1;padding:.28rem .55rem;font-size:.76rem}.op-field{padding:.42rem .6rem;border:1px solid #d4d4d8;border-radius:8px;font:inherit;font-size:.85rem}.op-row-detail td{background:#f8fafc;padding:0 !important;border-bottom:1px solid #e2e8f0}.op-breakdown{padding:.75rem 1rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(11rem,1fr));gap:.55rem .85rem}.op-breakdown__item{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:.55rem .65rem}.op-breakdown__label{display:block;font-size:.72rem;color:#64748b;margin-bottom:.15rem}.op-breakdown__value{font-size:.88rem;font-weight:700;color:#0f172a}
    </style>

    <div class="op-shell">
        <div class="op-note">
            Rankings use the <strong>PMC Priority Matrix (Aug 2025)</strong> on CFA data. Compare applicants <strong>within the same stage</strong> only.
            Seed-stage NA fields receive full weight. Onboarding decisions stay manual — this list is a guide.
        </div>

        <form method="get" action="{{ route($routePrefix.'.index') }}" class="op-toolbar">
            <input type="hidden" name="stage" value="{{ $activeStage }}">
            <input class="op-field" type="search" name="q" value="{{ $searchQuery }}" placeholder="Search app no, name, phone" style="min-width:14rem;flex:1;max-width:24rem;">
            @if ($districts->count() > 1)
                <select class="op-field" name="district_id">
                    <option value="">All districts</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district->id }}" @selected($districtFilter === (int) $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="op-btn op-btn--secondary">Filter</button>
            <a href="{{ route($routePrefix.'.export', array_merge($queryBase, ['stage' => $activeStage])) }}" class="op-btn" style="margin-left:auto;">Download CSV</a>
        </form>

        <div class="op-tabs">
            @foreach ($stageTabs as $key => $label)
                <a href="{{ route($routePrefix.'.index', array_merge($queryBase, ['stage' => $key])) }}" class="op-tab @if ($activeStage === $key) is-active @endif">{{ $label }}</a>
            @endforeach
        </div>

        <div class="op-table-wrap">
            <table class="op-table">
                <thead>
                    <tr>
                        <th>{{ $showStageRankLabel ? 'Stage rank' : 'Rank' }}</th>
                        <th>Application</th>
                        <th>Applicant</th>
                        <th>District</th>
                        <th>Stage</th>
                        <th>Score</th>
                        <th>Dimensions</th>
                        <th>Top drivers</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $showUrl = $showUrlResolver($row['id']);
                            $detailId = 'op-detail-'.$row['id'];
                        @endphp
                        <tr>
                            <td>
                                <span class="op-rank">{{ $showStageRankLabel ? ($row['rank_label'] ?? '—') : ($row['rank'] ?? '—') }}</span>
                            </td>
                            <td>
                                <strong>{{ $row['application_no'] }}</strong>
                                @if ($row['submitted_at'])
                                    <div class="op-dim">{{ $row['submitted_at'] }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $row['applicant_name'] }}
                                <div class="op-dim">{{ $row['block_name'] }}</div>
                            </td>
                            <td>{{ $row['district_name'] }}</td>
                            <td><span class="op-stage">{{ $row['stage'] }}</span></td>
                            <td><span class="op-score">{{ number_format($row['score'], 1) }}</span></td>
                            <td class="op-dim">
                                S {{ number_format($row['dimensions']['scalability']['points'], 1) }}/30 ·
                                E {{ number_format($row['dimensions']['economic']['points'], 1) }}/30 ·
                                So {{ number_format($row['dimensions']['social']['points'], 1) }}/20
                            </td>
                            <td class="op-drivers">{{ implode(' · ', $row['highlights']) }}</td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="op-btn op-btn--ghost js-op-toggle" data-target="{{ $detailId }}" aria-expanded="false">Breakdown</button>
                                @if ($showUrl)
                                    <a href="{{ $showUrl }}" class="op-btn op-btn--secondary" style="padding:.3rem .55rem;font-size:.76rem;margin-left:.25rem;">View CFA</a>
                                @endif
                            </td>
                        </tr>
                        <tr class="op-row-detail" id="{{ $detailId }}" hidden>
                            <td colspan="9">
                                <div class="op-breakdown">
                                    @foreach ($row['dimensions'] as $dimension)
                                        <div class="op-breakdown__item">
                                            <span class="op-breakdown__label">{{ $dimension['label'] }}</span>
                                            <span class="op-breakdown__value">{{ number_format($dimension['points'], 1) }} / {{ number_format($dimension['max'], 0) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="padding:1.2rem;color:#64748b;">No non-onboarded applications in this scope.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div style="margin-top:1rem;">{{ $rows->links() }}</div>
        @endif
    </div>

    <script>
        document.querySelectorAll('.js-op-toggle').forEach(function (button) {
            button.addEventListener('click', function () {
                var target = document.getElementById(button.getAttribute('data-target'));
                if (!target) return;
                var open = target.hidden;
                target.hidden = !open;
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                button.textContent = open ? 'Hide' : 'Breakdown';
            });
        });
    </script>
@endsection

@extends('layouts.admin')

@section('title', 'SPOC approval audit')
@section('heading', 'SPOC approval audit')

@section('content')
    <style>
        .saa-grid { display: grid; gap: 0.85rem; }
        .saa-cards { display: grid; gap: 0.65rem; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); }
        .saa-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 0.75rem 0.85rem; }
        .saa-k { font-size: 0.7rem; text-transform: uppercase; letter-spacing: .06em; color: #64748b; font-weight: 700; }
        .saa-v { margin-top: 0.15rem; font-size: 1.25rem; font-weight: 800; color: #0f172a; }
        .saa-tools { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 0.65rem 0.75rem; display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: flex-end; }
        .saa-lbl { display: block; font-size: 0.74rem; color: #64748b; margin-bottom: 0.2rem; font-weight: 700; }
        .saa-sel { border: 1px solid #d1d5db; border-radius: 9px; padding: 0.42rem 0.55rem; font-size: 0.84rem; min-width: 12rem; }
        .saa-btn { border: 1px solid #d1d5db; background: #fff; border-radius: 9px; padding: 0.42rem 0.72rem; font-size: 0.82rem; font-weight: 700; cursor: pointer; }
        .saa-note { margin: 0; font-size: 0.84rem; color: #64748b; }
        .saa-panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .saa-head { padding: 0.62rem 0.75rem; border-bottom: 1px solid #f1f5f9; font-size: 0.82rem; font-weight: 800; color: #334155; }
        .saa-table-wrap { overflow: auto; }
        .saa-table { width: 100%; min-width: 980px; border-collapse: collapse; font-size: 0.82rem; }
        .saa-table th { text-align: left; padding: 0.55rem 0.62rem; border-bottom: 1px solid #e5e7eb; background: #f8fafc; color: #64748b; font-size: 0.73rem; text-transform: uppercase; letter-spacing: .05em; }
        .saa-table td { padding: 0.55rem 0.62rem; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
        .saa-table tr:hover td { background: #fcfcff; }
        .saa-flag { display: inline-flex; border-radius: 999px; padding: 0.12rem 0.48rem; font-size: 0.72rem; font-weight: 800; }
        .saa-flag--bad { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .saa-flag--ok { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .saa-flag--na { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
        .saa-muted { font-size: 0.74rem; color: #64748b; margin-top: 0.08rem; }
    </style>

    <div class="saa-grid">
        <p class="saa-note">
            Tracks whether SPOCs opened the attachment before approving. Use this report for accountability — it does not block approvals.
        </p>

        <div class="saa-cards">
            <div class="saa-card">
                <div class="saa-k">Approved (last {{ (int) $filterDays }}d)</div>
                <div class="saa-v">{{ number_format((int) ($totals['total_approved'] ?? 0)) }}</div>
            </div>
            <div class="saa-card">
                <div class="saa-k">Without document view</div>
                <div class="saa-v">{{ number_format((int) ($totals['without_document_view'] ?? 0)) }}</div>
            </div>
        </div>

        <form method="get" class="saa-tools">
            <div>
                <label class="saa-lbl" for="saaDays">Period</label>
                <select id="saaDays" name="days" class="saa-sel">
                    @foreach ([7, 14, 30, 60, 90] as $days)
                        <option value="{{ $days }}" @selected((int) $filterDays === $days)>Last {{ $days }} days</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="saa-lbl" for="saaSpoc">SPOC</label>
                <select id="saaSpoc" name="spoc_id" class="saa-sel">
                    <option value="">All SPOCs</option>
                    @foreach ($spocOptions as $spoc)
                        <option value="{{ (int) $spoc->id }}" @selected((int) $filterSpocId === (int) $spoc->id)>
                            {{ $spoc->name }}@if($spoc->email) — {{ $spoc->email }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="saa-lbl" for="saaFlag">Show</label>
                <select id="saaFlag" name="flag" class="saa-sel">
                    <option value="" @selected($filterFlag === '')>All approvals</option>
                    <option value="without_doc" @selected($filterFlag === 'without_doc')>Approved without opening document</option>
                    <option value="fast" @selected($filterFlag === 'fast')>Review under 15 seconds</option>
                </select>
            </div>
            <button type="submit" class="saa-btn">Apply</button>
        </form>

        <div class="saa-panel">
            <div class="saa-head">SPOC summary</div>
            <div class="saa-table-wrap">
                <table class="saa-table">
                    <thead>
                        <tr>
                            <th>SPOC</th>
                            <th>Approved</th>
                            <th>Without doc view</th>
                            <th>Without doc %</th>
                            <th>Avg review time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($spocSummaries as $summary)
                            <tr>
                                <td>
                                    <strong>{{ $summary['spoc_name'] }}</strong>
                                    @if (! empty($summary['spoc_email']))
                                        <div class="saa-muted">{{ $summary['spoc_email'] }}</div>
                                    @endif
                                </td>
                                <td>{{ number_format((int) $summary['total_approved']) }}</td>
                                <td>{{ number_format((int) $summary['without_document_view']) }}</td>
                                <td>{{ number_format((float) $summary['without_document_rate'], 1) }}%</td>
                                <td>
                                    @if ((int) $summary['avg_review_seconds'] > 0)
                                        {{ (int) $summary['avg_review_seconds'] }}s
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No approvals in this period yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="saa-panel">
            <div class="saa-head">Recent approvals (latest 500 in period)</div>
            <div class="saa-table-wrap">
                <table class="saa-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>SPOC</th>
                            <th>Incubatee</th>
                            <th>Service</th>
                            <th>Document</th>
                            <th>Review time</th>
                            <th>Channel</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td style="white-space:nowrap;">{{ $row['approved_at']?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                                <td>
                                    <strong>{{ $row['spoc_name'] }}</strong>
                                    @if ($row['spoc_email'] !== '')
                                        <div class="saa-muted">{{ $row['spoc_email'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $row['incubatee'] }}</strong>
                                    @if ($row['application_no'] !== '')
                                        <div class="saa-muted">{{ $row['application_no'] }}</div>
                                    @endif
                                    @if ($row['district'] !== '—')
                                        <div class="saa-muted">{{ $row['district'] }}</div>
                                    @endif
                                </td>
                                <td>{{ $row['service'] }}</td>
                                <td>
                                    @if (! $row['had_attachment'])
                                        <span class="saa-flag saa-flag--na">No attachment</span>
                                    @elseif ($row['approved_without_document_view'])
                                        <span class="saa-flag saa-flag--bad">Not opened</span>
                                    @else
                                        <span class="saa-flag saa-flag--ok">Opened</span>
                                        @if ($row['document_view_source'] !== '')
                                            <div class="saa-muted">{{ str_replace('_', ' ', $row['document_view_source']) }}</div>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    @if ((int) $row['case_page_seconds'] > 0)
                                        {{ (int) $row['case_page_seconds'] }}s
                                    @else
                                        —
                                    @endif
                                    @if ($row['full_page_visited'])
                                        <div class="saa-muted">Full page</div>
                                    @elseif ($row['quick_review_opened'])
                                        <div class="saa-muted">Quick review only</div>
                                    @endif
                                </td>
                                <td>{{ str_replace('_', ' ', $row['approval_channel']) ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No matching approvals.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

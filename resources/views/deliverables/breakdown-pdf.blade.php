<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $breakdown['name'] ?? 'Achievement breakdown' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #9a3412; }
        .meta { color: #475569; margin-bottom: 16px; font-size: 10px; }
        .stats { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .stats td { border: 1px solid #e2e8f0; padding: 8px 10px; }
        .stats .label { background: #f8fafc; font-weight: 700; width: 28%; }
        .section { margin-top: 14px; }
        .section h2 { font-size: 13px; margin: 0 0 6px; color: #0f172a; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data th, table.data td { border: 1px solid #e2e8f0; padding: 5px 6px; text-align: left; vertical-align: top; }
        table.data th { background: #ffedd5; font-size: 10px; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <h1>{{ $serial }} — {{ $breakdown['name'] ?? 'Indicator' }}</h1>
    <div class="meta">
        {{ $scopeLabel }} · {{ $periodLabel }} · {{ $breakdown['source_type_label'] ?? '' }}
    </div>

    <table class="stats">
        <tr><td class="label">Target</td><td>{{ $target !== null ? number_format((int) $target) : '—' }}</td></tr>
        <tr><td class="label">Achievement</td><td>{{ number_format((int) ($breakdown['total'] ?? 0)) }}</td></tr>
        <tr><td class="label">Progress</td><td>{{ $achievementPct !== null ? $achievementPct.'%' : '—' }}</td></tr>
    </table>

    <div class="section">
        <h2>District split</h2>
        <table class="data">
            <thead><tr><th>District</th><th>Hub</th><th>Count</th><th>Share</th></tr></thead>
            <tbody>
                @forelse ($breakdown['by_district'] ?? [] as $item)
                    <tr>
                        <td>{{ $item['district'] ?? '' }}</td>
                        <td>{{ $item['hub'] ?? '' }}</td>
                        <td>{{ number_format((int) ($item['count'] ?? 0)) }}</td>
                        <td>{{ (int) ($item['share_pct'] ?? 0) }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No district data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Monthly trend</h2>
        <table class="data">
            <thead><tr><th>Month</th><th>Count</th><th>Share</th></tr></thead>
            <tbody>
                @forelse ($breakdown['by_month'] ?? [] as $item)
                    <tr>
                        <td>{{ $item['month'] ?? '' }}</td>
                        <td>{{ number_format((int) ($item['count'] ?? 0)) }}</td>
                        <td>{{ (int) ($item['share_pct'] ?? 0) }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">No monthly data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (! empty($breakdown['by_service']))
        <div class="section">
            <h2>By service</h2>
            <table class="data">
                <thead><tr><th>Service</th><th>Count</th><th>Share</th></tr></thead>
                <tbody>
                    @foreach ($breakdown['by_service'] as $item)
                        <tr>
                            <td>{{ $item['service'] ?? '' }}</td>
                            <td>{{ number_format((int) ($item['count'] ?? 0)) }}</td>
                            <td>{{ (int) ($item['share_pct'] ?? 0) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="section">
        <h2>Records</h2>
        @php $isMarketIncubatees = ($breakdown['source_type'] ?? '') === 'market_linkage_incubatees'; @endphp
        <table class="data">
            @if ($isMarketIncubatees)
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Application</th>
                        <th>Incubatee</th>
                        <th>Phone</th>
                        <th>Block</th>
                        <th>District</th>
                        <th>Hub</th>
                        <th>Submitted by</th>
                        <th>Partner</th>
                        <th>Mode</th>
                        <th>Linkage date</th>
                        <th>Link / URL</th>
                        <th>Bill</th>
                    </tr>
                </thead>
                <tbody>
                    @php $pdfSerial = 0; @endphp
                    @forelse ($breakdown['records'] ?? [] as $item)
                        @php
                            $pdfSerial++;
                            $partners = is_array($item['partner_rows'] ?? null) && $item['partner_rows'] !== []
                                ? $item['partner_rows']
                                : [[
                                    'partner_name' => $item['service'] ?? '',
                                    'linkage_mode_label' => $item['linkage_mode'] ?? '',
                                    'linkage_date_display' => '',
                                    'link_url' => '',
                                    'has_document' => false,
                                    'document_name' => '',
                                ]];
                        @endphp
                        @foreach ($partners as $partner)
                            <tr>
                                <td>{{ $pdfSerial }}</td>
                                <td>{{ $item['reference'] ?? '' }}</td>
                                <td>{{ $item['applicant'] ?? '' }}</td>
                                <td>{{ $item['phone'] ?? '' }}</td>
                                <td>{{ $item['block'] ?? '' }}</td>
                                <td>{{ $item['district'] ?? '' }}</td>
                                <td>{{ $item['hub'] ?? '' }}</td>
                                <td>{{ $item['submitted_by'] ?? '' }}</td>
                                <td>{{ $partner['partner_name'] ?? '' }}</td>
                                <td>{{ $partner['linkage_mode_label'] ?? '' }}</td>
                                <td>{{ $partner['linkage_date_display'] ?? '' }}</td>
                                <td>{{ $partner['link_url'] ?? '' }}</td>
                                <td>{{ ! empty($partner['has_document']) ? 'Yes' : 'No' }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="13" class="muted">No records found.</td></tr>
                    @endforelse
                </tbody>
            @else
                <thead><tr><th>Reference</th><th>Applicant</th><th>District</th><th>Service</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse ($breakdown['records'] ?? [] as $item)
                        <tr>
                            <td>{{ $item['reference'] ?? '' }}</td>
                            <td>{{ $item['applicant'] ?? '' }}</td>
                            <td>{{ $item['district'] ?? '' }}</td>
                            <td>{{ $item['service'] ?? '' }}</td>
                            <td>{{ $item['date'] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No records found.</td></tr>
                    @endforelse
                </tbody>
            @endif
        </table>
    </div>
</body>
</html>

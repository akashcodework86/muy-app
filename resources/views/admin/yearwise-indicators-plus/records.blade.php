@extends('layouts.admin')

@section('title', ($payload['metric_label'] ?? 'Records').' — Year-wise indicators')
@section('heading', ($payload['metric_label'] ?? 'Records').' — incubatee list')

@section('content')
@php
    $opts = $payload['filter_options'] ?? [];
    $records = $payload['records'];
    $qp = $queryParams ?? [];
    $regLabel = $payload['registration_label'] ?? 'Service / Reg. No.';
    $metric = (string) ($filters['metric'] ?? 'onboarding');
    $isServiceMetric = in_array($metric, ['udyam', 'artisan_card', 'fssai', 'gst', 'market_linkage', 'convergence'], true);
    $showLinks = $metric === 'market_linkage';
    $colspan = $showLinks ? 13 : 12;
@endphp
<style>
.yi-rec-nav{display:flex;flex-wrap:wrap;gap:.55rem;align-items:center;margin-bottom:.75rem;font-size:.86rem}
.yi-rec-nav a{color:#9a3412;font-weight:700;text-decoration:none}
.yi-rec-nav a:hover{text-decoration:underline}
.yi-rec-meta{color:#64748b}
.yi-rec-hero{background:linear-gradient(135deg,#fff7ed 0%,#fff 55%,#f8fafc 100%);border:1px solid #fed7aa;border-radius:14px;padding:.9rem 1rem;margin-bottom:.85rem}
.yi-rec-hero h2{margin:0;font-size:1.15rem;color:#9a3412;font-weight:800;letter-spacing:-.01em}
.yi-rec-hero p{margin:.28rem 0 0;color:#78716c;font-size:.86rem;max-width:52rem;line-height:1.45}
.yi-rec-stats{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.65rem}
.yi-rec-stat{background:#fff;border:1px solid #e7e5e4;border-radius:8px;padding:.35rem .65rem;font-size:.78rem;color:#57534e}
.yi-rec-stat strong{color:#1c1917;font-weight:800}
.yi-rec-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:.85rem .95rem;margin-bottom:.85rem}
.yi-rec-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.55rem;align-items:end}
.yi-rec-filters label{display:block;font-size:.75rem;font-weight:700;color:#475569;margin-bottom:.2rem}
.yi-rec-filters input,.yi-rec-filters select{width:100%;padding:.45rem .55rem;border:1px solid #d4d4d8;border-radius:8px;font-size:.88rem;background:#fff}
.yi-rec-actions{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.65rem}
.yi-rec-btn{display:inline-flex;align-items:center;padding:.48rem .85rem;border-radius:8px;font-weight:700;font-size:.84rem;text-decoration:none;border:none;cursor:pointer;font-family:inherit}
.yi-rec-btn--apply{background:#9a3412;color:#fff}
.yi-rec-btn--ghost{background:#fff;color:#334155;border:1px solid #d4d4d8}
.yi-rec-btn--xlsx{background:#065f46;color:#fff}
.yi-rec-btn--csv{background:#f1f5f9;color:#334155;border:1px solid #d4d4d8}
.yi-rec-table-wrap{background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:auto;max-height:calc(100vh - 220px)}
.yi-rec-table{width:100%;border-collapse:collapse;font-size:.84rem;min-width:1280px}
.yi-rec-table th,.yi-rec-table td{padding:.55rem .65rem;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
.yi-rec-table th{font-size:.7rem;text-transform:uppercase;letter-spacing:.03em;color:#64748b;background:#f8fafc;position:sticky;top:0;z-index:1}
.yi-rec-table tbody tr:hover{background:#fffbeb}
.yi-rec-chip{display:inline-block;padding:.12rem .4rem;border-radius:6px;font-size:.7rem;font-weight:700}
.yi-rec-chip--verified{background:#ecfdf5;color:#047857}
.yi-rec-chip--jit{background:#eff6ff;color:#1d4ed8}
.yi-rec-chip--lakhpati_didi{background:#fdf4ff;color:#86198f}
.yi-rec-name{font-weight:700;color:#0f172a}
.yi-rec-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:.8rem;letter-spacing:.01em;color:#1e293b;word-break:break-all}
.yi-rec-svc{min-width:11rem}
.yi-rec-svc-title{font-weight:650;color:#1f2937}
.yi-rec-sub{font-size:.76rem;color:#64748b;margin-top:.15rem;line-height:1.35}
.yi-rec-docs{display:flex;flex-direction:column;gap:.25rem}
.yi-rec-docs a{color:#9a3412;font-weight:600;text-decoration:none;font-size:.78rem}
.yi-rec-docs a:hover{text-decoration:underline}
.yi-rec-links{display:flex;flex-direction:column;gap:.28rem;min-width:10rem}
.yi-rec-links a{color:#9a3412;font-weight:650;text-decoration:none;font-size:.78rem;word-break:break-all}
.yi-rec-links a:hover{text-decoration:underline}
.yi-rec-muted{color:#94a3b8}
.yi-rec-foot{padding:.7rem .85rem;display:flex;justify-content:space-between;gap:.75rem;flex-wrap:wrap;align-items:center;color:#64748b;font-size:.82rem;background:#f8fafc;border-top:1px solid #e2e8f0}
.yi-rec-error{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:.75rem;padding:.7rem 1rem;margin-bottom:.85rem;font-size:.88rem;font-weight:600}
.yi-rec-loc{white-space:nowrap}
@media (max-width:900px){
  .yi-rec-table-wrap{max-height:none}
}
</style>

<div class="yi-rec-nav">
    <a href="{{ route('admin.yearwise-indicators-plus.index') }}">← Year-wise indicators</a>
    <span class="yi-rec-meta">
        {{ $payload['scope_label'] ?? '' }}
        @if (!empty($payload['year'])) · FY {{ $payload['year'] }} @endif
        @if (!empty($payload['phase_label'])) · {{ $payload['phase_label'] }} @endif
        @if (!empty($payload['district'])) · {{ $payload['district'] }} @endif
    </span>
</div>

@if (!empty($payload['error']))
    <div class="yi-rec-error">Could not load list: {{ $payload['error'] }}</div>
@endif

<div class="yi-rec-hero">
    <h2>{{ $payload['metric_label'] ?? 'Records' }}</h2>
    <p>
        @if ($isServiceMetric)
            Each row is one incubatee service achievement for this indicator, including Verified workbook rows
            plus JIT / Lakhpati extras where they contribute to the matrix total.
            {{ $regLabel }} is shown when captured in the source system.
        @elseif ($metric === 'onboarding')
            Onboarding list includes Verified incubatees plus JIT and Lakhpati Didi rows that feed the Plus matrix
            (notably FY 2023-24).
        @else
            CFA achievement rows for the selected scope. Registration number appears when present on the application.
        @endif
    </p>
    <div class="yi-rec-stats">
        <div class="yi-rec-stat"><strong>{{ number_format((int) ($payload['total'] ?? 0)) }}</strong> matching records</div>
        <div class="yi-rec-stat">Page size <strong>{{ (int) ($records->perPage() ?? 50) }}</strong></div>
        @if (!empty($filters['source']) && $filters['source'] !== 'all')
            <div class="yi-rec-stat">Source filter: <strong>{{ $opts['sources'][$filters['source']] ?? $filters['source'] }}</strong></div>
        @endif
    </div>
</div>

<form method="get" action="{{ route('admin.yearwise-indicators-plus.records') }}" class="yi-rec-card">
    <div class="yi-rec-filters">
        <div>
            <label for="metric">Indicator</label>
            <select name="metric" id="metric">
                @foreach (($opts['metrics'] ?? []) as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['metric'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="scope">Scope</label>
            <select name="scope" id="scope">
                @foreach (($opts['scopes'] ?? []) as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['scope'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="year">Financial year</label>
            <select name="year" id="year">
                <option value="">—</option>
                @foreach (($opts['years'] ?? []) as $fy)
                    <option value="{{ $fy }}" @selected(($filters['year'] ?? '') === $fy)>{{ $fy }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="phase">Phase</label>
            <select name="phase" id="phase">
                <option value="">—</option>
                @foreach (($opts['phases'] ?? []) as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['phase'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="district">District</label>
            <select name="district" id="district">
                <option value="">All districts</option>
                @foreach (($opts['districts'] ?? []) as $dName)
                    <option value="{{ $dName }}" @selected(($filters['district'] ?? '') === $dName)>{{ $dName }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="source">Source</label>
            <select name="source" id="source">
                @foreach (($opts['sources'] ?? []) as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['source'] ?? 'all') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="q">Search</label>
            <input type="text" name="q" id="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name / phone / app no. / reg no.">
        </div>
    </div>
    <div class="yi-rec-actions">
        <button type="submit" class="yi-rec-btn yi-rec-btn--apply">Apply filters</button>
        <a href="{{ route('admin.yearwise-indicators-plus.records', ['metric' => $filters['metric'] ?? 'onboarding', 'scope' => 'grand']) }}" class="yi-rec-btn yi-rec-btn--ghost">Reset</a>
        <a href="{{ route('admin.yearwise-indicators-plus.records.export.csv', $qp) }}" class="yi-rec-btn yi-rec-btn--csv">Export CSV</a>
        <a href="{{ route('admin.yearwise-indicators-plus.records.export.xlsx', $qp) }}" class="yi-rec-btn yi-rec-btn--xlsx">Export Excel</a>
    </div>
</form>

<div class="yi-rec-table-wrap">
    <table class="yi-rec-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Incubatee</th>
                <th>Application No.</th>
                <th>Phone</th>
                <th>Location</th>
                <th>Sector / Product</th>
                <th>{{ $regLabel }}</th>
                @if ($showLinks)
                    <th>Links</th>
                @endif
                <th>Service</th>
                <th>Service date</th>
                <th>FY</th>
                <th>Source</th>
                <th>Documents</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $i => $row)
                @php
                    $src = (string) ($row['plus_source'] ?? 'verified');
                    $chipClass = match ($src) {
                        'jit' => 'yi-rec-chip--jit',
                        'lakhpati_didi' => 'yi-rec-chip--lakhpati_didi',
                        default => 'yi-rec-chip--verified',
                    };
                    $docs = is_array($row['documents'] ?? null) ? $row['documents'] : [];
                    $dash = static fn ($v) => ($v !== null && trim((string) $v) !== '') ? $v : null;
                    $name = $dash($row['applicant_name'] ?? '');
                    $appNo = $dash($row['application_no'] ?? '');
                    $phone = $dash($row['phone'] ?? '');
                    $district = $dash($row['district'] ?? '');
                    $block = $dash($row['block'] ?? '');
                    $sector = $dash($row['sector'] ?? '');
                    $product = $dash($row['product'] ?? '');
                    $serviceNumber = $dash($row['service_number'] ?? '');
                    $marketLinks = is_array($row['market_links'] ?? null) ? $row['market_links'] : [];
                    $serviceTitle = $dash($row['service_label'] ?? '') ?: $dash($row['category'] ?? '');
                    $detail = $dash($row['detail'] ?? '');
                    $status = $dash($row['status'] ?? '');
                    $dateUsed = $dash($row['date_used'] ?? '');
                @endphp
                <tr>
                    <td>{{ $records->firstItem() + $i }}</td>
                    <td>
                        @if ($name)
                            <div class="yi-rec-name">{{ $name }}</div>
                        @else
                            <span class="yi-rec-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($appNo)
                            <span class="yi-rec-mono">{{ $appNo }}</span>
                        @else
                            <span class="yi-rec-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $phone ?? '—' }}</td>
                    <td class="yi-rec-loc">
                        @if ($district || $block)
                            <div>{{ $district ?? '—' }}</div>
                            @if ($block)
                                <div class="yi-rec-sub">{{ $block }}</div>
                            @endif
                        @else
                            <span class="yi-rec-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($sector || $product)
                            @if ($sector)<div>{{ $sector }}</div>@endif
                            @if ($product)<div class="yi-rec-sub">{{ $product }}</div>@endif
                        @else
                            <span class="yi-rec-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($serviceNumber)
                            <span class="yi-rec-mono">{{ $serviceNumber }}</span>
                        @else
                            <span class="yi-rec-muted">—</span>
                        @endif
                    </td>
                    @if ($showLinks)
                        <td>
                            @if ($marketLinks === [])
                                <span class="yi-rec-muted">—</span>
                            @else
                                <div class="yi-rec-links">
                                    @foreach ($marketLinks as $link)
                                        @php
                                            $linkLabel = trim((string) ($link['label'] ?? ''));
                                            $linkUrl = trim((string) ($link['url'] ?? ''));
                                        @endphp
                                        @if ($linkUrl !== '')
                                            <a href="{{ $linkUrl }}" target="_blank" rel="noopener noreferrer">{{ $linkLabel !== '' ? $linkLabel : $linkUrl }}</a>
                                        @elseif ($linkLabel !== '')
                                            <span class="yi-rec-sub">{{ $linkLabel }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    @endif
                    <td class="yi-rec-svc">
                        @if ($serviceTitle || $detail || $status)
                            @if ($serviceTitle)
                                <div class="yi-rec-svc-title">{{ $serviceTitle }}</div>
                            @endif
                            @if ($detail && $detail !== $serviceTitle)
                                <div class="yi-rec-sub">{{ $detail }}</div>
                            @endif
                            @if ($status)
                                <div class="yi-rec-sub">{{ $status }}</div>
                            @endif
                        @else
                            <span class="yi-rec-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $dateUsed ?? '—' }}</td>
                    <td>{{ $row['year'] }}</td>
                    <td><span class="yi-rec-chip {{ $chipClass }}">{{ $row['source_label'] ?? $src }}</span></td>
                    <td>
                        @if ($docs === [])
                            <span class="yi-rec-muted">—</span>
                        @else
                            <div class="yi-rec-docs">
                                @foreach ($docs as $doc)
                                    @if (!empty($doc['url']))
                                        <a href="{{ $doc['url'] }}" target="_blank" rel="noopener">{{ $doc['label'] ?? 'Document' }}</a>
                                    @else
                                        <span class="yi-rec-muted">{{ $doc['label'] ?? 'Document' }}@if (!empty($doc['note'])) ({{ $doc['note'] }})@endif</span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" style="text-align:center;color:#64748b;padding:1.5rem;">No records for these filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="yi-rec-foot">
        <div>
            @if ($records->total() > 0)
                Showing {{ $records->firstItem() }}–{{ $records->lastItem() }} of {{ number_format($records->total()) }}
            @else
                0 records
            @endif
        </div>
        <div>{{ $records->withQueryString()->links() }}</div>
    </div>
</div>
@endsection

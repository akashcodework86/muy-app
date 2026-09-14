@extends('layouts.admin')

@section('title', 'Bills')
@section('heading', 'Bills')

@push('styles')
<style>
    .ibd-shell { display:flex; flex-direction:column; gap:1rem; }
    .ibd-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .ibd-alert--warn { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .ibd-toolbar { display:flex; flex-wrap:wrap; gap:0.65rem; align-items:flex-end; justify-content:space-between; }
    .ibd-filters { display:flex; flex-wrap:wrap; gap:0.55rem; align-items:flex-end; }
    .ibd-field { display:flex; flex-direction:column; gap:0.25rem; }
    .ibd-field label { font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; }
    .ibd-field input, .ibd-field select { border:1px solid #cbd5e1; border-radius:8px; padding:0.45rem 0.6rem; font-size:0.86rem; min-width:9.5rem; background:#fff; }
    .ibd-field input[type="search"] { min-width:14rem; }
    .ibd-btn { display:inline-flex; align-items:center; gap:0.35rem; padding:0.48rem 0.9rem; border-radius:8px; font-size:0.85rem; font-weight:700; text-decoration:none; border:none; cursor:pointer; font-family:inherit; }
    .ibd-btn--primary { background:#1d4ed8; color:#fff; }
    .ibd-btn--ghost { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .ibd-btn--export { background:#065f46; color:#fff; }
    .ibd-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:0.65rem; }
    .ibd-stat { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.85rem 1rem; }
    .ibd-stat__label { font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; }
    .ibd-stat__value { display:block; margin-top:0.25rem; font-size:1.2rem; font-weight:800; color:#0f172a; }
    .ibd-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; }
    .ibd-table { width:100%; border-collapse:collapse; font-size:0.84rem; }
    .ibd-table th, .ibd-table td { text-align:left; padding:0.6rem 0.7rem; border-bottom:1px solid #e2e8f0; vertical-align:top; }
    .ibd-table th { font-size:0.7rem; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; background:#f8fafc; }
    .ibd-words { margin:0.2rem 0 0; font-size:0.74rem; font-weight:600; color:#0f766e; }
    .ibd-doc { color:#1d4ed8; font-weight:600; text-decoration:none; }
    .ibd-doc:hover { text-decoration:underline; }
    .ibd-empty { padding:1.25rem; text-align:center; color:#64748b; }
    .ibd-pager { padding:0.75rem 1rem; }
    @media (max-width:860px) {
        .ibd-table th { display:none; }
        .ibd-table td { display:block; border-bottom:none; padding:0.2rem 0.7rem; }
        .ibd-table tr { display:block; padding:0.7rem 0; border-bottom:1px solid #e2e8f0; }
        .ibd-table td::before { content:attr(data-label); display:block; font-size:0.66rem; font-weight:700; text-transform:uppercase; color:#64748b; }
    }
</style>
@endpush

@section('content')
@php
    $isPaginated = is_object($rows) && method_exists($rows, 'total');
    $districtJson = $districts->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name, 'hub_id' => (int) $d->hub_id])->values();
@endphp
<div class="ibd-shell">
    @if (! empty($migrationMissing))
        <div class="ibd-alert ibd-alert--warn">Bills table is missing. Run migrations first.</div>
    @endif

    <div class="ibd-stats">
        <div class="ibd-stat"><span class="ibd-stat__label">Bills</span><span class="ibd-stat__value">{{ number_format($totals['bills'] ?? 0) }}</span></div>
        <div class="ibd-stat"><span class="ibd-stat__label">Total amount</span><span class="ibd-stat__value">{{ \App\Support\IndianRupees::format($totals['amount'] ?? 0) }}</span></div>
        <div class="ibd-stat"><span class="ibd-stat__label">Incubatees</span><span class="ibd-stat__value">{{ number_format($totals['incubatees'] ?? 0) }}</span></div>
        <div class="ibd-stat"><span class="ibd-stat__label">Districts</span><span class="ibd-stat__value">{{ number_format($totals['districts'] ?? 0) }}</span></div>
    </div>

    <form method="get" action="{{ route($dashboardRoute) }}" class="ibd-toolbar">
        <div class="ibd-filters">
            <div class="ibd-field">
                <label for="ibd-q">Search</label>
                <input id="ibd-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, bill no, application">
            </div>
            @if ($showHubFilter ?? true)
            <div class="ibd-field">
                <label for="ibd-hub">Hub</label>
                <select id="ibd-hub" name="hub">
                    <option value="">All hubs</option>
                    @foreach ($hubs as $hub)
                        <option value="{{ $hub->id }}" @selected((int) ($filters['hub_id'] ?? 0) === (int) $hub->id)>{{ $hub->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="ibd-field">
                <label for="ibd-district">District</label>
                <select id="ibd-district" name="district">
                    <option value="">All districts</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district->id }}" data-hub="{{ $district->hub_id }}" @selected((int) ($filters['district_id'] ?? 0) === (int) $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ibd-field">
                <label for="ibd-from">From</label>
                <input id="ibd-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="ibd-field">
                <label for="ibd-to">To</label>
                <input id="ibd-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>
            <button type="submit" class="ibd-btn ibd-btn--primary">Apply</button>
            <a class="ibd-btn ibd-btn--ghost" href="{{ route($dashboardRoute) }}">Clear</a>
        </div>
        <a class="ibd-btn ibd-btn--export" href="{{ route($exportRoute, request()->query()) }}">Export Excel</a>
    </form>

    <div class="ibd-card">
        @if ($isPaginated ? $rows->total() === 0 : $rows->isEmpty())
            <p class="ibd-empty">No bills match these filters.</p>
        @else
            <table class="ibd-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Bill number</th>
                        <th>Amount</th>
                        <th>Applicant</th>
                        <th>District</th>
                        <th>Batch</th>
                        <th>Added by</th>
                        <th>Document</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $bill)
                        @php
                            $offset = $isPaginated ? max(0, (int) $rows->firstItem() - 1) : 0;
                            $words = \App\Support\IndianRupees::inWords($bill->amount);
                        @endphp
                        <tr>
                            <td data-label="#">{{ $offset + $i + 1 }}</td>
                            <td data-label="Date">{{ $bill->bill_date?->format('d M Y') }}</td>
                            <td data-label="Bill number">{{ $bill->bill_number }}</td>
                            <td data-label="Amount">
                                {{ \App\Support\IndianRupees::format($bill->amount) }}
                                @if ($words !== '')
                                    <p class="ibd-words">{{ $words }}</p>
                                @endif
                            </td>
                            <td data-label="Applicant">
                                <strong>{{ $bill->cfaSubmission?->applicant_name ?: '—' }}</strong><br>
                                <span style="color:#64748b;font-size:0.78rem;">{{ $bill->cfaSubmission?->application_no ?: '—' }}</span>
                            </td>
                            <td data-label="District">
                                {{ $bill->district?->name ?: '—' }}
                                @if ($bill->district?->hub?->name)
                                    <div style="color:#64748b;font-size:0.76rem;">{{ $bill->district->hub->name }}</div>
                                @endif
                            </td>
                            <td data-label="Batch">{{ $bill->batch?->name ?: '—' }}</td>
                            <td data-label="Added by">{{ $bill->creator?->name ?: '—' }}</td>
                            <td data-label="Document">
                                @if ($bill->hasDocument())
                                    <a class="ibd-doc" href="{{ route($documentRoute, $bill) }}">{{ $bill->document_original_name ?: 'Download' }}</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($isPaginated)
                <div class="ibd-pager">{{ $rows->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const hub = document.getElementById('ibd-hub');
    const district = document.getElementById('ibd-district');
    if (!hub || !district) return;
    const districts = @json($districtJson);
    function sync() {
        const hubId = hub.value;
        const current = district.value;
        district.innerHTML = '<option value="">All districts</option>';
        districts.forEach(function (d) {
            if (hubId && String(d.hub_id) !== String(hubId)) return;
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = d.name;
            if (String(current) === String(d.id)) opt.selected = true;
            district.appendChild(opt);
        });
    }
    hub.addEventListener('change', sync);
})();
</script>
@endpush

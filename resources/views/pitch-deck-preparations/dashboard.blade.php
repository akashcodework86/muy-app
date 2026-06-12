@extends('layouts.admin')

@section('title', \App\Models\PitchDeckPreparation::MODULE_LABEL)
@section('heading', \App\Models\PitchDeckPreparation::MODULE_LABEL)

@push('styles')
<style>
    .pdp-shell { display:flex; flex-direction:column; gap:1.25rem; }
    .pdp-alert { border-radius:12px; padding:0.85rem 1rem; font-size:0.88rem; }
    .pdp-alert--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .pdp-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .pdp-hero {
        display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:1rem;
        padding:1.1rem 1.25rem; border-radius:16px;
        background:linear-gradient(135deg, #eef2ff 0%, #f8fafc 55%, #ecfdf5 100%);
        border:1px solid #e2e8f0;
    }
    .pdp-hero__title { margin:0; font-size:1rem; font-weight:800; color:#0f172a; }
    .pdp-hero__sub { margin:0.35rem 0 0; font-size:0.84rem; color:#64748b; }
    .pdp-stats { display:flex; flex-wrap:wrap; gap:0.65rem; align-items:stretch; }
    .pdp-stat { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:0.95rem 1.05rem; min-width:120px; }
    .pdp-stat__label { font-size:0.72rem; color:#64748b; text-transform:uppercase; letter-spacing:0.07em; font-weight:700; }
    .pdp-stat__value { margin-top:0.35rem; font-size:1.35rem; font-weight:800; color:#0f172a; }
    .pdp-stat--services { border-color:#bfdbfe; background:#f8fbff; }
    .pdp-stat--state { border-color:#bbf7d0; background:#f7fef9; }
    .pdp-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.2rem 1.3rem; }
    .pdp-filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:0.85rem; align-items:end; }
    .pdp-filter-field { display:flex; flex-direction:column; gap:0.35rem; }
    .pdp-filter-field label { font-size:0.78rem; font-weight:700; color:#0f172a; }
    .pdp-filter-field input, .pdp-filter-field select {
        width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px;
        padding:0.55rem 0.65rem; font-size:0.88rem; background:#fff;
    }
    .pdp-btn { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.58rem 0.95rem; font-weight:700; cursor:pointer; font-size:0.88rem; text-decoration:none; display:inline-flex; }
    .pdp-btn--secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .pdp-btn--export { background:#065f46; }
    .pdp-table-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:auto; }
    .pdp-table { width:100%; border-collapse:collapse; font-size:0.84rem; min-width:980px; }
    .pdp-table th { text-align:left; padding:0.72rem 0.75rem; border-bottom:1px solid #e2e8f0; font-size:0.68rem; font-weight:700; text-transform:uppercase; color:#64748b; }
    .pdp-table td { padding:0.72rem 0.75rem; border-bottom:1px solid #e2e8f0; vertical-align:top; }
    .pdp-empty { padding:1.25rem; color:#64748b; text-align:center; }
    .pdp-name { font-weight:800; color:#0f172a; }
    .pdp-meta { color:#64748b; font-size:0.78rem; margin-top:0.15rem; }
    .pdp-actions { display:flex; flex-wrap:wrap; gap:0.4rem; align-items:center; }
    .pdp-link { color:#4f46e5; font-weight:700; text-decoration:none; font-size:0.82rem; background:none; border:none; padding:0; cursor:pointer; font-family:inherit; }
    .pdp-link--detail { background:#eef2ff; border:1px solid #c7d2fe; border-radius:8px; padding:0.28rem 0.55rem; }
    .pdp-pill { display:inline-flex; padding:0.14rem 0.48rem; border-radius:999px; font-size:0.68rem; font-weight:800; }
    .pdp-pill--ok { background:#dcfce7; color:#166534; }
    .pdp-pill--muted { background:#f1f5f9; color:#475569; }
    .pdp-pill--services { background:#dbeafe; color:#1e40af; }
    .pdp-pill--state { background:#dcfce7; color:#166534; }
</style>
@endpush

@section('content')
@php
    use App\Support\PitchDeckCombinedDeliverablesSupport;

    $showRoute = match ($currentRole ?? '') {
        'state_admin' => 'admin.pitch-deck-preparations.show',
        default => 'spoc.pitch-deck-preparations.show',
    };
    $deckRoute = match ($currentRole ?? '') {
        'state_admin' => 'admin.pitch-deck-preparations.deck',
        default => 'spoc.pitch-deck-preparations.deck',
    };
    $editRoute = 'spoc.pitch-deck-preparations.edit';
    $serviceShowRoute = match ($currentRole ?? '') {
        'state_admin' => 'admin.phase3-services.show',
        default => 'spoc.services.show',
    };
    $dashboardRoute = ($currentRole ?? '') === 'state_admin'
        ? 'admin.pitch-deck-preparations.dashboard'
        : 'spoc.pitch-deck-preparations.dashboard';
@endphp
<div class="pdp-shell">
    @if (!empty($migrationMissing))
        <div class="pdp-alert pdp-alert--warning">
            <strong>Database update required.</strong> Run <code>php artisan migrate</code>.
        </div>
    @endif

    @if (session('status'))
        <div class="pdp-alert pdp-alert--success">{{ session('status') }}</div>
    @endif

    <div class="pdp-hero">
        <div>
            <h2 class="pdp-hero__title">All pitch decks (unified)</h2>
            <p class="pdp-hero__sub">
                MIS 8.3 — district staff service cases and state team entries combined.
            </p>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center;">
            <div class="pdp-stats">
                <div class="pdp-stat">
                    <div class="pdp-stat__label">Total</div>
                    <div class="pdp-stat__value">{{ number_format((int) ($totals['total'] ?? 0)) }}</div>
                </div>
                <div class="pdp-stat pdp-stat--services">
                    <div class="pdp-stat__label">District staff</div>
                    <div class="pdp-stat__value">{{ number_format((int) ($totals['services'] ?? 0)) }}</div>
                </div>
                <div class="pdp-stat pdp-stat--state">
                    <div class="pdp-stat__label">State team</div>
                    <div class="pdp-stat__value">{{ number_format((int) ($totals['state_team'] ?? 0)) }}</div>
                </div>
            </div>
            @if (!empty($canSubmit))
                <a href="{{ route('spoc.pitch-deck-preparations.create') }}" class="pdp-btn">+ New entry</a>
            @endif
            @if (!empty($exportRoute))
                <a href="{{ route($exportRoute, array_filter($filters ?? [])) }}" class="pdp-btn pdp-btn--export">Export CSV</a>
            @endif
        </div>
    </div>

    <div class="pdp-card">
        <form method="get" action="{{ route($dashboardRoute) }}">
            <div class="pdp-filters">
                <div class="pdp-filter-field">
                    <label for="q">Search</label>
                    <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Incubatee, app no, district…">
                </div>
                <div class="pdp-filter-field">
                    <label for="filled_by">Filled by</label>
                    <select id="filled_by" name="filled_by">
                        @foreach ($filledByOptions ?? [] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['filled_by'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pdp-filter-field">
                    <label for="district_id">District</label>
                    <select id="district_id" name="district_id">
                        <option value="">All districts</option>
                        @foreach ($districts ?? [] as $d)
                            <option value="{{ $d->id }}" @selected((int) ($filters['district_id'] ?? 0) === (int) $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pdp-filter-field">
                    <label for="from">From</label>
                    <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="pdp-filter-field">
                    <label for="to">To</label>
                    <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="pdp-filter-field" style="display:flex; flex-direction:row; gap:0.5rem;">
                    <button type="submit" class="pdp-btn">Filter</button>
                    <a href="{{ route($dashboardRoute) }}" class="pdp-btn pdp-btn--secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <div class="pdp-table-card">
        <table class="pdp-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Incubatee</th>
                    <th>Filled by</th>
                    <th>Onboarding</th>
                    <th>District</th>
                    <th>Prepared on</th>
                    <th>Prepared for</th>
                    <th>Support</th>
                    <th>Entered by</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $isServiceRow = ($row['source_channel'] ?? '') === PitchDeckCombinedDeliverablesSupport::CHANNEL_SERVICES;
                        $profile = $row['incubatee_profile'] ?? $incubateeProfiles[$row['id']] ?? [];
                        $preparation = $row['preparation_model'] ?? null;
                    @endphp
                    <tr>
                        <td>{{ $row['id'] }}</td>
                        <td>
                            <div class="pdp-name">{{ $row['incubatee_name'] }}</div>
                            <div class="pdp-meta">
                                @if (!empty($row['application_no']))
                                    {{ $row['application_no'] }}
                                @elseif (!empty($row['reference_number']))
                                    {{ $row['reference_number'] }}
                                @endif
                                @if (!empty($row['source_label']) && $row['source_label'] !== '—')
                                    @if (!empty($row['application_no']) || !empty($row['reference_number'])) · @endif
                                    {{ $row['source_label'] }}
                                @endif
                                @if (!empty($row['phone']))
                                    · {{ $row['phone'] }}
                                @endif
                            </div>
                        </td>
                        <td>
                            @if ($isServiceRow)
                                <span class="pdp-pill pdp-pill--services">{{ $row['filled_by_label'] }}</span>
                            @else
                                <span class="pdp-pill pdp-pill--state">{{ $row['filled_by_label'] }}</span>
                            @endif
                            <div class="pdp-meta">{{ $row['filled_by_name'] }}</div>
                        </td>
                        <td>
                            @if (!empty($profile['is_onboarded']))
                                <span class="pdp-pill pdp-pill--ok">Onboarded</span>
                            @else
                                <span class="pdp-pill pdp-pill--muted">{{ $profile['onboarding_status'] ?? 'Not onboarded' }}</span>
                            @endif
                        </td>
                        <td>{{ $row['district_name'] ?? '—' }}</td>
                        <td>{{ $row['prepared_on_display'] ?? '—' }}</td>
                        <td>{{ $row['prepared_for'] ?: '—' }}</td>
                        <td>{{ $row['support_mode'] ?? '—' }}</td>
                        <td>{{ $row['entered_by_name'] ?? '—' }}</td>
                        <td>
                            <div class="pdp-actions">
                                @if ($isServiceRow && !empty($row['service_case_id']))
                                    <a class="pdp-link pdp-link--detail" href="{{ route($serviceShowRoute, $row['service_case_id']) }}">Service case</a>
                                @elseif ($preparation)
                                    @if (!empty($isAdminView) || (int) $preparation->entered_by_user_id === (int) auth()->id())
                                    <a class="pdp-link pdp-link--detail" href="{{ route($showRoute, $preparation) }}">Full detail view</a>
                                    @endif
                                    @if (!empty($row['has_deck_file']))
                                        <button type="button"
                                            class="pdp-link js-pdp-deck-preview"
                                            data-deck-preview-url="{{ route($deckRoute, ['pitchDeckPreparation' => $preparation, 'inline' => 1]) }}"
                                            data-deck-download-url="{{ route($deckRoute, $preparation) }}"
                                            data-deck-name="{{ $preparation->deck_file_name }}">View deck</button>
                                    @endif
                                    @if (!empty($canSubmit) && (int) $preparation->entered_by_user_id === (int) auth()->id())
                                        <a class="pdp-link" href="{{ route($editRoute, $preparation) }}">Edit</a>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="pdp-empty">No entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'links'))
        <div>{{ $rows->links() }}</div>
    @endif
</div>

@include('pitch-deck-preparations.partials.deck-preview-modal')
@endsection

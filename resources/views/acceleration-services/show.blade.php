@extends('layouts.admin')

@section('title', 'Acceleration session detail')
@section('heading', 'Acceleration session detail')

@include('acceleration-services.partials.styles')

@php
    $items = $session->items ?? collect();
    $grouped = $items->groupBy('section');
    $sectionOrder = ['service_detail', 'cross_cutting', 'partnership'];
    $mediaCount = $items->sum(fn ($item) => $item->media?->count() ?? 0);
    $sectionCounts = [];
    foreach ($sectionOrder as $sectionKey) {
        $n = $grouped->get($sectionKey, collect())->count();
        if ($n > 0) {
            $sectionCounts[$sectionKey] = $n;
        }
    }
    // Any unexpected sections
    foreach ($grouped as $sectionKey => $sectionItems) {
        if (! isset($sectionCounts[$sectionKey]) && $sectionItems->isNotEmpty()) {
            $sectionCounts[$sectionKey] = $sectionItems->count();
        }
    }
@endphp

@push('styles')
<style>
    .accel-show { display:flex; flex-direction:column; gap:1.15rem; width:100%; max-width:100%; }
    .accel-show__nav { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:0.65rem; }
    .accel-show__hero {
        background:#fff;
        border:1px solid #e2e8f0;
        border-radius:16px;
        padding:1.15rem 1.25rem;
        box-shadow:0 1px 2px rgba(15,23,42,0.04);
        display:flex;
        flex-direction:column;
        gap:1rem;
    }
    .accel-show__hero-top {
        display:flex;
        justify-content:space-between;
        gap:0.85rem;
        flex-wrap:wrap;
        align-items:flex-start;
    }
    .accel-show__name { margin:0; font-size:1.25rem; font-weight:800; letter-spacing:-0.02em; color:#0f172a; }
    .accel-show__sub { margin:0.3rem 0 0; font-size:0.86rem; color:#64748b; line-height:1.45; }
    .accel-show__chips { display:flex; flex-wrap:wrap; gap:0.45rem; }
    .accel-show__chip {
        display:inline-flex;
        align-items:center;
        gap:0.35rem;
        padding:0.35rem 0.7rem;
        border-radius:999px;
        background:#f8fafc;
        border:1px solid #e2e8f0;
        font-size:0.78rem;
        font-weight:700;
        color:#334155;
    }
    .accel-show__chip strong { color:#0f172a; font-variant-numeric:tabular-nums; }
    .accel-show__chip--teal { background:#f0fdfa; border-color:#99f6e4; color:#0f766e; }
    .accel-meta {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
        gap:0.75rem;
        font-size:0.86rem;
        padding-top:0.85rem;
        border-top:1px solid #f1f5f9;
    }
    .accel-meta dt { font-weight:700; color:#64748b; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.04em; }
    .accel-meta dd { margin:0.15rem 0 0; color:#0f172a; font-weight:500; }

    .accel-show-section { margin-bottom:1.15rem; }
    .accel-show-section:last-child { margin-bottom:0; }
    .accel-show-section__head {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:0.65rem;
        margin:0 0 0.65rem;
        padding-bottom:0.45rem;
        border-bottom:1px solid #e2e8f0;
    }
    .accel-show-section__title { margin:0; font-size:0.92rem; font-weight:800; color:#0f172a; }
    .accel-show-section__count {
        font-size:0.72rem;
        font-weight:700;
        color:#0f766e;
        background:#f0fdfa;
        border:1px solid #99f6e4;
        border-radius:999px;
        padding:0.2rem 0.55rem;
    }

    .accel-item-card {
        border:1px solid #e2e8f0;
        border-radius:12px;
        padding:0.9rem 1rem;
        margin-bottom:0.65rem;
        background:#fff;
        box-shadow:0 1px 2px rgba(15,23,42,0.03);
    }
    .accel-item-card:last-child { margin-bottom:0; }
    .accel-item-card__head {
        display:flex;
        gap:0.65rem;
        align-items:flex-start;
    }
    .accel-item-card__num {
        width:1.65rem;
        height:1.65rem;
        border-radius:999px;
        background:linear-gradient(180deg,#14b8a6,#0d9488);
        color:#fff;
        font-size:0.75rem;
        font-weight:800;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        flex-shrink:0;
        margin-top:0.05rem;
    }
    .accel-item-card__title { margin:0; font-size:0.95rem; font-weight:800; color:#0f172a; line-height:1.35; }
    .accel-item-card__meta { margin:0.2rem 0 0; font-size:0.74rem; color:#64748b; }

    .accel-payload { margin:0.7rem 0 0; display:grid; gap:0; font-size:0.82rem; border:1px solid #eef2f7; border-radius:10px; overflow:hidden; }
    .accel-payload__row {
        display:grid;
        grid-template-columns:minmax(7rem, 32%) 1fr;
        gap:0.35rem 0.75rem;
        padding:0.55rem 0.75rem;
        background:#f8fafc;
        border-bottom:1px solid #eef2f7;
    }
    .accel-payload__row:nth-child(even) { background:#fff; }
    .accel-payload__row:last-child { border-bottom:none; }
    .accel-payload__label { color:#64748b; font-weight:700; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.03em; align-self:start; padding-top:0.1rem; }
    .accel-payload__value { color:#0f172a; font-weight:500; line-height:1.45; word-break:break-word; }

    .accel-media-list { display:flex; flex-wrap:wrap; gap:0.45rem; margin-top:0.65rem; }
    .accel-media-list a {
        font-size:0.8rem;
        color:#0f766e;
        font-weight:700;
        text-decoration:none;
        background:#f0fdfa;
        border:1px solid #99f6e4;
        border-radius:8px;
        padding:0.35rem 0.65rem;
    }
    .accel-media-list a:hover { background:#ccfbf1; }

    .accel-show__actions {
        display:flex;
        flex-wrap:wrap;
        gap:0.65rem;
        align-items:center;
        padding:0.85rem 0 0.25rem;
    }
    .accel-danger-btn {
        background:#fff;
        border:1px solid #fecaca;
        color:#b91c1c;
        padding:0.5rem 0.85rem;
        border-radius:10px;
        font-weight:700;
        cursor:pointer;
        font-family:inherit;
        font-size:0.84rem;
    }
    .accel-danger-btn:hover { background:#fef2f2; }

    @media (max-width: 640px) {
        .accel-payload__row { grid-template-columns:1fr; }
    }
</style>
@endpush

@section('content')
<div class="accel-show">
    <div class="accel-show__nav">
        <a href="{{ route($dashboardRoute) }}" class="accel-link">← Back to dashboard</a>
        @if (!empty($addServicesRoute))
            <a href="{{ route($addServicesRoute, ['from_session' => $session->id]) }}#accel-form" class="accel-btn accel-btn--secondary">+ Add more services</a>
        @endif
    </div>

    <div class="accel-show__hero">
        <div class="accel-show__hero-top">
            <div>
                <h3 class="accel-show__name">{{ $session->applicant_name }}</h3>
                <p class="accel-show__sub">
                    Session {{ $session->service_date?->format('d M Y') ?? '—' }}
                    @if ($session->application_no) · {{ $session->application_no }} @endif
                    @if ($session->district_name) · {{ $session->district_name }} @endif
                </p>
            </div>
            @if ($session->counts_for_7_2)
                <span class="accel-badge accel-badge--init">7.2 Initiation</span>
            @else
                <span class="accel-badge accel-badge--follow">Follow-up session</span>
            @endif
        </div>

        <div class="accel-show__chips">
            <span class="accel-show__chip accel-show__chip--teal"><strong>{{ $items->count() }}</strong> service{{ $items->count() === 1 ? '' : 's' }}</span>
            @foreach ($sectionCounts as $sectionKey => $count)
                <span class="accel-show__chip">{{ \App\Support\AccelerationServicesOptions::sectionLabel($sectionKey) }} <strong>{{ $count }}</strong></span>
            @endforeach
            @if ($mediaCount > 0)
                <span class="accel-show__chip"><strong>{{ $mediaCount }}</strong> attachment{{ $mediaCount === 1 ? '' : 's' }}</span>
            @endif
        </div>

        <dl class="accel-meta">
            <div><dt>Session date</dt><dd>{{ $session->service_date?->format('d M Y') }}</dd></div>
            <div><dt>Application no</dt><dd>{{ $session->application_no ?: '—' }}</dd></div>
            <div><dt>Phone</dt><dd>{{ $session->phone ?: '—' }}</dd></div>
            <div><dt>District</dt><dd>{{ $session->district_name ?: '—' }}</dd></div>
            <div><dt>Onboarding</dt><dd>{{ $session->onboard_label ?: '—' }}</dd></div>
            <div><dt>Submitted by</dt><dd>{{ $session->submitted_by_name }}</dd></div>
            <div><dt>Logged at</dt><dd>{{ $session->created_at?->format('d M Y H:i') }}</dd></div>
        </dl>
    </div>

    <div class="accel-card">
        <div class="accel-card__head" style="margin-bottom:1rem;">
            <h3 class="accel-card__title" style="margin:0;">Services recorded</h3>
            <p class="accel-card__sub" style="margin:0.25rem 0 0;">Grouped by category with field details for each tick.</p>
        </div>

        @if ($items->isEmpty())
            <p style="color:#64748b; margin:0;">No services recorded on this session.</p>
        @else
            @php $globalIndex = 0; @endphp
            @foreach ($sectionOrder as $sectionKey)
                @php $sectionItems = $grouped->get($sectionKey, collect()); @endphp
                @continue($sectionItems->isEmpty())
                <div class="accel-show-section">
                    <div class="accel-show-section__head">
                        <h4 class="accel-show-section__title">{{ \App\Support\AccelerationServicesOptions::sectionLabel($sectionKey) }}</h4>
                        <span class="accel-show-section__count">{{ $sectionItems->count() }}</span>
                    </div>
                    @foreach ($sectionItems as $item)
                        @php
                            $globalIndex++;
                            $schema = \App\Support\AccelerationItemSchemas::forKey((string) $item->item_key, (string) $item->section);
                            $payload = is_array($item->payload) ? $item->payload : [];
                            $serviceDate = $payload['service_date'] ?? $payload['date'] ?? null;
                        @endphp
                        <div class="accel-item-card">
                            <div class="accel-item-card__head">
                                <span class="accel-item-card__num">{{ $globalIndex }}</span>
                                <div style="min-width:0; flex:1;">
                                    <h5 class="accel-item-card__title">{{ $item->item_label }}</h5>
                                    <p class="accel-item-card__meta">
                                        {{ \App\Support\AccelerationServicesOptions::sectionLabel($item->section) }}
                                        @php
                                            $serviceDateLabel = null;
                                            if (! empty($serviceDate)) {
                                                try {
                                                    $serviceDateLabel = \Carbon\Carbon::parse($serviceDate)->format('d M Y');
                                                } catch (\Throwable) {
                                                    $serviceDateLabel = (string) $serviceDate;
                                                }
                                            }
                                        @endphp
                                        @if ($serviceDateLabel)
                                            · {{ $serviceDateLabel }}
                                        @endif
                                        @if ($item->is_custom)
                                            · Custom
                                        @endif
                                    </p>

                                    @if ($schema !== [] && $payload !== [])
                                        <div class="accel-payload">
                                            @foreach ($schema as $field)
                                                @php
                                                    $key = (string) ($field['key'] ?? '');
                                                    if ($key === '' || ! array_key_exists($key, $payload)) {
                                                        continue;
                                                    }
                                                @endphp
                                                <div class="accel-payload__row">
                                                    <div class="accel-payload__label">{{ $field['label'] ?? $key }}</div>
                                                    <div class="accel-payload__value">{!! \App\Support\SchemaValueFormatter::renderHtml($field, $payload[$key]) !!}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif ($item->remarks)
                                        <p style="margin:0.55rem 0 0; font-size:0.84rem; color:#334155;">{{ $item->remarks }}</p>
                                    @endif

                                    @if ($item->media->isNotEmpty())
                                        <div class="accel-media-list">
                                            @foreach ($item->media as $media)
                                                <a href="{{ route($mediaRoute, $media) }}" target="_blank" rel="noopener">{{ $media->original_name }}</a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach

            @foreach ($grouped as $sectionKey => $sectionItems)
                @continue(in_array($sectionKey, $sectionOrder, true) || $sectionItems->isEmpty())
                <div class="accel-show-section">
                    <div class="accel-show-section__head">
                        <h4 class="accel-show-section__title">{{ \App\Support\AccelerationServicesOptions::sectionLabel($sectionKey) }}</h4>
                        <span class="accel-show-section__count">{{ $sectionItems->count() }}</span>
                    </div>
                    @foreach ($sectionItems as $item)
                        @php $globalIndex++; @endphp
                        <div class="accel-item-card">
                            <div class="accel-item-card__head">
                                <span class="accel-item-card__num">{{ $globalIndex }}</span>
                                <div>
                                    <h5 class="accel-item-card__title">{{ $item->item_label }}</h5>
                                    @if ($item->remarks)
                                        <p style="margin:0.45rem 0 0; font-size:0.84rem; color:#334155;">{{ $item->remarks }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>

    <div class="accel-show__actions">
        @if (!empty($addServicesRoute))
            <a href="{{ route($addServicesRoute, ['from_session' => $session->id]) }}#accel-form" class="accel-btn">+ Add more services</a>
        @endif
        <a href="{{ route($dashboardRoute) }}" class="accel-btn accel-btn--secondary">Back to dashboard</a>
        @if (!empty($canDelete) && !empty($destroyRoute))
            <form method="post" action="{{ route($destroyRoute, $session) }}" onsubmit="return confirm('Delete this session?');" style="margin-left:auto;">
                @csrf
                @method('DELETE')
                <button type="submit" class="accel-danger-btn">Delete session</button>
            </form>
        @endif
    </div>
</div>
@endsection

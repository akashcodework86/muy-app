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
    @if (session('status'))
        <div class="accel-alert accel-alert--success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="accel-alert accel-alert--warning">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="accel-show__nav">
        <a href="{{ route($dashboardRoute) }}" class="accel-link">← Back to dashboard</a>
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
            @if (!empty($editRoute))
                <a href="{{ route($editRoute, $session) }}" class="accel-btn">Edit entry</a>
            @endif
            @if (!empty($addServicesRoute))
                <a href="{{ route($addServicesRoute, ['from_session' => $session->id]) }}#accel-form" class="accel-btn accel-btn--secondary">+ Add more services</a>
            @endif
        </div>
    </div>

    @if (!empty($workflowReady) && (string) $session->status === \App\Support\AccelerationServicesApproval::STATUS_SENT_BACK && $session->sent_back_remarks)
        <div class="accel-alert accel-alert--warning">
            <strong>Sent back by {{ $session->sent_back_by_name ?: 'checker' }}</strong>
            @if ($session->sent_back_at) ({{ $session->sent_back_at->format('d M Y H:i') }}) @endif
            — {{ $session->sent_back_remarks }}
        </div>
    @endif

    @if (!empty($workflowReady) && $session->isLocked())
        <div class="accel-alert accel-alert--success" style="margin:0;">
            This entry is <strong>approved and locked</strong>. To record more services for this incubatee, use “Add more services” — the new entry goes through approval again.
        </div>
    @endif

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
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.35rem;">
                @if (!empty($workflowReady))
                    <span class="accel-status accel-status--{{ (string) ($session->status ?? 'approved') }}">{{ $session->statusLabel() }}</span>
                @endif
                @if ($session->counts_for_7_2)
                    <span class="accel-badge accel-badge--init">7.2 Initiation</span>
                @else
                    <span class="accel-badge accel-badge--follow">Follow-up session</span>
                @endif
            </div>
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
            @if (!empty($workflowReady))
                <div><dt>State review</dt><dd>{{ $session->first_approved_by_name ? $session->first_approved_by_name.' · '.$session->first_approved_at?->format('d M Y H:i') : 'Pending' }}</dd></div>
                <div><dt>Final approval</dt><dd>{{ $session->final_approved_by_name ? $session->final_approved_by_name.' · '.$session->final_approved_at?->format('d M Y H:i') : 'Pending' }}</dd></div>
            @endif
        </dl>
    </div>

    @if (!empty($canApprove) && !empty($approveRoute) && !empty($sendBackRoute))
        <div class="accel-card" style="border-left:4px solid #4f46e5;">
            <h3 class="accel-card__title" style="margin:0 0 0.35rem;">Approval action needed</h3>
            <p class="accel-card__sub" style="margin:0 0 0.85rem;">
                @if ((string) $session->status === \App\Support\AccelerationServicesApproval::STATUS_PENDING_FINAL)
                    This entry is awaiting <strong>final approval</strong>. Approving makes it count towards the 7.2 deliverable and locks the entry.
                @else
                    This entry is awaiting <strong>state review</strong>. Approving forwards it for final approval.
                @endif
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-start;">
                <form method="post" action="{{ route($approveRoute, $session) }}" onsubmit="return confirm('Approve this entry?');">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ url()->previous() && str_contains(url()->previous(), '/spoc/service-cases') ? url()->previous() : route('spoc.service-cases.index') }}">
                    <button type="submit" class="accel-btn">
                        @if ((string) $session->status === \App\Support\AccelerationServicesApproval::STATUS_PENDING_FINAL)
                            Approve entry (final)
                        @else
                            Review &amp; forward
                        @endif
                    </button>
                </form>
                <form method="post" action="{{ route($sendBackRoute, $session) }}" style="flex:1;min-width:16rem;display:flex;flex-direction:column;gap:0.45rem;">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ url()->previous() && str_contains(url()->previous(), '/spoc/service-cases') ? url()->previous() : route('spoc.service-cases.index') }}">
                    <textarea name="remarks" rows="2" required placeholder="Remarks for the maker (required to send back)" style="width:100%;border:1px solid #d1d5db;border-radius:10px;padding:0.5rem 0.65rem;font-family:inherit;font-size:0.86rem;resize:vertical;">{{ old('remarks') }}</textarea>
                    <div>
                        <button type="submit" class="accel-danger-btn" onclick="return confirm('Send this entry back to {{ $session->submitted_by_name }}?');">Send back with remarks</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

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

    @if (!empty($workflowReady))
        <div class="accel-card">
            <h3 class="accel-card__title" style="margin:0 0 0.75rem;">Activity log</h3>
            @if (($events ?? collect())->isEmpty())
                <p style="color:#64748b;margin:0;font-size:0.86rem;">
                    No workflow events recorded yet.
                    @if ($session->isLocked()) This entry predates the approval workflow and is treated as approved. @endif
                </p>
            @else
                <div style="display:flex;flex-direction:column;">
                    @foreach ($events as $event)
                        <div style="display:flex;gap:0.75rem;padding:0.6rem 0;border-bottom:1px solid #f1f5f9;">
                            <div style="flex-shrink:0;width:8.5rem;font-size:0.74rem;color:#64748b;font-weight:600;">
                                {{ $event->created_at?->format('d M Y H:i') }}
                            </div>
                            <div style="min-width:0;">
                                <div style="font-size:0.85rem;font-weight:700;color:#0f172a;">
                                    {{ \App\Support\AccelerationServicesApproval::actionLabel((string) $event->action) }}
                                </div>
                                <div style="font-size:0.76rem;color:#64748b;margin-top:0.1rem;">
                                    {{ $event->actor_name }}
                                    @if ($event->actor_role) · {{ str_replace('_', ' ', $event->actor_role) }} @endif
                                </div>
                                @if ($event->remarks)
                                    <div style="font-size:0.8rem;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:0.35rem 0.55rem;margin-top:0.3rem;">
                                        {{ $event->remarks }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div class="accel-show__actions">
        @if (!empty($editRoute))
            <a href="{{ route($editRoute, $session) }}" class="accel-btn">Edit entry</a>
        @endif
        @if (!empty($addServicesRoute))
            <a href="{{ route($addServicesRoute, ['from_session' => $session->id]) }}#accel-form" class="accel-btn accel-btn--secondary">+ Add more services</a>
        @endif
        <a href="{{ route($dashboardRoute) }}" class="accel-btn accel-btn--secondary">Back to dashboard</a>
        @if (!empty($canDelete) && !empty($destroyRoute))
            <form method="post" action="{{ route($destroyRoute, $session) }}" onsubmit="return confirm('Delete this draft?');" style="margin-left:auto;">
                @csrf
                @method('DELETE')
                <button type="submit" class="accel-danger-btn">Delete draft</button>
            </form>
        @endif
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('title', ($batch->batch_name ?? 'Legacy batch').' · Legacy')
@section('heading', $batch->batch_name ?? 'Legacy batch')

@push('styles')
<style>
    .batch-shell { display: flex; flex-direction: column; gap: 1rem; }
    .batch-crumbs { font-size: 0.82rem; color: #64748b; }
    .batch-crumbs a { color: #0d9488; text-decoration: none; font-weight: 600; }
    .batch-crumbs a:hover { text-decoration: underline; }
    .batch-hero {
        padding: 1.1rem 1.25rem;
        border-radius: 16px;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 60%, #eef2ff 100%);
        border: 1px solid rgba(148, 163, 184, 0.35);
        box-shadow: 0 12px 30px -16px rgba(79, 70, 229, 0.15);
    }
    .batch-hero__name { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0 0 0.35rem; }
    .batch-hero__sub { font-size: 0.88rem; color: #475569; margin: 0; }
    .chip {
        display: inline-flex; align-items: center; gap: 0.3rem;
        padding: 0.2rem 0.6rem; border-radius: 999px;
        font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em;
    }
    .chip--muted { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .members-wrap {
        background: #fff;
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 14px;
        overflow: hidden;
    }
    .members-head {
        padding: 0.85rem 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .members-head h3 { margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a; }
    .muted { color: #64748b; font-size: 0.82rem; }
    .m-table { width: 100%; border-collapse: collapse; }
    .m-table thead th {
        text-align: left; padding: 0.65rem 0.9rem;
        background: #ffffff; font-size: 0.72rem;
        letter-spacing: 0.08em; text-transform: uppercase;
        color: #475569; font-weight: 700;
        border-bottom: 1px solid #e2e8f0; white-space: nowrap;
    }
    .m-table tbody td { padding: 0.7rem 0.9rem; font-size: 0.875rem; border-bottom: 1px solid #f1f5f9; color: #0f172a; }
    .m-table tbody tr:hover { background: rgba(20, 184, 166, 0.05); }
    .m-table a { color: #0d9488; font-weight: 600; text-decoration: none; }
    .m-table a:hover { text-decoration: underline; }
    @media (max-width: 720px) {
        .m-table thead { display: none; }
        .m-table, .m-table tbody, .m-table tr, .m-table td { display: block; width: 100%; }
        .m-table tr { padding: 0.55rem 0; border-bottom: 1px solid #e2e8f0; }
        .m-table td { padding: 0.2rem 0.9rem; border: none; }
        .m-table td::before {
            content: attr(data-label);
            display: inline-block; min-width: 120px;
            font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em;
            color: #64748b; font-weight: 700; margin-right: 0.5rem;
        }
    }
</style>
@endpush

@section('content')
@php
    $totalMembers = $members->count();
@endphp
<div class="batch-shell">
    <div class="batch-crumbs">
        <a href="{{ $routeIndex }}?source=legacy">← Back to legacy batches</a>
    </div>

    <div class="batch-hero">
        <p class="batch-hero__name">{{ $batch->batch_name ?? '—' }}</p>
        <p class="batch-hero__sub">
            Legacy batch ID <strong>#{{ $legacyBatchId }}</strong>
            @if (! empty($batch->onboard_district))
                · Onboarding district: <strong>{{ $batch->onboard_district }}</strong>
            @endif
        </p>
        <p style="margin:0.75rem 0 0;">
            <span class="chip chip--muted">rbiphase2 · read-only</span>
        </p>
        <p class="muted" style="margin:0.65rem 0 0;font-size:0.82rem;">
            Operational work continues in Phase 3. Use this view to see who was onboarded in the legacy system.
        </p>
    </div>

    <div class="members-wrap">
        <div class="members-head">
            <h3>Members</h3>
            <span class="muted">{{ $totalMembers }} {{ $totalMembers === 1 ? 'member' : 'members' }}</span>
        </div>
        <table class="m-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Application ID</th>
                    <th>Application no.</th>
                    <th>Applicant</th>
                    <th>Phone</th>
                    <th>District</th>
                    <th>Form stage</th>
                    <th>More</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $i => $m)
                    <tr>
                        <td data-label="#">{{ $i + 1 }}</td>
                        <td data-label="Application ID">{{ $m['application_id'] }}</td>
                        <td data-label="Application no.">{{ $m['application_no'] !== '' ? $m['application_no'] : '—' }}</td>
                        <td data-label="Applicant"><strong>{{ $m['applicant_name'] ?: '—' }}</strong></td>
                        <td data-label="Phone">{{ $m['phone'] ?: '—' }}</td>
                        <td data-label="District">{{ $m['district'] ?: '—' }}</td>
                        <td data-label="Form stage">{{ $m['form_stage'] ?: '—' }}</td>
                        <td data-label="More">
                            @if (auth()->user()->role === 'district_staff')
                                @if (app(\App\Services\AppSettingsService::class)->isEnabled('service_module.enabled'))
                                    <a href="{{ route('staff.services.create', ['legacy_application_id' => $m['application_id']]) }}" style="display:inline-block;margin-right:0.5rem;">Assign service</a>
                                @endif
                                <a href="{{ route('staff.phase2-data', array_filter(['search' => $m['application_no'] ?: (string) $m['application_id']])) }}">Phase 2 data</a>
                            @elseif (auth()->user()->role === 'state_admin')
                                <a href="{{ route('admin.phase2-cfa.index', array_filter(['search' => $m['application_no'] ?: (string) $m['application_id']])) }}">Legacy CFA list</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="muted" style="text-align:center;padding:1.5rem;">No members in this batch for your scope.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

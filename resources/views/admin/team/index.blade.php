@extends('layouts.admin')

@section('title', 'Team')
@section('heading', 'Team')

@push('styles')
<style>
    .team-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
    .team-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.75rem;
    }
    .team-stat {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.7rem 0.85rem;
    }
    .team-stat__label {
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
        font-weight: 700;
    }
    .team-stat__value {
        margin-top: 0.18rem;
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
    }
    .team-section {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid rgba(148, 163, 184, 0.32);
        border-radius: 16px;
        padding: 1rem;
    }
    .team-section h3 {
        margin: 0 0 0.75rem;
        font-size: 0.98rem;
        color: #0f172a;
        font-weight: 800;
    }
    .team-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 0.75rem;
    }
    .team-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.75rem;
        display: flex;
        gap: 0.65rem;
        align-items: flex-start;
    }
    .team-avatar {
        width: 46px;
        height: 46px;
        border-radius: 999px;
        object-fit: cover;
        border: 1px solid rgba(148, 163, 184, 0.4);
        background: #e2e8f0;
        flex-shrink: 0;
    }
    .team-avatar-fallback {
        width: 46px;
        height: 46px;
        border-radius: 999px;
        background: linear-gradient(135deg, #6366f1, #14b8a6);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        font-weight: 800;
        flex-shrink: 0;
    }
    .team-meta { min-width: 0; }
    .team-name { font-size: 0.88rem; font-weight: 800; color: #0f172a; margin: 0 0 0.2rem; }
    .team-line { font-size: 0.76rem; color: #475569; margin: 0.08rem 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .team-empty { font-size: 0.82rem; color: #64748b; padding: 0.35rem 0.1rem; }
</style>
@endpush

@section('content')
<div class="team-grid">
    <section class="team-stats">
        <div class="team-stat">
            <div class="team-stat__label">Total members</div>
            <div class="team-stat__value">{{ number_format((int) ($totalMembers ?? 0)) }}</div>
        </div>
        <div class="team-stat">
            <div class="team-stat__label">Total designations</div>
            <div class="team-stat__value">{{ number_format((int) ($totalDesignations ?? 0)) }}</div>
        </div>
    </section>

    @forelse ($designationGroups as $designation => $members)
        <section class="team-section">
            <h3>{{ $designation }} ({{ count($members) }})</h3>
            <div class="team-cards">
                @foreach ($members as $member)
                    <article class="team-card">
                        @if ($member->avatarUrl())
                            <img src="{{ $member->avatarUrl() }}" alt="" class="team-avatar">
                        @else
                            <span class="team-avatar-fallback">{{ strtoupper(substr(trim((string) $member->name), 0, 1)) ?: '?' }}</span>
                        @endif
                        <div class="team-meta">
                            <p class="team-name">{{ $member->name }}</p>
                            <p class="team-line">{{ $member->email ?: '—' }}</p>
                            <p class="team-line">{{ $member->phone ?: '—' }}</p>
                            <p class="team-line">District: {{ $member->district?->name ?? '—' }}</p>
                            <p class="team-line">Designation: {{ $member->designationRecord?->name ?? '—' }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <section class="team-section">
            <p class="team-empty">No team members found.</p>
        </section>
    @endforelse
</div>
@endsection


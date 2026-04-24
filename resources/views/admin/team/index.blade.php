@extends('layouts.admin')

@section('title', 'Team')
@section('heading', 'Team')

@push('styles')
<style>
    .team-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
    .team-filters {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.75rem;
        display: grid;
        grid-template-columns: minmax(220px, 1fr) repeat(3, minmax(150px, 200px)) auto;
        gap: 0.55rem;
        align-items: end;
    }
    .team-filters .fld { display: flex; flex-direction: column; gap: 0.22rem; }
    .team-filters label { font-size: 0.68rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; }
    .team-filters input, .team-filters select {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 0.45rem 0.55rem;
        font: inherit;
        background: #fff;
    }
    .team-filters .actions { display: flex; gap: 0.45rem; }
    .team-btn {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 0.45rem 0.7rem;
        text-decoration: none;
        background: #fff;
        color: #334155;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
    }
    .team-btn--primary { background: linear-gradient(135deg, #0d9488, #4f46e5); color: #fff; border-color: transparent; }
    @media (max-width: 980px) {
        .team-filters { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 580px) {
        .team-filters { grid-template-columns: 1fr; }
    }
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
    .team-meta { min-width: 0; flex: 1; }
    .team-name { font-size: 0.88rem; font-weight: 800; color: #0f172a; margin: 0 0 0.2rem; }
    .team-line {
        font-size: 0.76rem;
        color: #475569;
        margin: 0.08rem 0;
        white-space: normal;
        word-break: break-word;
        line-height: 1.35;
    }
    .team-empty { font-size: 0.82rem; color: #64748b; padding: 0.35rem 0.1rem; }
</style>
@endpush

@section('content')
<div class="team-grid">
    <form method="get" action="{{ route('team.index') }}" class="team-filters">
        <div class="fld">
            <label for="q">Search</label>
            <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email, mobile, district, designation">
        </div>
        <div class="fld">
            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="">All roles</option>
                @foreach (($roles ?? []) as $role)
                    <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ ucfirst(str_replace('_', ' ', $role)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="fld">
            <label for="designation_id">Designation</label>
            <select id="designation_id" name="designation_id">
                <option value="">All designations</option>
                @foreach (($designations ?? []) as $designation)
                    <option value="{{ $designation->id }}" @selected((int) ($filters['designation_id'] ?? 0) === (int) $designation->id)>{{ $designation->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="fld">
            <label for="district_id">District</label>
            <select id="district_id" name="district_id">
                <option value="">All districts</option>
                @foreach (($districts ?? []) as $district)
                    <option value="{{ $district->id }}" @selected((int) ($filters['district_id'] ?? 0) === (int) $district->id)>{{ $district->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="actions">
            <button type="submit" class="team-btn team-btn--primary">Apply</button>
            <a href="{{ route('team.index') }}" class="team-btn">Reset</a>
        </div>
    </form>

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

    @forelse ($designationGroups as $section)
        <section class="team-section">
            <h3>{{ $section['title'] }} ({{ count($section['members']) }})</h3>
            <div class="team-cards">
                @foreach ($section['members'] as $member)
                    <article class="team-card">
                        @if (!empty($member['avatar_url']))
                            <img src="{{ $member['avatar_url'] }}" alt="" class="team-avatar">
                        @else
                            <span class="team-avatar-fallback">{{ strtoupper(substr(trim((string) ($member['name'] ?? '')), 0, 1)) ?: '?' }}</span>
                        @endif
                        <div class="team-meta">
                            <p class="team-name">{{ $member['name'] }}</p>
                            <p class="team-line">{{ $member['email'] ?: '—' }}</p>
                            <p class="team-line">{{ $member['phone'] ?: '—' }}</p>
                            @if (!empty($member['spoc_districts']))
                                <p class="team-line">District mapping: {{ implode(', ', $member['spoc_districts']) }}</p>
                            @else
                                <p class="team-line">District: {{ $member['district'] ?: '—' }}</p>
                            @endif
                            <p class="team-line">Designation: {{ $member['designation'] ?: '—' }}</p>
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


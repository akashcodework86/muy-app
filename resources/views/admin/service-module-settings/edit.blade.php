@extends('layouts.admin')

@section('title', 'Service module settings')
@section('heading', 'Service module settings')

@section('content')
    <p style="font-size:0.9rem; color:#52525b; margin:0 0 1rem; max-width:55rem;">
        Control when and for whom the service delivery workflow (maker&ndash;checker) is live.
        Changes take effect immediately &mdash; no redeploy needed.
    </p>

    @if (session('status'))
        <p style="background:#dcfce7; color:#166534; padding:0.5rem 0.75rem; border-radius:6px; font-size:0.88rem; margin:0.5rem 0 1rem; max-width:55rem;">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul style="color:#b91c1c; margin:0 0 1rem; padding-left:1.2rem; font-size:0.88rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.service-module-settings.update') }}" style="max-width:55rem;">
        @csrf
        @method('PUT')

        {{-- Master switch --}}
        <section style="background:#fff; border:1px solid #e4e4e7; border-radius:12px; padding:1rem 1.1rem; margin-bottom:1rem;">
            <header style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem;">
                <div>
                    <h3 style="margin:0 0 0.25rem; font-size:1rem; color:#18181b;">Service module — master switch</h3>
                    <p style="margin:0; font-size:0.82rem; color:#52525b; line-height:1.45;">
                        When <strong>ON</strong>, district staff see the "Services" menu and can create new service cases.<br>
                        When <strong>OFF</strong>, no new cases can be created, but existing in-flight cases
                        (draft / pending approval / sent back) can still be completed so nothing is stranded.
                    </p>
                </div>
                <label style="display:inline-flex; align-items:center; gap:0.5rem; cursor:pointer; flex-shrink:0;">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" @checked($enabled) style="width:1.1rem; height:1.1rem; accent-color:#4f46e5; cursor:pointer;">
                    <span style="font-weight:600; font-size:0.92rem; color:{{ $enabled ? '#166534' : '#991b1b' }};">
                        {{ $enabled ? 'ENABLED' : 'DISABLED' }}
                    </span>
                </label>
            </header>
        </section>

        {{-- Eligibility scope --}}
        <section style="background:#fff; border:1px solid #e4e4e7; border-radius:12px; padding:1rem 1.1rem; margin-bottom:1rem;">
            <header style="margin-bottom:0.75rem;">
                <h3 style="margin:0 0 0.25rem; font-size:1rem; color:#18181b;">Incubatee eligibility</h3>
                <p style="margin:0; font-size:0.82rem; color:#52525b; line-height:1.45;">
                    Which incubatees are eligible to receive services from staff.
                </p>
            </header>

            <label style="display:flex; gap:0.6rem; align-items:flex-start; padding:0.7rem 0.85rem; border:1px solid {{ $eligibility === 'onboarded_only' ? '#c7d2fe' : '#e4e4e7' }}; border-radius:10px; background:{{ $eligibility === 'onboarded_only' ? '#eef2ff' : '#fff' }}; margin-bottom:0.55rem; cursor:pointer;">
                <input type="radio" name="eligibility" value="onboarded_only" @checked($eligibility === 'onboarded_only') style="margin-top:0.2rem; accent-color:#4f46e5;">
                <span>
                    <span style="display:block; font-weight:600; font-size:0.92rem; color:#18181b;">Only onboarded incubatees <span style="color:#6366f1; font-size:0.75rem; font-weight:500;">(recommended)</span></span>
                    <span style="display:block; font-size:0.8rem; color:#52525b; margin-top:0.15rem;">Staff can raise services only for incubatees who are part of at least one onboarding batch (any stage).</span>
                </span>
            </label>

            <label style="display:flex; gap:0.6rem; align-items:flex-start; padding:0.7rem 0.85rem; border:1px solid {{ $eligibility === 'all' ? '#c7d2fe' : '#e4e4e7' }}; border-radius:10px; background:{{ $eligibility === 'all' ? '#eef2ff' : '#fff' }}; cursor:pointer;">
                <input type="radio" name="eligibility" value="all" @checked($eligibility === 'all') style="margin-top:0.2rem; accent-color:#4f46e5;">
                <span>
                    <span style="display:block; font-weight:600; font-size:0.92rem; color:#18181b;">All incubatees</span>
                    <span style="display:block; font-size:0.8rem; color:#52525b; margin-top:0.15rem;">Any CFA submission in the staff's district is eligible &mdash; no batch required.</span>
                </span>
            </label>
        </section>

        {{-- Deliverables inline edit --}}
        <section style="background:#fff; border:1px solid #e4e4e7; border-radius:12px; padding:1rem 1.1rem; margin-bottom:1rem;">
            <header style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem;">
                <div>
                    <h3 style="margin:0 0 0.25rem; font-size:1rem; color:#18181b;">Deliverables page — edit mode</h3>
                    <p style="margin:0; font-size:0.82rem; color:#52525b; line-height:1.45;">
                        When <strong>ON</strong>, state admins can edit <em>Type of Indicator</em> and
                        <em>Spoke/Hub/State</em> directly on the Deliverables report.<br>
                        When <strong>OFF</strong>, those columns are read-only for everyone.
                    </p>
                    <p style="margin:0.5rem 0 0; font-size:0.82rem;">
                        <a href="{{ route('admin.deliverables.index') }}" style="color:#4f46e5;">Open Deliverables page →</a>
                    </p>
                </div>
                <label style="display:inline-flex; align-items:center; gap:0.5rem; cursor:pointer; flex-shrink:0;">
                    <input type="hidden" name="deliverables_indicator_metadata_editable" value="0">
                    <input type="checkbox" name="deliverables_indicator_metadata_editable" value="1" @checked($deliverablesIndicatorMetadataEditable) style="width:1.1rem; height:1.1rem; accent-color:#4f46e5; cursor:pointer;">
                    <span style="font-weight:600; font-size:0.92rem; color:{{ $deliverablesIndicatorMetadataEditable ? '#166534' : '#991b1b' }};">
                        {{ $deliverablesIndicatorMetadataEditable ? 'EDITABLE' : 'READ-ONLY' }}
                    </span>
                </label>
            </header>
        </section>

        <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
            <button type="submit" style="background:#4f46e5; color:#fff; border:none; padding:0.6rem 1.25rem; border-radius:8px; font-weight:600; font-size:0.9rem; cursor:pointer;">
                Save settings
            </button>
        </div>
    </form>
@endsection

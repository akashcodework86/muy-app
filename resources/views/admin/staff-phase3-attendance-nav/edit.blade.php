@extends('layouts.admin')

@section('title', 'Staff training menus')
@section('heading', 'District staff — training menus')

@section('content')
    <p style="font-size:0.9rem; color:#52525b; margin:0 0 1rem; max-width:55rem;">
        Turn each item <strong>off</strong> to remove it from the district staff header and block direct links.
        State admin and SPOC dashboards are unchanged.
        <strong>Default:</strong> every item is <strong>visible</strong> until you save a change here (or turn one off).
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

    <form method="POST" action="{{ route('admin.staff-phase3-attendance-nav.update') }}" style="max-width:55rem;">
        @csrf
        @method('PUT')

        @php
            $rows = [
                ['key' => 'training_package', 'label' => 'Training Package Attendance', 'on' => $trainingPackage],
                ['key' => 'technical_training', 'label' => 'Technical training to incubatees', 'on' => $technicalTraining],
                ['key' => 'eap_edp_session', 'label' => 'EAP / EDP sessions', 'on' => $eapEdpSession],
                ['key' => 'district_workshop', 'label' => 'District level workshop', 'on' => $districtWorkshop],
            ];
        @endphp

        <div style="display:grid; gap:0.75rem; margin-bottom:1.25rem;">
            @foreach ($rows as $row)
                <section style="background:#fff; border:1px solid #e4e4e7; border-radius:12px; padding:0.95rem 1.1rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                    <div style="min-width:12rem;">
                        <h3 style="margin:0; font-size:0.98rem; color:#18181b;">{{ $row['label'] }}</h3>
                        <p style="margin:0.25rem 0 0; font-size:0.78rem; color:#71717a;">District staff top bar + all URLs for this module</p>
                    </div>
                    <label style="display:inline-flex; align-items:center; gap:0.55rem; cursor:pointer; flex-shrink:0; user-select:none;">
                        <input type="hidden" name="{{ $row['key'] }}" value="0">
                        <input type="checkbox" name="{{ $row['key'] }}" value="1" @checked($row['on']) style="width:1.15rem; height:1.15rem; accent-color:#0d9488; cursor:pointer;">
                        <span style="font-weight:700; font-size:0.88rem; letter-spacing:0.02em; padding:0.35rem 0.75rem; border-radius:999px; background:{{ $row['on'] ? 'linear-gradient(135deg,#ccfbf1,#ecfeff)' : '#f4f4f5' }}; color:{{ $row['on'] ? '#0f766e' : '#71717a' }}; border:1px solid {{ $row['on'] ? '#99f6e4' : '#e4e4e7' }};">
                            {{ $row['on'] ? 'VISIBLE' : 'HIDDEN' }}
                        </span>
                    </label>
                </section>
            @endforeach
        </div>

        <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
            <button type="submit" style="background:#0d9488; color:#fff; border:none; padding:0.6rem 1.25rem; border-radius:8px; font-weight:600; font-size:0.9rem; cursor:pointer;">
                Save
            </button>
        </div>
    </form>
@endsection

@extends('layouts.admin')

@section('title', 'Service SPOCs')
@section('heading', 'Service SPOCs — district assignment')

@section('content')
    @php
        $totalDistricts = $hubs->sum(fn ($h) => $h->districts->count());
        $assignedDistricts = $hubs->sum(fn ($h) => $h->districts->filter(fn ($d) => $d->serviceSpoc)->count());
        $unassignedDistricts = $totalDistricts - $assignedDistricts;
    @endphp

    <p style="font-size:0.9rem; color:#52525b; margin:0 0 0.75rem;">
        Map each district to exactly one <strong>State Staff (SPOC)</strong>. SPOCs approve / send back / reject service cases raised by district staff for services that require maker–checker verification.
        A SPOC can cover multiple districts, but a district can only have one SPOC at a time.
    </p>
    <p style="font-size:0.85rem; color:#52525b; margin:0 0 1rem;">
        Don't see the right names?
        <a href="{{ route('admin.state-staff.index') }}">Manage State Staff (SPOC) users</a>.
    </p>

    @if (session('status'))
        <p style="background:#dcfce7; color:#166534; padding:0.5rem 0.75rem; border-radius:6px; font-size:0.88rem; margin:0.5rem 0 1rem;">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul style="color:#b91c1c; margin:0 0 1rem; padding-left:1.2rem; font-size:0.88rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
        <span style="background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe; padding:0.25rem 0.55rem; border-radius:999px; font-size:0.78rem; font-weight:600;">Districts: {{ $totalDistricts }}</span>
        <span style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0; padding:0.25rem 0.55rem; border-radius:999px; font-size:0.78rem; font-weight:600;">Assigned: {{ $assignedDistricts }}</span>
        <span style="background:{{ $unassignedDistricts > 0 ? '#fef3c7' : '#f1f5f9' }}; color:{{ $unassignedDistricts > 0 ? '#92400e' : '#475569' }}; border:1px solid {{ $unassignedDistricts > 0 ? '#fde68a' : '#e2e8f0' }}; padding:0.25rem 0.55rem; border-radius:999px; font-size:0.78rem; font-weight:600;">Unassigned: {{ $unassignedDistricts }}</span>
        <span style="background:#fff; color:#475569; border:1px solid #e2e8f0; padding:0.25rem 0.55rem; border-radius:999px; font-size:0.78rem; font-weight:600;">Active SPOCs: {{ $spocs->count() }}</span>
    </div>

    @if ($spocs->isEmpty())
        <div style="background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:8px; padding:0.85rem 1rem; margin-bottom:1rem; font-size:0.88rem;">
            No active State Staff (SPOC) users yet.
            <a href="{{ route('admin.state-staff.create') }}">Add a SPOC user</a> first, then come back here to assign districts.
        </div>
    @endif

    {{-- Mode tabs --}}
    <div role="tablist" aria-label="Assignment view" style="display:flex; gap:0.35rem; border-bottom:1px solid #e4e4e7; margin-bottom:1rem;">
        <button type="button" id="tab-by-spoc" role="tab" aria-selected="true" aria-controls="panel-by-spoc" onclick="window.__spocsSwitch('by-spoc')" style="background:#fff; border:1px solid #e4e4e7; border-bottom:none; padding:0.55rem 1rem; border-radius:10px 10px 0 0; font-weight:600; font-size:0.88rem; cursor:pointer; color:#18181b; margin-bottom:-1px;">
            By SPOC <span style="font-weight:400; color:#71717a;">(tick multiple districts)</span>
        </button>
        <button type="button" id="tab-by-district" role="tab" aria-selected="false" aria-controls="panel-by-district" onclick="window.__spocsSwitch('by-district')" style="background:#fafafa; border:1px solid transparent; padding:0.55rem 1rem; border-radius:10px 10px 0 0; font-weight:600; font-size:0.88rem; cursor:pointer; color:#71717a;">
            By district <span style="font-weight:400;">(row-by-row)</span>
        </button>
    </div>

    {{-- Panel: By SPOC (checkbox grid per SPOC) --}}
    <div id="panel-by-spoc" role="tabpanel" aria-labelledby="tab-by-spoc">
        @if ($spocs->isEmpty())
            <p style="color:#71717a; font-size:0.88rem;">Add a SPOC first to use this mode.</p>
        @else
            <p style="font-size:0.85rem; color:#52525b; margin:0 0 0.75rem;">
                Pick a SPOC card below, tick every district they should cover, and Save. Districts already held by someone else are marked — ticking them will <strong>reassign</strong> from that SPOC to this one.
            </p>

            @php
                $focusSpocId = (int) request()->query('focus_spoc', 0);
                $spocAssignedSet = [];
                foreach ($spocDistricts as $sid => $dids) {
                    foreach ($dids as $did) {
                        $spocAssignedSet[$sid][$did] = true;
                    }
                }
                // district_id -> spoc_id (for "held by someone else" badges)
                $districtHolder = [];
                foreach ($spocDistricts as $sid => $dids) {
                    foreach ($dids as $did) {
                        $districtHolder[$did] = (int) $sid;
                    }
                }
            @endphp

            @foreach ($spocs as $spoc)
                @php
                    $myIds = $spocAssignedSet[$spoc->id] ?? [];
                    $myCount = count($myIds);
                    $isOpen = $focusSpocId === (int) $spoc->id || ($focusSpocId === 0 && $loop->first);
                @endphp
                <details @if ($isOpen) open @endif style="background:#fff; border:1px solid #e4e4e7; border-radius:10px; margin-bottom:0.75rem;">
                    <summary style="cursor:pointer; padding:0.75rem 1rem; display:flex; flex-wrap:wrap; align-items:center; gap:0.65rem; font-weight:600; font-size:0.95rem; color:#0f172a;">
                        <span style="width:2rem; height:2rem; border-radius:50%; background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:0.78rem; font-weight:700;">
                            {{ strtoupper(mb_substr($spoc->name, 0, 1)) }}
                        </span>
                        <span>{{ $spoc->name }}</span>
                        <span style="font-weight:400; font-size:0.78rem; color:#64748b;">{{ $spoc->email }}</span>
                        <span style="background:{{ $myCount > 0 ? '#dcfce7' : '#fef3c7' }}; color:{{ $myCount > 0 ? '#166534' : '#92400e' }}; border:1px solid {{ $myCount > 0 ? '#bbf7d0' : '#fde68a' }}; padding:0.15rem 0.55rem; border-radius:999px; font-size:0.72rem; font-weight:700;">
                            {{ $myCount }} {{ Str::plural('district', $myCount) }}
                        </span>
                        <span style="flex:1;"></span>
                        <span style="font-size:0.75rem; color:#71717a;">click to expand</span>
                    </summary>

                    <form method="post" action="{{ route('admin.service-spocs.update-for-spoc') }}" style="padding:0 1rem 1rem;">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="spoc_id" value="{{ $spoc->id }}">

                        @foreach ($hubs as $hub)
                            @if ($hub->districts->isEmpty()) @continue @endif
                            <div style="margin-top:0.5rem; padding:0.65rem 0.8rem; background:#fafafa; border:1px solid #f4f4f5; border-radius:8px;">
                                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
                                    <strong style="font-size:0.85rem; color:#3730a3;">{{ $hub->name }} Hub</strong>
                                    <span style="font-size:0.72rem; color:#71717a;">{{ $hub->districts->count() }} districts</span>
                                </div>
                                <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:0.35rem 0.75rem;">
                                    @foreach ($hub->districts as $d)
                                        @php
                                            $holderId = $districtHolder[$d->id] ?? null;
                                            $isMine = $holderId === (int) $spoc->id;
                                            $otherHolder = $holderId && ! $isMine ? $spocs->firstWhere('id', $holderId) : null;
                                        @endphp
                                        <label style="display:flex; align-items:flex-start; gap:0.4rem; cursor:pointer; padding:0.2rem 0; font-size:0.85rem;">
                                            <input type="checkbox" name="district_ids[]" value="{{ $d->id }}" @checked($isMine) style="margin-top:0.2rem;">
                                            <span>
                                                {{ $d->name }}
                                                @if ($otherHolder)
                                                    <span title="Currently with {{ $otherHolder->name }} — ticking will reassign." style="display:block; font-size:0.7rem; color:#b45309; font-weight:600;">⚠ with {{ $otherHolder->name }}</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        <div style="margin-top:0.75rem; display:flex; flex-wrap:wrap; gap:0.6rem; align-items:center;">
                            <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.5rem 1rem; border-radius:6px; font-weight:600; font-size:0.88rem; cursor:pointer;">
                                Save {{ $spoc->name }}'s districts
                            </button>
                            <span style="font-size:0.78rem; color:#64748b;">
                                Unticking a district that's currently this SPOC's = unassign. Ticking a district held by another SPOC = reassign.
                            </span>
                        </div>
                    </form>
                </details>
            @endforeach
        @endif
    </div>

    {{-- Panel: By district (existing grid) --}}
    <div id="panel-by-district" role="tabpanel" aria-labelledby="tab-by-district" hidden>
    <form method="post" action="{{ route('admin.service-spocs.update') }}">
        @csrf
        @method('PUT')

        @foreach ($hubs as $hub)
            <div style="background:#fff; border:1px solid #e4e4e7; border-radius:10px; padding:1rem 1.15rem; margin-bottom:1rem;">
                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem; margin-bottom:0.65rem;">
                    <strong style="font-size:1rem; color:#0f172a;">{{ $hub->name }} Hub</strong>
                    <span style="font-size:0.78rem; color:#71717a;">{{ $hub->districts->count() }} districts</span>
                </div>

                @if ($hub->districts->isEmpty())
                    <p style="margin:0; font-size:0.85rem; color:#71717a;">No districts under this hub.</p>
                @else
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                            <thead>
                                <tr style="text-align:left; background:#fafafa;">
                                    <th style="padding:0.45rem 0.65rem; border-bottom:1px solid #e4e4e7; width:40%;">District</th>
                                    <th style="padding:0.45rem 0.65rem; border-bottom:1px solid #e4e4e7; width:45%;">SPOC</th>
                                    <th style="padding:0.45rem 0.65rem; border-bottom:1px solid #e4e4e7;">Assigned</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($hub->districts as $d)
                                    @php
                                        $currentSpocId = $d->serviceSpoc?->state_staff_user_id;
                                        $currentSpocName = $d->serviceSpoc?->stateStaff?->name;
                                    @endphp
                                    <tr>
                                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">
                                            {{ $d->name }}
                                        </td>
                                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">
                                            <select name="assignments[{{ $d->id }}]" style="width:100%; max-width:22rem; padding:0.35rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px; background:#fff;">
                                                <option value="">— Unassigned —</option>
                                                @foreach ($spocs as $s)
                                                    <option value="{{ $s->id }}" @selected((int) $currentSpocId === (int) $s->id)>
                                                        {{ $s->name }}@if (isset($counts[$s->id])) ({{ $counts[$s->id] }} distr.)@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; font-size:0.78rem; color:#64748b;">
                                            @if ($d->serviceSpoc)
                                                {{ $d->serviceSpoc->assigned_at?->diffForHumans() ?? '—' }}
                                            @else
                                                <em style="color:#b45309;">No SPOC</em>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach

        <div style="position:sticky; bottom:0.5rem; background:rgba(255,255,255,0.92); backdrop-filter:blur(6px); border:1px solid #e4e4e7; border-radius:10px; padding:0.65rem 0.9rem; display:flex; gap:0.65rem; align-items:center; flex-wrap:wrap;">
            <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.55rem 1.1rem; border-radius:6px; font-weight:600; font-size:0.9rem; cursor:pointer;">
                Save assignments
            </button>
            <span style="font-size:0.8rem; color:#64748b;">
                Changes take effect immediately. In-flight cases that are already pending with the previous SPOC stay with them — new submissions route to the new SPOC.
            </span>
        </div>
    </form>
    </div> {{-- /panel-by-district --}}

    <script>
        (function () {
            const tabs = {
                'by-spoc': {
                    btn: document.getElementById('tab-by-spoc'),
                    panel: document.getElementById('panel-by-spoc'),
                },
                'by-district': {
                    btn: document.getElementById('tab-by-district'),
                    panel: document.getElementById('panel-by-district'),
                },
            };

            function switchTo(key) {
                if (!tabs[key]) return;
                Object.entries(tabs).forEach(([k, t]) => {
                    const active = k === key;
                    t.panel.hidden = !active;
                    t.btn.setAttribute('aria-selected', active ? 'true' : 'false');
                    t.btn.style.background = active ? '#fff' : '#fafafa';
                    t.btn.style.color = active ? '#18181b' : '#71717a';
                    t.btn.style.borderColor = active ? '#e4e4e7' : 'transparent';
                    t.btn.style.borderBottomColor = active ? '#fff' : 'transparent';
                });
                try { localStorage.setItem('spocs_tab', key); } catch (e) {}
            }

            window.__spocsSwitch = switchTo;

            // Restore last tab, or open By-SPOC if the URL focuses a SPOC.
            const url = new URL(window.location.href);
            if (url.searchParams.has('focus_spoc')) {
                switchTo('by-spoc');
            } else {
                try {
                    const saved = localStorage.getItem('spocs_tab');
                    if (saved) switchTo(saved);
                } catch (e) {}
            }
        })();
    </script>
@endsection

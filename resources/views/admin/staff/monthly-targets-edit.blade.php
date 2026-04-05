@extends('layouts.admin')

@section('title', $deliverable->name.' — '.$user->name)
@section('heading', 'Monthly targets — '.$deliverable->name.' — '.$user->name)

@section('content')
    <p style="font-size:0.9rem; color:#52525b;">Designation: <strong>{{ $user->designationRecord?->name ?? '—' }}</strong> · District: <strong>{{ $user->district?->name ?? '—' }}</strong> · Code: <strong>{{ $deliverable->code }}</strong></p>

    @if ($applyUrl)
        <p style="font-size:0.85rem; margin:0.5rem 0 1rem;">Applicant form URL (CFA only — share with this staff):<br>
            <input type="text" readonly value="{{ $applyUrl }}" onclick="this.select()" style="width:100%; max-width:36rem; margin-top:0.35rem; padding:0.4rem; font-size:0.8rem; border:1px solid #d4d4d8; border-radius:6px;">
        </p>
    @endif

    <p style="margin:0 0 1rem;">
        <a href="{{ route('admin.staff.monthly-targets.index', ['user' => $user, 'fiscal_year_id' => $fiscalYearId]) }}" style="font-size:0.88rem;">← All deliverables</a>
    </p>

    <form method="get" action="{{ route('admin.staff.monthly-targets.edit', ['user' => $user, 'deliverable' => $deliverable->code]) }}" style="margin-bottom:1rem; display:flex; flex-wrap:wrap; gap:0.5rem; align-items:flex-end;">
        <div>
            <label for="fy" style="display:block; font-size:0.8rem; font-weight:500; margin-bottom:0.25rem;">Fiscal year</label>
            <select id="fy" name="fiscal_year_id" onchange="this.form.submit()" style="padding:0.4rem 0.5rem; border-radius:6px; border:1px solid #d4d4d8; min-width:12rem;">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </div>
    </form>

    @if ($districtTarget === null)
        <p style="background:#fffbeb; border:1px solid #fcd34d; color:#92400e; padding:0.75rem; border-radius:6px; font-size:0.9rem;">
            District target for this deliverable is not set for this year. Set it under
            <a href="{{ route('admin.targets.district', ['fiscal_year_id' => $fiscalYearId, 'deliverable_id' => $deliverable->id]) }}">District targets</a>.
        </p>
    @else
        @php
            $slot = max(0, $districtTarget - $othersAnnualTotal);
        @endphp
        <div style="background:#fff; border:1px solid #e4e4e7; border-radius:8px; padding:0.85rem 1rem; margin-bottom:1rem; font-size:0.9rem; line-height:1.6;">
            <div><strong>District target (this deliverable):</strong> {{ number_format($districtTarget) }}</div>
            <div><strong>Other staff in this district (annual total, this deliverable):</strong> {{ number_format($othersAnnualTotal) }}</div>
            <div><strong>This staff’s share (12‑month sum to aim for when final):</strong> {{ number_format($slot) }}</div>
        </div>

        <form method="post" action="{{ route('admin.staff.monthly-targets.update', ['user' => $user, 'deliverable' => $deliverable->code]) }}" id="monthly-target-form" data-slot="{{ $slot }}">
            @csrf
            <input type="hidden" name="fiscal_year_id" value="{{ $fiscalYearId }}">

            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(7rem, 1fr)); gap:0.65rem; max-width:40rem;">
                @foreach (range(1, 12) as $m)
                    <div>
                        <label style="display:block; font-size:0.75rem; font-weight:500; margin-bottom:0.2rem;">M{{ $m }}</label>
                        <input type="number" name="months[{{ $m }}]" min="0" step="1" class="js-monthly-m"
                            value="{{ old('months.'.$m, $existing[$m] ?? 0) }}"
                            style="width:100%; padding:0.35rem; border:1px solid #d4d4d8; border-radius:6px;">
                    </div>
                @endforeach
            </div>
            <p style="margin-top:0.75rem; font-size:0.85rem;"><strong>Month sum (this staff):</strong> <span id="monthly-sum">0</span> · <strong>slot for this staff:</strong> {{ number_format($slot) }} <span style="color:#71717a;">(partial months OK — save anytime)</span></p>
            <p style="margin-top:1rem;">
                <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.55rem 1rem; border-radius:6px; font-weight:500;">Save months</button>
                <a href="{{ route('admin.staff.monthly-targets.index', ['user' => $user, 'fiscal_year_id' => $fiscalYearId]) }}" style="margin-left:0.75rem; font-size:0.9rem;">All deliverables</a>
                <a href="{{ route('admin.staff.index') }}" style="margin-left:0.75rem; font-size:0.9rem;">Staff list</a>
            </p>
        </form>

        @push('scripts')
            <script>
                (function () {
                    const form = document.getElementById('monthly-target-form');
                    if (!form) return;
                    const slot = parseInt(form.getAttribute('data-slot'), 10) || 0;
                    const sumEl = document.getElementById('monthly-sum');
                    function upd() {
                        let s = 0;
                        form.querySelectorAll('.js-monthly-m').forEach(function (el) {
                            const raw = String(el.value).trim();
                            const v = raw === '' ? 0 : parseInt(raw, 10);
                            s += Number.isFinite(v) && v >= 0 ? v : 0;
                        });
                        sumEl.textContent = s.toLocaleString('en-IN');
                        sumEl.style.color = s === slot ? '#047857' : '#b45309';
                    }
                    form.querySelectorAll('.js-monthly-m').forEach(function (el) {
                        el.addEventListener('input', upd);
                    });
                    upd();
                })();
            </script>
        @endpush
    @endif
@endsection

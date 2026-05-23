@extends('layouts.admin')

@section('title', 'Allocate staff targets by service')
@section('heading', 'Allocate staff targets by service')

@section('content')
    <p style="font-size:0.88rem; color:#52525b; margin:0 0 1rem; max-width:52rem; line-height:1.55;">
        Split a district’s target for one service/deliverable across staff by <strong>designation %</strong>.
        Staff within the same designation share equally. M1–M12 are filled with an equal monthly split.
        The existing per-staff <strong>M1–M12 (all MIS)</strong> screens remain for manual edits.
    </p>

    @if (session('status'))
        <p style="background:#f0fdf4; border:1px solid #86efac; color:#166534; padding:0.75rem; border-radius:8px; font-size:0.88rem; margin-bottom:1rem;">
            {{ session('status') }}
        </p>
    @endif

    @if ($errors->any())
        <div style="background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; padding:0.75rem; border-radius:8px; font-size:0.88rem; margin-bottom:1rem;">
            <ul style="margin:0.35rem 0 0 1.1rem; padding:0;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="get" action="{{ route('admin.targets.allocate-by-service') }}" style="margin-bottom:1.25rem; display:flex; flex-wrap:wrap; gap:0.65rem; align-items:flex-end; padding:1rem; background:#fff; border:1px solid #e4e4e7; border-radius:10px;">
        <div>
            <label for="fy" style="display:block; font-size:0.78rem; font-weight:600; margin-bottom:0.25rem;">Fiscal year</label>
            <select id="fy" name="fiscal_year_id" style="padding:0.45rem 0.55rem; border-radius:8px; border:1px solid #d4d4d8; min-width:11rem;">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="district_id" style="display:block; font-size:0.78rem; font-weight:600; margin-bottom:0.25rem;">District</label>
            <select id="district_id" name="district_id" style="padding:0.45rem 0.55rem; border-radius:8px; border:1px solid #d4d4d8; min-width:14rem;">
                <option value="">— Select district —</option>
                @foreach ($districts as $d)
                    <option value="{{ $d->id }}" @selected((int) $districtId === (int) $d->id)>{{ $d->hub?->name ?? 'Hub' }} · {{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="deliverable_id" style="display:block; font-size:0.78rem; font-weight:600; margin-bottom:0.25rem;">Service / deliverable</label>
            <select id="deliverable_id" name="deliverable_id" style="padding:0.45rem 0.55rem; border-radius:8px; border:1px solid #d4d4d8; min-width:18rem;">
                <option value="">— Select service —</option>
                @foreach ($deliverables as $d)
                    <option value="{{ $d->id }}" @selected((int) $deliverableId === (int) $d->id)>{{ $d->sort_order }}. {{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.5rem 0.85rem; border-radius:8px; font-weight:600; cursor:pointer;">Load</button>
    </form>

    @if ($districtId && $deliverableId && $deliverable)
        @if ($districtTarget === null)
            <p style="background:#fffbeb; border:1px solid #fcd34d; color:#92400e; padding:0.85rem; border-radius:8px; font-size:0.9rem;">
                District target for <strong>{{ $deliverable->name }}</strong> is not set for
                <strong>{{ $district?->name ?? 'this district' }}</strong>.
                Set it under <a href="{{ route('admin.targets.district', ['fiscal_year_id' => $fiscalYearId, 'deliverable_id' => $deliverable->id]) }}">District targets</a> first.
            </p>
        @elseif ($designationGroups === [])
            <p style="background:#fffbeb; border:1px solid #fcd34d; color:#92400e; padding:0.85rem; border-radius:8px; font-size:0.9rem;">
                No active district staff in <strong>{{ $district?->name }}</strong>.
            </p>
        @else
            <div style="background:linear-gradient(135deg,#f0fdfa,#eef2ff); border:1px solid #99f6e4; border-radius:10px; padding:0.9rem 1rem; margin-bottom:1rem; font-size:0.9rem;">
                <div><strong>District:</strong> {{ $district?->name }} · <strong>Service:</strong> {{ $deliverable->name }}</div>
                <div><strong>District target (annual):</strong> {{ number_format((int) $districtTarget) }}</div>
            </div>

            <form method="post" action="{{ route('admin.targets.allocate-by-service.apply') }}" id="allocate-form">
                @csrf
                <input type="hidden" name="fiscal_year_id" value="{{ $fiscalYearId }}">
                <input type="hidden" name="district_id" value="{{ $districtId }}">
                <input type="hidden" name="deliverable_id" value="{{ $deliverableId }}">

                <div style="overflow-x:auto; background:#fff; border:1px solid #e4e4e7; border-radius:10px; margin-bottom:1rem;">
                    <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                        <thead>
                            <tr style="background:#fafafa; text-align:left;">
                                <th style="padding:0.55rem 0.75rem; border-bottom:1px solid #e4e4e7;">Designation</th>
                                <th style="padding:0.55rem 0.75rem; border-bottom:1px solid #e4e4e7; width:6rem;">Staff</th>
                                <th style="padding:0.55rem 0.75rem; border-bottom:1px solid #e4e4e7;">Team members</th>
                                <th style="padding:0.55rem 0.75rem; border-bottom:1px solid #e4e4e7; width:8rem;">Share %</th>
                                <th style="padding:0.55rem 0.75rem; border-bottom:1px solid #e4e4e7; width:9rem;">Group total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($designationGroups as $group)
                                @php
                                    $key = $group['key'];
                                    $pct = (float) ($percentValues[$key] ?? 0);
                                    $groupTotal = (int) round(((int) $districtTarget) * $pct / 100);
                                @endphp
                                <tr>
                                    <td style="padding:0.5rem 0.75rem; border-bottom:1px solid #f4f4f5; font-weight:600;">{{ $group['designation_name'] }}</td>
                                    <td style="padding:0.5rem 0.75rem; border-bottom:1px solid #f4f4f5;">{{ $group['staff_count'] }}</td>
                                    <td style="padding:0.5rem 0.75rem; border-bottom:1px solid #f4f4f5; color:#475569; font-size:0.82rem;">
                                        {{ collect($group['staff'])->pluck('name')->join(', ') }}
                                    </td>
                                    <td style="padding:0.5rem 0.75rem; border-bottom:1px solid #f4f4f5;">
                                        <input type="number" name="percent[{{ $key }}]" min="0" max="100" step="0.1"
                                            value="{{ old('percent.'.$key, $percentValues[$key] ?? 0) }}"
                                            class="js-designation-pct" data-key="{{ $key }}"
                                            style="width:5rem; padding:0.35rem; border:1px solid #d4d4d8; border-radius:6px;">
                                    </td>
                                    <td style="padding:0.5rem 0.75rem; border-bottom:1px solid #f4f4f5;" class="js-group-total" data-key="{{ $key }}">
                                        {{ number_format($groupTotal) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p style="font-size:0.85rem; margin:0 0 1rem;">
                    <strong>Total %:</strong> <span id="pct-sum">0</span>
                    <span style="color:#64748b;"> (must equal 100%)</span>
                </p>

                @if ($previewRows !== [])
                    <div style="background:#fff; border:1px solid #e4e4e7; border-radius:10px; overflow:hidden; margin-bottom:1rem;">
                        <div style="padding:0.65rem 0.85rem; background:#f8fafc; border-bottom:1px solid #e4e4e7; font-weight:700; font-size:0.9rem;">
                            Preview — per staff (equal split within designation)
                        </div>
                        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                            <thead>
                                <tr style="text-align:left; background:#fff;">
                                    <th style="padding:0.5rem 0.75rem; border-bottom:1px solid #e4e4e7;">Staff</th>
                                    <th style="padding:0.5rem 0.75rem; border-bottom:1px solid #e4e4e7;">Designation</th>
                                    <th style="padding:0.5rem 0.75rem; border-bottom:1px solid #e4e4e7;">Annual target</th>
                                    <th style="padding:0.5rem 0.75rem; border-bottom:1px solid #e4e4e7;">M1–M12 (sample)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($previewRows as $row)
                                    <tr>
                                        <td style="padding:0.45rem 0.75rem; border-bottom:1px solid #f4f4f5;">{{ $row['user_name'] }}</td>
                                        <td style="padding:0.45rem 0.75rem; border-bottom:1px solid #f4f4f5; color:#64748b;">{{ $row['designation_name'] }}</td>
                                        <td style="padding:0.45rem 0.75rem; border-bottom:1px solid #f4f4f5; font-weight:600;">{{ number_format($row['annual_total']) }}</td>
                                        <td style="padding:0.45rem 0.75rem; border-bottom:1px solid #f4f4f5; color:#64748b; font-size:0.78rem;">
                                            @php $months = $row['months']; @endphp
                                            M1 {{ $months[1] ?? 0 }}, M2 {{ $months[2] ?? 0 }}, … M12 {{ $months[12] ?? 0 }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <button type="submit" style="background:#0f766e; color:#fff; border:none; padding:0.6rem 1.1rem; border-radius:8px; font-weight:700; cursor:pointer;">
                    Apply targets to staff (M1–M12)
                </button>
                <a href="{{ route('admin.staff.index') }}" style="margin-left:0.75rem; font-size:0.88rem;">District staff list</a>
            </form>
        @endif
    @endif

    @push('scripts')
        <script>
            (function () {
                const districtTarget = {{ (int) ($districtTarget ?? 0) }};
                const inputs = document.querySelectorAll('.js-designation-pct');
                const sumEl = document.getElementById('pct-sum');
                if (!inputs.length || !sumEl) return;

                function upd() {
                    let sum = 0;
                    inputs.forEach(function (el) {
                        const v = parseFloat(el.value);
                        const pct = Number.isFinite(v) ? v : 0;
                        sum += pct;
                        const key = el.getAttribute('data-key');
                        const totalCell = document.querySelector('.js-group-total[data-key="' + key + '"]');
                        if (totalCell && districtTarget > 0) {
                            totalCell.textContent = Math.round(districtTarget * pct / 100).toLocaleString('en-IN');
                        }
                    });
                    sumEl.textContent = sum.toFixed(1);
                    sumEl.style.color = Math.abs(sum - 100) < 0.05 ? '#047857' : '#b45309';
                }

                inputs.forEach(function (el) {
                    el.addEventListener('input', upd);
                });
                upd();
            })();
        </script>
    @endpush
@endsection

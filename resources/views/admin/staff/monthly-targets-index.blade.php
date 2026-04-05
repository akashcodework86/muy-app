@extends('layouts.admin')

@section('title', 'Monthly targets — '.$user->name)
@section('heading', 'MIS monthly targets — '.$user->name)

@section('content')
    <p style="font-size:0.9rem; color:#52525b;">Designation: <strong>{{ $user->designationRecord?->name ?? '—' }}</strong> · District: <strong>{{ $user->district?->name ?? '—' }}</strong></p>
    <p style="font-size:0.85rem; color:#71717a; margin:0.5rem 0 1rem;">Set M1–M12 for each deliverable. District row must exist (Admin → District targets). Partial saves are allowed on each deliverable screen.</p>

    <form method="get" action="{{ route('admin.staff.monthly-targets.index', $user) }}" style="margin-bottom:1.25rem; display:flex; flex-wrap:wrap; gap:0.5rem; align-items:flex-end;">
        <div>
            <label for="fy" style="display:block; font-size:0.8rem; font-weight:500; margin-bottom:0.25rem;">Fiscal year</label>
            <select id="fy" name="fiscal_year_id" onchange="this.form.submit()" style="padding:0.4rem 0.5rem; border-radius:6px; border:1px solid #d4d4d8; min-width:12rem;">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #e4e4e7; border-radius:8px; font-size:0.875rem;">
            <thead>
                <tr style="background:#fafafa; text-align:left;">
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7; width:3rem;">S.No</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Deliverable</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">MIS label</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7; width:10rem;">District target</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7; width:9rem;">M1–M12</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($deliverables as $d)
                    @php
                        $dt = $districtTargets[$d->id] ?? null;
                    @endphp
                    <tr>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $d->sort_order }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $d->name }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; color:#52525b; font-size:0.8rem;">{{ $d->mis_entry_label }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">
                            @if ($dt !== null)
                                {{ number_format((int) $dt) }}
                            @else
                                <span style="color:#b45309;">Not set</span>
                            @endif
                        </td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">
                            <a href="{{ route('admin.staff.monthly-targets.edit', ['user' => $user, 'deliverable' => $d->code, 'fiscal_year_id' => $fiscalYearId]) }}">Edit months</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p style="margin-top:1rem;">
        <a href="{{ route('admin.staff.index') }}" style="font-size:0.9rem;">← Back to staff</a>
    </p>
@endsection

@extends('layouts.admin')

@section('title', 'State targets')
@section('heading', 'State targets (all MIS deliverables)')

@section('content')
    @if (! $fiscalYearId || $fiscalYears->isEmpty())
        <p>No fiscal year found. Add one in the database first.</p>
    @else
        <form method="get" action="{{ route('admin.targets.state') }}" style="margin-bottom:1.25rem; display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center;">
            <label for="fy" style="font-size:0.9rem; font-weight:500;">Fiscal year</label>
            <select id="fy" name="fiscal_year_id" onchange="this.form.submit()" style="padding:0.4rem 0.5rem; border-radius:6px; border:1px solid #d4d4d8; min-width:12rem;">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }} ({{ $fy->code }})</option>
                @endforeach
            </select>
        </form>

        <form method="post" action="{{ route('admin.targets.state.update') }}">
            @csrf
            <input type="hidden" name="fiscal_year_id" value="{{ $fiscalYearId }}">

            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #e4e4e7; border-radius:8px; font-size:0.875rem;">
                    <thead>
                        <tr style="background:#fafafa; text-align:left;">
                            <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7; width:3rem;">S.No</th>
                            <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Deliverable</th>
                            <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">MIS entry label</th>
                            <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7; width:9rem;">State target</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliverables as $d)
                            <tr>
                                <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $d->sort_order }}</td>
                                <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $d->name }}</td>
                                <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; color:#52525b;">{{ $d->mis_entry_label }}</td>
                                <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">
                                    <input type="number" name="targets[{{ $d->id }}]" min="0" step="1"
                                        value="{{ old('targets.'.$d->id, $existing[$d->id] ?? 0) }}"
                                        style="width:100%; max-width:8rem; padding:0.35rem 0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p style="margin-top:1rem;">
                <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.55rem 1rem; border-radius:6px; font-weight:500;">Save state targets</button>
            </p>
        </form>

        <p style="margin-top:1.25rem; font-size:0.85rem; color:#71717a;">Next: split each deliverable across districts — <a href="{{ route('admin.targets.district', ['fiscal_year_id' => $fiscalYearId]) }}">District targets</a>.</p>
    @endif
@endsection

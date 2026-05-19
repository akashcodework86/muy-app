@extends('layouts.admin')

@section('title', 'Deliverables')
@section('heading', 'Deliverables')

@section('content')
    @php
        $fyName = $fiscalYear?->name ?? '—';
    @endphp

    <p style="font-size:0.88rem;color:#52525b;margin:0 0 1rem;max-width:52rem;">
        Official MIS indicator sequence (sections 1–12).
        <strong>Target</strong> = state target (FY {{ $fyName }}).
        <strong>Achievement</strong> = Phase 3 cases, CFA, onboarding, trainings, and field visits in this FY.
    </p>

    <form method="get" action="{{ route('admin.deliverables.index') }}" style="display:flex;flex-wrap:wrap;gap:0.65rem;align-items:flex-end;margin-bottom:1rem;background:#fff;border:1px solid #e4e4e7;border-radius:10px;padding:0.75rem 0.9rem;">
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label for="fiscal_year_id" style="font-size:0.75rem;font-weight:600;color:#475569;">Fiscal year</label>
            <select name="fiscal_year_id" id="fiscal_year_id" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;min-width:10rem;">
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.48rem 0.9rem;border-radius:8px;font-weight:600;cursor:pointer;">Apply</button>
        <a href="{{ route('admin.deliverables.export', ['fiscal_year_id' => $fiscalYearId]) }}" style="text-decoration:none;background:#065f46;color:#fff;padding:0.48rem 0.9rem;border-radius:8px;font-weight:600;font-size:0.88rem;">⬇ Export .xlsx</a>
        <a href="{{ route('admin.targets.state', ['fiscal_year_id' => $fiscalYearId]) }}" style="font-size:0.85rem;color:#4f46e5;margin-left:auto;">Edit state targets →</a>
    </form>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.86rem;background:#fff;">
            <thead>
                <tr>
                    <th style="padding:0.55rem 0.45rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:3.5rem;">S.N.</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:left;min-width:16rem;">Indicator</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:8rem;">Type of Indicator</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:8rem;">Spoke/ Hub/ State</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:6rem;">Targets</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:6rem;">Achievement</th>
                    <th style="padding:0.55rem 0.65rem;border:1px solid #1c1917;background:#9a3412;color:#fff;font-weight:700;text-align:center;min-width:6rem;">Achievement (%)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $isPillar = $row['row_type'] === 'pillar';
                        $isSubcategory = $row['row_type'] === 'subcategory';
                        $bg = $isPillar ? '#fce7f3' : '#fff';
                        $fontWeight = ($isPillar || $isSubcategory) ? '700' : '400';
                    @endphp
                    <tr>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;background:{{ $bg }};text-align:center;font-weight:{{ $fontWeight }};">{{ $row['serial'] }}</td>
                        <td style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;background:{{ $bg }};font-weight:{{ $fontWeight }};">{{ $row['name'] }}</td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;background:{{ $bg }};text-align:center;">{{ $row['indicator_type'] ?: '—' }}</td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;background:{{ $bg }};text-align:center;">{{ $row['level'] ?: '—' }}</td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;background:{{ $bg }};text-align:center;">{{ $row['target'] !== null ? number_format($row['target']) : '—' }}</td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;background:{{ $bg }};text-align:center;font-weight:{{ ($isPillar || $isSubcategory) ? '700' : '400' }};">{{ number_format($row['achievement']) }}</td>
                        <td style="padding:0.45rem;border:1px solid #d4d4d8;background:{{ $bg }};text-align:center;">{{ $row['achievement_pct'] !== null ? $row['achievement_pct'].'%' : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:1.25rem;text-align:center;color:#64748b;border:1px solid #d4d4d8;">No service categories or services found. Add them under Service catalog.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

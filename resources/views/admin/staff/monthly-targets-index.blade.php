@extends('layouts.admin')

@section('title', 'Monthly targets — '.$user->name)
@section('heading', 'MIS monthly targets — '.$user->name)

@section('content')
    <p style="font-size:0.9rem; color:#52525b;">Designation: <strong>{{ $user->designationRecord?->name ?? '—' }}</strong> · District: <strong>{{ $user->district?->name ?? '—' }}</strong></p>
    <p style="font-size:0.85rem; color:#71717a; margin:0.5rem 0 1rem;">Set M1–M12 for each deliverable. District row must exist (Admin → District targets). Partial saves are allowed on each deliverable screen.</p>
    <p style="font-size:0.82rem; color:#64748b; margin:-0.5rem 0 1rem; padding:0.5rem 0.65rem; background:#f8fafc; border-radius:6px; border:1px solid #e2e8f0;">
        <strong>Achieved (FY)</strong> dikhega jab is row par <strong>staff M1–12 sum</strong> ya <strong>district target</strong> set ho (0 bhi dikhega), ya CFA ho. Source: Phase 2 <code>admin/targets.php</code> jaisa — CFA (<code>submitted_by_name</code>), workshops, onboarding, BST sessions, market partners, services (<code>COALESCE(assigned_date, doc_date)</code>), ATF roll-up; <code>legacy_user_id</code> + <code>LEGACY_DB_*</code> zaroori. Fallback (bina iske): <code>legacy_phase2.php</code> mapping se sirf <code>rbi_services_assigned</code>. Unmapped: <code>php artisan report:legacy-rbi-services-unmapped --fy-code=2024-25</code>.
    </p>

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

    @if ($fiscalYear ?? null)
        <p style="font-size:0.8rem; color:#64748b; margin:-0.25rem 0 1rem;">
            <strong>Imported Phase 2 (legacy DB):</strong> poora historical achievement count hota hai (koi FY date filter nahi); monthly grid mein yeh total <strong>M1</strong> par dikhega.
            <strong>Naya phase:</strong> CFA referrals <code>cfa_submissions</code> isi FY (<strong>{{ \Illuminate\Support\Carbon::parse($fiscalYear->starts_on)->format('d M Y') }}</strong>–<strong>{{ \Illuminate\Support\Carbon::parse($fiscalYear->ends_on)->format('d M Y') }}</strong>) ke hisaab se count hote hain.
        </p>
    @endif

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #e4e4e7; border-radius:8px; font-size:0.875rem;">
            <thead>
                <tr style="background:#fafafa; text-align:left;">
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7; width:3rem;">S.No</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Deliverable</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">MIS label</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7; width:9rem;">District target</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7; width:9rem;">Staff target<br><span style="font-weight:400;font-size:0.72rem;color:#71717a;">(M1–12 sum)</span></th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7; width:9rem;">Achieved (FY)</th>
                    <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7; width:9rem;">M1–M12</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($deliverables as $d)
                    @php
                        $dt = $districtTargets[$d->id] ?? null;
                        $ach = $achievementByDeliverableId[$d->id] ?? null;
                        $staffSum = $ach ? (int) $ach['monthlySum'] : 0;
                        $achFy = $ach ? (int) $ach['achievementAnnual'] : 0;
                        $tracks = $ach && $ach['tracksAchievement'];
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
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ number_format($staffSum) }}</td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">
                            @if ($tracks)
                                <span style="font-weight:600;">{{ number_format($achFy) }}</span>
                                @if ($staffSum > 0)
                                    @php $pct = round(min(100, ($achFy / $staffSum) * 100)); @endphp
                                    <span style="color:#64748b;font-size:0.78rem;"> ({{ $pct }}% of staff M1–12)</span>
                                @endif
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </td>
                        <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">
                            <a href="{{ route('admin.staff.monthly-targets.edit', ['user' => $user, 'deliverable_code' => $d->code, 'fiscal_year_id' => $fiscalYearId]) }}">Edit months</a>
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

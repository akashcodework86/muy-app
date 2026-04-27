@extends('layouts.admin')

@section('title', 'Onboarding insight')
@section('heading', 'Onboarding insight')

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.85rem;">
        <div>
            <p style="margin:0;font-size:0.95rem;font-weight:700;color:#0f172a;">
                {{ $hub->name }} — district-wise onboarding progress
            </p>
            <p style="margin:0.22rem 0 0;font-size:0.82rem;color:#64748b;">
                Target source: {{ $activeFy?->name ?? 'Active FY not found' }} district onboarding allocation · Achievement source: locked onboarding batches (Phase 3 onwards).
            </p>
        </div>
        <a href="{{ route('dashboard') }}" style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.42rem 0.62rem;border-radius:8px;border:1px solid #cbd5e1;background:#fff;color:#0f172a;text-decoration:none;font-size:0.78rem;font-weight:700;">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to dashboard
        </a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.55rem;margin-bottom:0.75rem;">
        <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:11px;padding:0.65rem 0.7rem;">
            <div style="font-size:0.68rem;color:#4c1d95;text-transform:uppercase;font-weight:800;letter-spacing:0.06em;">Onboarding achieved / target</div>
            <div style="margin-top:0.2rem;font-size:1.05rem;font-weight:800;color:#0f172a;">{{ number_format($totalAchieved) }} / {{ number_format($totalTarget) }}</div>
        </div>
        <div style="background:#ecfeff;border:1px solid #a5f3fc;border-radius:11px;padding:0.65rem 0.7rem;">
            <div style="font-size:0.68rem;color:#0f766e;text-transform:uppercase;font-weight:800;letter-spacing:0.06em;">Overall progress</div>
            <div style="margin-top:0.2rem;font-size:1.05rem;font-weight:800;color:#0f172a;">
                @if (! is_null($overallProgressPct))
                    {{ $overallProgressPct }}%
                @else
                    —
                @endif
            </div>
        </div>
        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:11px;padding:0.65rem 0.7rem;">
            <div style="font-size:0.68rem;color:#9a3412;text-transform:uppercase;font-weight:800;letter-spacing:0.06em;">Gap remaining</div>
            <div style="margin-top:0.2rem;font-size:1.05rem;font-weight:800;color:#0f172a;">{{ number_format($totalGap) }}</div>
        </div>
        <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:11px;padding:0.65rem 0.7rem;">
            <div style="font-size:0.68rem;color:#334155;text-transform:uppercase;font-weight:800;letter-spacing:0.06em;">Coverage alerts</div>
            <div style="margin-top:0.2rem;font-size:0.86rem;font-weight:700;color:#0f172a;">
                {{ number_format($districtsWithoutTarget) }} districts without target · {{ number_format($districtsWithZeroAchieved) }} districts at 0 achieved
            </div>
        </div>
    </div>

    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:0.75rem 0.85rem;margin-bottom:0.8rem;">
        <div style="font-size:0.74rem;font-weight:800;color:#92400e;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.22rem;">Smart analysis</div>
        <p style="margin:0;font-size:0.84rem;color:#78350f;line-height:1.45;">
            @if ($totalTarget <= 0)
                Onboarding baseline is missing at hub level. First allocate district onboarding targets, then track execution from locked batches.
            @elseif ($overallProgressPct !== null && $overallProgressPct >= 100)
                Hub onboarding target is achieved. Consider refreshing district allocation to absorb additional demand.
            @elseif ($overallProgressPct !== null && $overallProgressPct >= 70)
                Hub is on-track with {{ $overallProgressPct }}% progress. Focus on the remaining {{ number_format($totalGap) }} gap, especially districts with zero achievement.
            @else
                Hub onboarding progress is {{ $overallProgressPct ?? 0 }}%, which indicates execution risk. Prioritize districts with high target but low achievement and unlock more batches.
            @endif
        </p>
    </div>

    <div style="overflow-x:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
        <table style="width:100%;border-collapse:collapse;min-width:980px;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="text-align:left;padding:0.64rem 0.75rem;border-bottom:1px solid #e5e7eb;font-size:0.72rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">District</th>
                    <th style="text-align:left;padding:0.64rem 0.75rem;border-bottom:1px solid #e5e7eb;font-size:0.72rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">Target</th>
                    <th style="text-align:left;padding:0.64rem 0.75rem;border-bottom:1px solid #e5e7eb;font-size:0.72rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">Achieved</th>
                    <th style="text-align:left;padding:0.64rem 0.75rem;border-bottom:1px solid #e5e7eb;font-size:0.72rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">Progress</th>
                    <th style="text-align:left;padding:0.64rem 0.75rem;border-bottom:1px solid #e5e7eb;font-size:0.72rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">Gap</th>
                    <th style="text-align:left;padding:0.64rem 0.75rem;border-bottom:1px solid #e5e7eb;font-size:0.72rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;">Smart analysis</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $progress = $row['progress_pct'];
                        $barPct = is_null($progress) ? 0 : min(100, max(0, (int) $progress));
                    @endphp
                    <tr>
                        <td style="padding:0.68rem 0.75rem;border-bottom:1px solid #f1f5f9;font-weight:700;color:#0f172a;">{{ $row['district_name'] }}</td>
                        <td style="padding:0.68rem 0.75rem;border-bottom:1px solid #f1f5f9;">{{ number_format((int) $row['target']) }}</td>
                        <td style="padding:0.68rem 0.75rem;border-bottom:1px solid #f1f5f9;font-weight:700;color:#0f766e;">{{ number_format((int) $row['achieved']) }}</td>
                        <td style="padding:0.68rem 0.75rem;border-bottom:1px solid #f1f5f9;">
                            @if (! is_null($progress))
                                <div style="display:flex;align-items:center;gap:0.5rem;">
                                    <div style="height:7px;flex:1;max-width:130px;border-radius:999px;background:#e2e8f0;border:1px solid #cbd5e1;overflow:hidden;">
                                        <div style="height:100%;width:{{ $barPct }}%;background:linear-gradient(90deg,#4f46e5,#14b8a6);"></div>
                                    </div>
                                    <strong style="font-size:0.8rem;color:#0f172a;">{{ $progress }}%</strong>
                                </div>
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </td>
                        <td style="padding:0.68rem 0.75rem;border-bottom:1px solid #f1f5f9;color:#7c2d12;font-weight:700;">{{ number_format((int) $row['gap']) }}</td>
                        <td style="padding:0.68rem 0.75rem;border-bottom:1px solid #f1f5f9;color:#334155;font-size:0.79rem;line-height:1.4;">
                            {{ $row['smart_analysis'] }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:1.25rem;text-align:center;color:#64748b;">
                            No district onboarding records found for this hub.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection


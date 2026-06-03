@php
    $participantRows = $participantRows ?? (method_exists($record ?? null, 'participantRows') ? $record->participantRows() : []);
    $participantRows = is_array($participantRows) ? array_values($participantRows) : [];
    $filledCount = collect($participantRows)->filter(fn ($p) => ! empty($p['name']))->count();
    $title = $title ?? 'Participant register';
@endphp

<div class="ws-reg-card">
    <div class="ws-reg-card__head">
        <h3 class="ws-reg-card__title">
            {{ $title }}
            <span class="ws-reg-card__meta">
                ({{ count($participantRows) }} rows@if ($filledCount > 0), {{ $filledCount }} named@endif)
            </span>
        </h3>
    </div>

    @if (count($participantRows) > 0)
        <div class="ws-reg-scroll">
            <table class="ws-reg-table">
                <thead>
                    <tr>
                        <th class="ws-reg-table__sr">#</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Gender</th>
                        <th>District</th>
                        <th>Block</th>
                        <th>Gram panchayat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($participantRows as $idx => $p)
                        <tr>
                            <td class="ws-reg-table__sr">{{ (int) ($p['sr'] ?? $idx + 1) }}</td>
                            <td>
                                @if (! empty($p['name']))
                                    <strong>{{ $p['name'] }}</strong>
                                @else
                                    <span class="ws-reg-muted">—</span>
                                @endif
                            </td>
                            <td class="ws-reg-mono">{{ ! empty($p['mobile']) ? $p['mobile'] : '—' }}</td>
                            <td>
                                @if (($p['gender'] ?? '') === 'M')
                                    <span class="ws-reg-gender ws-reg-gender--m">M</span>
                                @elseif (($p['gender'] ?? '') === 'F')
                                    <span class="ws-reg-gender ws-reg-gender--f">F</span>
                                @else
                                    <span class="ws-reg-muted">—</span>
                                @endif
                            </td>
                            <td>{{ ! empty($p['district_name']) ? $p['district_name'] : '—' }}</td>
                            <td>{{ ! empty($p['block_name']) ? $p['block_name'] : '—' }}</td>
                            <td>{{ ! empty($p['gram_panchayat_name']) ? $p['gram_panchayat_name'] : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="ws-reg-foot-label">Totals</td>
                        <td>
                            @php
                                $mCount = collect($participantRows)->filter(fn ($p) => ($p['gender'] ?? '') === 'M')->count();
                                $fCount = collect($participantRows)->filter(fn ($p) => ($p['gender'] ?? '') === 'F')->count();
                            @endphp
                            @if ($mCount > 0)
                                <span class="ws-reg-gender ws-reg-gender--m">{{ $mCount }} M</span>
                            @endif
                            @if ($fCount > 0)
                                <span class="ws-reg-gender ws-reg-gender--f">{{ $fCount }} F</span>
                            @endif
                            @if ($mCount === 0 && $fCount === 0)
                                <span class="ws-reg-muted">—</span>
                            @endif
                        </td>
                        <td colspan="3" class="ws-reg-foot-meta">{{ count($participantRows) }} rows total</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @else
        <p class="ws-reg-empty">No participant rows recorded for this session.</p>
    @endif
</div>

@once
    @push('styles')
    <style>
        .ws-reg-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; }
        .ws-reg-card__head { padding:1rem 1.25rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
        .ws-reg-card__title { margin:0; font-size:0.98rem; font-weight:800; color:#0f172a; }
        .ws-reg-card__meta { font-weight:500; color:#64748b; font-size:0.82rem; }
        .ws-reg-scroll { overflow-x:auto; }
        .ws-reg-table { width:100%; border-collapse:collapse; font-size:0.84rem; min-width:720px; }
        .ws-reg-table th { padding:0.6rem 0.65rem; text-align:left; font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; background:#f1f5f9; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
        .ws-reg-table td { padding:0.55rem 0.65rem; border-bottom:1px solid #f1f5f9; vertical-align:middle; color:#334155; }
        .ws-reg-table tfoot td { background:#f8fafc; border-top:2px solid #e2e8f0; font-size:0.78rem; }
        .ws-reg-table__sr { width:2.5rem; text-align:center; font-weight:700; color:#64748b; }
        .ws-reg-mono { font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:0.82rem; }
        .ws-reg-muted { color:#94a3b8; font-size:0.82rem; }
        .ws-reg-gender { display:inline-flex; align-items:center; justify-content:center; min-width:1.6rem; padding:0.15rem 0.4rem; border-radius:6px; font-size:0.72rem; font-weight:800; }
        .ws-reg-gender--m { background:#eef2ff; color:#3730a3; }
        .ws-reg-gender--f { background:#fdf2f8; color:#be185d; }
        .ws-reg-foot-label { font-weight:700; color:#64748b; }
        .ws-reg-foot-meta { color:#64748b; }
        .ws-reg-empty { margin:0; padding:1.25rem; color:#64748b; font-size:0.88rem; }
    </style>
    @endpush
@endonce

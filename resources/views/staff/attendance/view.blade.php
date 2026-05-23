@extends('layouts.admin')

@section('title', 'Block Level Workshops — District View')
@section('heading', 'Block Level Workshop Dashboard')

@push('styles')
<style>
    :root {
        --bw-indigo: #4f46e5;
        --bw-teal:   #0d9488;
        --bw-rose:   #e11d48;
        --bw-amber:  #d97706;
        --bw-text:   #0f172a;
        --bw-muted:  #64748b;
        --bw-ink:    #334155;
        --bw-border: #e2e8f0;
        --bw-bg:     #f8fafc;
    }

    .bw-shell { display:flex;flex-direction:column;gap:1.25rem;padding-bottom:3rem;font-family:'DM Sans',sans-serif;width:100%; }

    /* Banner */
    .bw-banner { background:linear-gradient(135deg,rgba(79,70,229,0.07),rgba(13,148,136,0.06));border:1px solid rgba(79,70,229,0.15);border-radius:16px;padding:1rem 1.3rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap; }
    .bw-banner__left { display:flex;align-items:center;gap:0.9rem; }
    .bw-banner__icon { width:2.8rem;height:2.8rem;background:linear-gradient(135deg,var(--bw-indigo),#7c3aed);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;flex-shrink:0;box-shadow:0 4px 12px rgba(79,70,229,0.28); }
    .bw-banner__title { margin:0 0 0.15rem;font-size:1rem;font-weight:800;color:var(--bw-text); }
    .bw-banner__sub   { margin:0;font-size:0.8rem;color:var(--bw-muted); }
    .bw-banner__btn   { display:inline-flex;align-items:center;gap:0.35rem;padding:0.5rem 0.95rem;background:var(--bw-indigo);color:#fff;border-radius:9px;font-size:0.83rem;font-weight:700;text-decoration:none; }
    .bw-banner__btn:hover { opacity:0.88; }

    /* Stat chips */
    .bw-chips { display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.7rem; }
    .bw-chip { background:#fff;border:1px solid var(--bw-border);border-radius:14px;padding:0.8rem 1rem;box-shadow:0 3px 12px rgba(15,23,42,0.04);display:flex;align-items:center;gap:0.65rem; }
    .bw-chip__icon { width:2.1rem;height:2.1rem;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.9rem;flex-shrink:0; }
    .bw-chip__icon--indigo { background:#eef2ff;color:var(--bw-indigo); }
    .bw-chip__icon--teal   { background:#ccfbf1;color:#0f766e; }
    .bw-chip__icon--blue   { background:#dbeafe;color:#1d4ed8; }
    .bw-chip__icon--rose   { background:#ffe4e6;color:var(--bw-rose); }
    .bw-chip__label { font-size:0.67rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--bw-muted);font-weight:700; }
    .bw-chip__value { font-size:1.2rem;font-weight:800;color:var(--bw-text); }

    /* Filter bar */
    .bw-filter { background:#fff;border:1px solid var(--bw-border);border-radius:14px;padding:0.85rem 1.1rem;box-shadow:0 2px 10px rgba(15,23,42,0.04); }
    .bw-filter__row { display:flex;flex-wrap:wrap;gap:0.5rem;align-items:flex-end; }
    .bw-filter__field { display:flex;flex-direction:column;gap:0.22rem;flex:1;min-width:130px; }
    .bw-filter__label { font-size:0.71rem;font-weight:700;color:var(--bw-ink); }
    .bw-input { padding:0.48rem 0.65rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;color:var(--bw-text);background:#fff;width:100%;font-family:inherit; }
    .bw-input:focus { outline:none;border-color:var(--bw-indigo);box-shadow:0 0 0 3px rgba(79,70,229,0.1); }
    .bw-btn { display:inline-flex;align-items:center;gap:0.35rem;padding:0.5rem 0.9rem;background:var(--bw-indigo);color:#fff;border:none;border-radius:8px;font-size:0.84rem;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;white-space:nowrap; }
    .bw-btn:hover { opacity:0.88; }
    .bw-btn--ghost { background:transparent;color:var(--bw-indigo);border:1px solid var(--bw-indigo); }
    .bw-btn--teal  { background:linear-gradient(135deg,var(--bw-teal),#0f766e); }
    .bw-btn--sm { padding:0.38rem 0.7rem;font-size:0.78rem; }

    /* Table card */
    .bw-card { background:#fff;border:1px solid var(--bw-border);border-radius:16px;box-shadow:0 4px 18px rgba(15,23,42,0.05);overflow:hidden; }
    .bw-card__head { padding:0.85rem 1.2rem;border-bottom:1px solid var(--bw-border);display:flex;align-items:center;gap:0.55rem;flex-wrap:wrap; }
    .bw-card__head-icon { width:1.8rem;height:1.8rem;background:linear-gradient(135deg,#ccfbf1,#a7f3d0);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--bw-teal);font-size:0.82rem;flex-shrink:0; }
    .bw-card__title { margin:0;font-size:0.92rem;font-weight:700;color:var(--bw-text); }
    .bw-card__count { margin-left:auto;font-size:0.78rem;color:var(--bw-muted); }

    /* Table */
    .bw-table { width:100%;border-collapse:collapse;font-size:0.845rem;min-width:900px; }
    .bw-table thead tr { background:var(--bw-bg); }
    .bw-table th { padding:0.6rem 0.9rem;text-align:left;font-size:0.67rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--bw-muted);border-bottom:1px solid var(--bw-border);white-space:nowrap; }
    .bw-table td { padding:0.7rem 0.9rem;color:var(--bw-ink);border-bottom:1px solid #f1f5f9;vertical-align:middle; }
    .bw-table tbody tr:last-child td { border-bottom:none; }
    .bw-table tbody tr:hover td { background:#fafafe; }

    /* Badges + pills */
    .bw-date-badge { display:inline-block;padding:0.2rem 0.55rem;background:#eef2ff;color:var(--bw-indigo);border:1px solid #c7d2fe;border-radius:8px;font-size:0.77rem;font-weight:700;white-space:nowrap; }
    .bw-pill { display:inline-block;padding:0.14rem 0.48rem;border-radius:999px;font-size:0.7rem;font-weight:700; }
    .bw-pill--teal  { background:#ccfbf1;color:#0f766e; }
    .bw-pill--rose  { background:#ffe4e6;color:var(--bw-rose); }
    .bw-pill--muted { background:#f1f5f9;color:var(--bw-muted); }
    .bw-pill--indigo { background:#eef2ff;color:var(--bw-indigo); }

    /* location cell */
    .bw-loc-main  { font-weight:600;color:var(--bw-text); }
    .bw-loc-sub   { font-size:0.75rem;color:var(--bw-muted);margin-top:0.1rem; }

    /* participant count cell */
    .bw-p-total  { font-weight:800;font-size:0.95rem;color:var(--bw-text); }
    .bw-p-split  { font-size:0.73rem;color:var(--bw-muted);margin-top:0.12rem; }

    /* gram panchayat breakdown */
    .bw-gp-primary { font-size:0.82rem;font-weight:700;color:var(--bw-text);margin-bottom:0.35rem; }
    .bw-gp-list { display:flex;flex-direction:column;gap:0.28rem;margin:0;padding:0;list-style:none; }
    .bw-gp-item { display:flex;align-items:center;justify-content:space-between;gap:0.5rem;font-size:0.76rem;line-height:1.3; }
    .bw-gp-item__name { color:var(--bw-ink);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:11rem; }
    .bw-gp-item__count { flex-shrink:0;font-weight:800;color:var(--bw-teal);background:#ccfbf1;padding:0.1rem 0.4rem;border-radius:6px;font-size:0.7rem; }

    /* photo thumbs in row */
    .bw-photo-row { display:flex;align-items:center;gap:0.3rem;flex-wrap:wrap; }
    .bw-photo-thumb { width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid var(--bw-border);display:block;flex-shrink:0; }
    .bw-photo-thumb-link { display:block;line-height:0;border-radius:6px;overflow:hidden; }
    .bw-photo-thumb-link:hover { box-shadow:0 2px 8px rgba(79,70,229,0.25); }
    .bw-photo-more { font-size:0.68rem;font-weight:700;color:var(--bw-muted);padding:0.15rem 0.35rem;background:#f1f5f9;border-radius:6px;white-space:nowrap; }

    /* empty */
    .bw-empty { padding:3rem 1rem;text-align:center;color:var(--bw-muted);font-size:0.88rem; }
    .bw-empty i { display:block;font-size:2.4rem;color:#c7d2fe;margin-bottom:0.55rem; }
    .bw-empty a { display:inline-flex;align-items:center;gap:0.35rem;margin-top:0.65rem;padding:0.48rem 0.85rem;background:var(--bw-indigo);color:#fff;border-radius:9px;font-size:0.83rem;font-weight:700;text-decoration:none; }
</style>
@endpush

@section('content')
<div class="bw-shell">

    @if (!empty($migrationMissing))
        <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;">
            <strong>Attendance table not found.</strong> Run <code>php artisan migrate</code> to activate this module.
        </div>
    @endif

    {{-- Banner --}}
    <div class="bw-banner">
        <div class="bw-banner__left">
            <div class="bw-banner__icon"><i class="fa-solid fa-people-group"></i></div>
            <div>
                <p class="bw-banner__title">Block Level Workshop Records</p>
                <p class="bw-banner__sub">{{ $user->name }} &middot; <strong>{{ $user->district?->name ?? 'No district' }}</strong></p>
            </div>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <a href="{{ route('staff.attendance.index') }}" class="bw-banner__btn">
                <i class="fa-solid fa-plus"></i> New workshop
            </a>
        </div>
    </div>

    {{-- Summary stat chips --}}
    @if (empty($migrationMissing))
    <div class="bw-chips">
        <div class="bw-chip">
            <div class="bw-chip__icon bw-chip__icon--indigo"><i class="fa-solid fa-calendar-check"></i></div>
            <div>
                <div class="bw-chip__label">Workshops</div>
                <div class="bw-chip__value">{{ number_format($totalWorkshops) }}</div>
            </div>
        </div>
        <div class="bw-chip">
            <div class="bw-chip__icon bw-chip__icon--teal"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="bw-chip__label">Total participants</div>
                <div class="bw-chip__value">{{ number_format($totalParticipants) }}</div>
            </div>
        </div>
        <div class="bw-chip">
            <div class="bw-chip__icon bw-chip__icon--blue"><i class="fa-solid fa-person"></i></div>
            <div>
                <div class="bw-chip__label">Male</div>
                <div class="bw-chip__value">{{ number_format($totalMale) }}</div>
            </div>
        </div>
        <div class="bw-chip">
            <div class="bw-chip__icon bw-chip__icon--rose"><i class="fa-solid fa-person-dress"></i></div>
            <div>
                <div class="bw-chip__label">Female</div>
                <div class="bw-chip__value">{{ number_format($totalFemale) }}</div>
            </div>
        </div>
    </div>
    @endif

    {{-- Filter bar --}}
    @if (empty($migrationMissing))
    <div class="bw-filter">
        <form method="get" action="{{ route('staff.attendance.view') }}">
            <div class="bw-filter__row">
                <div class="bw-filter__field" style="max-width:150px;">
                    <label class="bw-filter__label">From date</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="bw-input">
                </div>
                <div class="bw-filter__field" style="max-width:150px;">
                    <label class="bw-filter__label">To date</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="bw-input">
                </div>
                @if (!empty($blockOptions))
                <div class="bw-filter__field" style="max-width:185px;">
                    <label class="bw-filter__label">Block</label>
                    <select name="block" class="bw-input">
                        <option value="">— All blocks —</option>
                        @foreach ($blockOptions as $b)
                            <option value="{{ $b }}" @selected(request('block') === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                @if (!empty($coordinatorOptions))
                <div class="bw-filter__field" style="max-width:200px;">
                    <label class="bw-filter__label">Submitted by</label>
                    <select name="coordinator_id" class="bw-input">
                        <option value="">— All staff —</option>
                        @foreach ($coordinatorOptions as $co)
                            <option value="{{ $co['id'] }}" @selected((int) request('coordinator_id') === (int) $co['id'])>{{ $co['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div style="display:flex;gap:0.4rem;align-items:flex-end;">
                    <button type="submit" class="bw-btn"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('staff.attendance.view') }}" class="bw-btn bw-btn--ghost">Reset</a>
                </div>
            </div>
        </form>
    </div>
    @endif

    {{-- Records table --}}
    @if (empty($migrationMissing))
    <div class="bw-card">
        <div class="bw-card__head">
            <div class="bw-card__head-icon"><i class="fa-solid fa-table-list"></i></div>
            <h3 class="bw-card__title">Workshop records</h3>
            <span class="bw-card__count">{{ number_format($reports->total()) }} total &middot; page {{ $reports->currentPage() }} of {{ $reports->lastPage() }}</span>
        </div>

        <div style="overflow-x:auto;">
            <table class="bw-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Submitted by</th>
                        <th>Location</th>
                        <th>Gram panchayat (participants)</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Total</th>
                        <th>Participants</th>
                        <th>Photos</th>
                        <th>Sheet</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $i => $row)
                        @php
                            $rowNum = ($reports->currentPage() - 1) * $reports->perPage() + $i + 1;
                            $media = $row->visitMediaItems();
                            $participantRows = $row->participantRows();
                            $filledRows = collect($participantRows)->filter(fn ($p) => ! empty($p['name']))->count();
                            $gpCounts = $row->participantCountsByGramPanchayat();
                        @endphp
                        <tr>
                            <td style="color:var(--bw-muted);font-size:0.77rem;font-weight:600;">{{ $rowNum }}</td>

                            <td>
                                <span class="bw-date-badge">{{ $row->visit_date?->format('d M Y') }}</span>
                            </td>

                            <td>
                                <div style="font-size:0.82rem;font-weight:600;color:var(--bw-text);">{{ $row->field_coordinator_name ?: '—' }}</div>
                            </td>

                            <td>
                                @if ($row->area || $row->block)
                                    @if ($row->area)
                                        <div class="bw-loc-main">{{ $row->area }}</div>
                                    @endif
                                    @if ($row->block)
                                        <div class="bw-loc-sub"><i class="fa-solid fa-grip-lines-vertical" style="font-size:0.6rem;margin-right:2px;"></i>{{ $row->block }}</div>
                                    @endif
                                    @if ($row->district?->name)
                                        <span class="bw-pill bw-pill--teal" style="margin-top:0.2rem;display:inline-block;">
                                            <i class="fa-solid fa-location-dot" style="font-size:0.6rem;"></i> {{ $row->district->name }}
                                        </span>
                                    @endif
                                @else
                                    <span style="color:var(--bw-muted);">—</span>
                                @endif
                            </td>

                            <td style="font-size:0.82rem;min-width:10rem;">
                                @if ($gpCounts !== [])
                                    @if ($row->gramPanchayat?->name)
                                        <div class="bw-gp-primary" title="Workshop gram panchayat">
                                            <i class="fa-solid fa-location-dot" style="font-size:0.65rem;color:var(--bw-teal);"></i>
                                            {{ $row->gramPanchayat->name }}
                                        </div>
                                    @endif
                                    <ul class="bw-gp-list">
                                        @foreach ($gpCounts as $gp)
                                            <li class="bw-gp-item" title="{{ $gp['name'] }}">
                                                <span class="bw-gp-item__name">{{ $gp['name'] }}</span>
                                                <span class="bw-gp-item__count">{{ number_format($gp['count']) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @elseif ($row->gramPanchayat?->name)
                                    <div class="bw-gp-primary">{{ $row->gramPanchayat->name }}</div>
                                    <span style="font-size:0.75rem;color:var(--bw-muted);">No participant rows</span>
                                @else
                                    <span style="color:var(--bw-muted);">—</span>
                                @endif
                            </td>

                            <td>
                                @if ((int) $row->participants_male_count > 0)
                                    <span class="bw-pill bw-pill--indigo">{{ number_format((int) $row->participants_male_count) }}</span>
                                @else
                                    <span style="color:var(--bw-muted);">0</span>
                                @endif
                            </td>

                            <td>
                                @if ((int) $row->participants_female_count > 0)
                                    <span class="bw-pill bw-pill--rose">{{ number_format((int) $row->participants_female_count) }}</span>
                                @else
                                    <span style="color:var(--bw-muted);">0</span>
                                @endif
                            </td>

                            <td>
                                <span class="bw-p-total">{{ number_format((int) $row->participants_total) }}</span>
                            </td>

                            <td>
                                @if (count($participantRows) > 0)
                                    <span style="font-size:0.8rem;color:var(--bw-teal);font-weight:700;">
                                        {{ $filledRows }}/{{ count($participantRows) }} filled
                                    </span>
                                @else
                                    <span class="bw-pill bw-pill--muted">No rows</span>
                                @endif
                            </td>

                            <td style="min-width:8rem;">
                                @if (count($media) > 0)
                                    <div class="bw-photo-row">
                                        @foreach (array_slice($media, 0, 4) as $idx => $item)
                                            <a href="{{ route('staff.attendance.attachment', ['attendanceReport' => $row, 'index' => $idx, 'inline' => 1]) }}"
                                               target="_blank" rel="noopener"
                                               class="bw-photo-thumb-link"
                                               title="Photo {{ $idx + 1 }}">
                                                <img class="bw-photo-thumb"
                                                     src="{{ route('staff.attendance.attachment', ['attendanceReport' => $row, 'index' => $idx, 'inline' => 1]) }}"
                                                     alt="Photo {{ $idx + 1 }}"
                                                     loading="lazy">
                                            </a>
                                        @endforeach
                                        @if (count($media) > 4)
                                            <span class="bw-photo-more">+{{ count($media) - 4 }}</span>
                                        @endif
                                    </div>
                                @elseif ($row->attachment_path)
                                    <a href="{{ route('staff.attendance.attachment', $row) }}" class="bw-btn bw-btn--sm bw-btn--ghost" style="padding:0.3rem 0.55rem;font-size:0.72rem;">
                                        <i class="fa-solid fa-image"></i> 1 file
                                    </a>
                                @else
                                    <span style="color:var(--bw-muted);font-size:0.8rem;">—</span>
                                @endif
                            </td>

                            <td>
                                @if ($row->hasAttendanceSheet())
                                    <a href="{{ route('staff.attendance.sheet.download', $row) }}" class="bw-pill bw-pill--teal" style="text-decoration:none;">
                                        <i class="fa-solid fa-file-excel"></i> Sheet
                                    </a>
                                @else
                                    <span style="color:var(--bw-muted);font-size:0.8rem;">—</span>
                                @endif
                            </td>

                            <td style="white-space:nowrap;">
                                <a href="{{ route('staff.attendance.show', $row) }}" class="bw-btn bw-btn--sm bw-btn--teal" style="text-decoration:none;">
                                    <i class="fa-solid fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12">
                                <div class="bw-empty">
                                    <i class="fa-regular fa-folder-open"></i>
                                    No workshops found for this district.
                                    <br>
                                    <a href="{{ route('staff.attendance.index') }}">
                                        <i class="fa-solid fa-plus"></i> Submit first workshop
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($reports instanceof \Illuminate\Contracts\Pagination\Paginator && $reports->hasPages())
        <div>{{ $reports->links() }}</div>
    @endif
    @endif

</div>
@endsection

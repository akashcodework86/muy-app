@extends('layouts.admin')

@section('title', 'Block Level Workshops — State Admin')
@section('heading', 'Block Level Workshop Dashboard')

@push('styles')
<style>
    :root {
        --bwa-indigo: #4f46e5;
        --bwa-teal:   #0d9488;
        --bwa-rose:   #e11d48;
        --bwa-amber:  #d97706;
        --bwa-text:   #0f172a;
        --bwa-muted:  #64748b;
        --bwa-ink:    #334155;
        --bwa-border: #e2e8f0;
        --bwa-bg:     #f8fafc;
    }

    .bwa-shell { display:flex;flex-direction:column;gap:1.25rem;padding-bottom:3rem;font-family:'DM Sans',sans-serif;width:100%; }

    /* Banner */
    .bwa-banner { background:linear-gradient(135deg,rgba(79,70,229,0.07),rgba(13,148,136,0.06));border:1px solid rgba(79,70,229,0.15);border-radius:16px;padding:1rem 1.3rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap; }
    .bwa-banner__left { display:flex;align-items:center;gap:0.9rem; }
    .bwa-banner__icon { width:2.8rem;height:2.8rem;background:linear-gradient(135deg,var(--bwa-indigo),#7c3aed);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;flex-shrink:0;box-shadow:0 4px 12px rgba(79,70,229,0.28); }
    .bwa-banner__title { margin:0 0 0.15rem;font-size:1rem;font-weight:800;color:var(--bwa-text); }
    .bwa-banner__sub   { margin:0;font-size:0.8rem;color:var(--bwa-muted); }
    .bwa-banner__btn   { display:inline-flex;align-items:center;gap:0.35rem;padding:0.5rem 0.95rem;background:var(--bwa-indigo);color:#fff;border-radius:9px;font-size:0.83rem;font-weight:700;text-decoration:none; }
    .bwa-banner__btn:hover { opacity:0.88; }

    /* Stat chips */
    .bwa-chips { display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.7rem; }
    .bwa-chip { background:#fff;border:1px solid var(--bwa-border);border-radius:14px;padding:0.8rem 1rem;box-shadow:0 3px 12px rgba(15,23,42,0.04);display:flex;align-items:center;gap:0.65rem; }
    .bwa-chip__icon { width:2.1rem;height:2.1rem;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.9rem;flex-shrink:0; }
    .bwa-chip__icon--indigo { background:#eef2ff;color:var(--bwa-indigo); }
    .bwa-chip__icon--teal   { background:#ccfbf1;color:#0f766e; }
    .bwa-chip__icon--blue   { background:#dbeafe;color:#1d4ed8; }
    .bwa-chip__icon--rose   { background:#ffe4e6;color:var(--bwa-rose); }
    .bwa-chip__label { font-size:0.67rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--bwa-muted);font-weight:700; }
    .bwa-chip__value { font-size:1.2rem;font-weight:800;color:var(--bwa-text); }

    /* Filter bar */
    .bwa-filter { background:#fff;border:1px solid var(--bwa-border);border-radius:14px;padding:0.85rem 1.1rem;box-shadow:0 2px 10px rgba(15,23,42,0.04); }
    .bwa-filter__row { display:flex;flex-wrap:wrap;gap:0.5rem;align-items:flex-end; }
    .bwa-filter__field { display:flex;flex-direction:column;gap:0.22rem;flex:1;min-width:130px; }
    .bwa-filter__label { font-size:0.71rem;font-weight:700;color:var(--bwa-ink); }
    .bwa-input { padding:0.48rem 0.65rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem;color:var(--bwa-text);background:#fff;width:100%;font-family:inherit; }
    .bwa-input:focus { outline:none;border-color:var(--bwa-indigo);box-shadow:0 0 0 3px rgba(79,70,229,0.1); }
    .bwa-btn { display:inline-flex;align-items:center;gap:0.35rem;padding:0.5rem 0.9rem;background:var(--bwa-indigo);color:#fff;border:none;border-radius:8px;font-size:0.84rem;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;white-space:nowrap; }
    .bwa-btn:hover { opacity:0.88; }
    .bwa-btn--ghost { background:transparent;color:var(--bwa-indigo);border:1px solid var(--bwa-indigo); }
    .bwa-btn--teal  { background:linear-gradient(135deg,var(--bwa-teal),#0f766e); }
    .bwa-btn--sm { padding:0.38rem 0.7rem;font-size:0.78rem; }

    /* Table card */
    .bwa-card { background:#fff;border:1px solid var(--bwa-border);border-radius:16px;box-shadow:0 4px 18px rgba(15,23,42,0.05);overflow:hidden; }
    .bwa-card__head { padding:0.85rem 1.2rem;border-bottom:1px solid var(--bwa-border);display:flex;align-items:center;gap:0.55rem;flex-wrap:wrap; }
    .bwa-card__head-icon { width:1.8rem;height:1.8rem;background:linear-gradient(135deg,#ccfbf1,#a7f3d0);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--bwa-teal);font-size:0.82rem;flex-shrink:0; }
    .bwa-card__title { margin:0;font-size:0.92rem;font-weight:700;color:var(--bwa-text); }
    .bwa-card__count { margin-left:auto;font-size:0.78rem;color:var(--bwa-muted); }

    /* Table */
    .bwa-table { width:100%;border-collapse:collapse;font-size:0.845rem;min-width:950px; }
    .bwa-table thead tr { background:var(--bwa-bg); }
    .bwa-table th { padding:0.6rem 0.9rem;text-align:left;font-size:0.67rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--bwa-muted);border-bottom:1px solid var(--bwa-border);white-space:nowrap; }
    .bwa-table td { padding:0.7rem 0.9rem;color:var(--bwa-ink);border-bottom:1px solid #f1f5f9;vertical-align:middle; }
    .bwa-table tbody tr:last-child td { border-bottom:none; }
    .bwa-table tbody tr:hover td { background:#fafafe; }

    /* Badges + pills */
    .bwa-date-badge { display:inline-block;padding:0.2rem 0.55rem;background:#eef2ff;color:var(--bwa-indigo);border:1px solid #c7d2fe;border-radius:8px;font-size:0.77rem;font-weight:700;white-space:nowrap; }
    .bwa-pill { display:inline-block;padding:0.14rem 0.48rem;border-radius:999px;font-size:0.7rem;font-weight:700; }
    .bwa-pill--teal   { background:#ccfbf1;color:#0f766e; }
    .bwa-pill--rose   { background:#ffe4e6;color:var(--bwa-rose); }
    .bwa-pill--muted  { background:#f1f5f9;color:var(--bwa-muted); }
    .bwa-pill--indigo { background:#eef2ff;color:var(--bwa-indigo); }
    .bwa-pill--amber  { background:#fef3c7;color:var(--bwa-amber); }

    /* location cell */
    .bwa-loc-main { font-weight:600;color:var(--bwa-text); }
    .bwa-loc-sub  { font-size:0.75rem;color:var(--bwa-muted);margin-top:0.1rem; }

    /* gram panchayat breakdown */
    .bwa-gp-primary { font-size:0.82rem;font-weight:700;color:var(--bwa-text);margin-bottom:0.35rem; }
    .bwa-gp-list { display:flex;flex-direction:column;gap:0.28rem;margin:0;padding:0;list-style:none; }
    .bwa-gp-item { display:flex;align-items:center;justify-content:space-between;gap:0.5rem;font-size:0.76rem;line-height:1.3; }
    .bwa-gp-item__name { color:var(--bwa-ink);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:11rem; }
    .bwa-gp-item__count { flex-shrink:0;font-weight:800;color:var(--bwa-teal);background:#ccfbf1;padding:0.1rem 0.4rem;border-radius:6px;font-size:0.7rem; }

    /* photo thumbs */
    .bwa-photo-row { display:flex;align-items:center;gap:0.3rem;flex-wrap:wrap; }
    .bwa-photo-thumb { width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid var(--bwa-border);display:block;flex-shrink:0; }
    .bwa-photo-thumb-link { display:block;line-height:0;border-radius:6px;overflow:hidden; }
    .bwa-photo-thumb-link:hover { box-shadow:0 2px 8px rgba(79,70,229,0.25); }
    .bwa-photo-more { font-size:0.68rem;font-weight:700;color:var(--bwa-muted);padding:0.15rem 0.35rem;background:#f1f5f9;border-radius:6px;white-space:nowrap; }

    /* empty */
    .bwa-empty { padding:3rem 1rem;text-align:center;color:var(--bwa-muted);font-size:0.88rem; }
    .bwa-empty i { display:block;font-size:2.4rem;color:#c7d2fe;margin-bottom:0.55rem; }
</style>
@endpush

@section('content')
<div class="bwa-shell">

    @if (!empty($migrationMissing))
        <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;">
            <strong>Table not found.</strong> Run <code>php artisan migrate</code> to activate this module.
        </div>
    @endif

    {{-- Banner --}}
    <div class="bwa-banner">
        <div class="bwa-banner__left">
            <div class="bwa-banner__icon"><i class="fa-solid fa-people-group"></i></div>
            <div>
                <p class="bwa-banner__title">Block Level Workshop Records</p>
                <p class="bwa-banner__sub">State-wide view &middot; all districts</p>
            </div>
        </div>
    </div>

    {{-- Stat chips --}}
    @if (empty($migrationMissing))
    <div class="bwa-chips">
        <div class="bwa-chip">
            <div class="bwa-chip__icon bwa-chip__icon--indigo"><i class="fa-solid fa-calendar-check"></i></div>
            <div>
                <div class="bwa-chip__label">Workshops</div>
                <div class="bwa-chip__value">{{ number_format($totalWorkshops) }}</div>
            </div>
        </div>
        <div class="bwa-chip">
            <div class="bwa-chip__icon bwa-chip__icon--teal"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="bwa-chip__label">Total participants</div>
                <div class="bwa-chip__value">{{ number_format($totalParticipants) }}</div>
            </div>
        </div>
        <div class="bwa-chip">
            <div class="bwa-chip__icon bwa-chip__icon--blue"><i class="fa-solid fa-person"></i></div>
            <div>
                <div class="bwa-chip__label">Male</div>
                <div class="bwa-chip__value">{{ number_format($totalMale) }}</div>
            </div>
        </div>
        <div class="bwa-chip">
            <div class="bwa-chip__icon bwa-chip__icon--rose"><i class="fa-solid fa-person-dress"></i></div>
            <div>
                <div class="bwa-chip__label">Female</div>
                <div class="bwa-chip__value">{{ number_format($totalFemale) }}</div>
            </div>
        </div>
    </div>
    @endif

    {{-- Filter bar --}}
    @if (empty($migrationMissing))
    <div class="bwa-filter">
        <form method="get" action="{{ route('admin.block-workshops.index') }}">
            <div class="bwa-filter__row">
                <div class="bwa-filter__field" style="max-width:190px;">
                    <label class="bwa-filter__label">Hub</label>
                    <select name="hub" class="bwa-input" onchange="this.form.submit()">
                        <option value="">— All hubs —</option>
                        @foreach ($hubs as $h)
                            <option value="{{ $h->id }}" @selected((int) request('hub') === (int) $h->id)>{{ $h->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="bwa-filter__field" style="max-width:200px;">
                    <label class="bwa-filter__label">District</label>
                    <select name="district" class="bwa-input">
                        <option value="">— All districts —</option>
                        @foreach ($districts as $d)
                            <option value="{{ $d->id }}" @selected((int) request('district') === (int) $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if (!empty($blockOptions))
                <div class="bwa-filter__field" style="max-width:190px;">
                    <label class="bwa-filter__label">Block</label>
                    <select name="block" class="bwa-input">
                        <option value="">— All blocks —</option>
                        @foreach ($blockOptions as $b)
                            <option value="{{ $b }}" @selected(request('block') === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="bwa-filter__field" style="max-width:150px;">
                    <label class="bwa-filter__label">From date</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="bwa-input">
                </div>
                <div class="bwa-filter__field" style="max-width:150px;">
                    <label class="bwa-filter__label">To date</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="bwa-input">
                </div>
                <div class="bwa-filter__field" style="max-width:200px;">
                    <label class="bwa-filter__label">Search</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Name, block, GP…" class="bwa-input">
                </div>
                <div style="display:flex;gap:0.4rem;align-items:flex-end;">
                    <button type="submit" class="bwa-btn"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('admin.block-workshops.index') }}" class="bwa-btn bwa-btn--ghost">Reset</a>
                </div>
            </div>
        </form>
    </div>
    @endif

    {{-- Records table --}}
    @if (empty($migrationMissing))
    <div class="bwa-card">
        <div class="bwa-card__head">
            <div class="bwa-card__head-icon"><i class="fa-solid fa-table-list"></i></div>
            <h3 class="bwa-card__title">Workshop records</h3>
            <span class="bwa-card__count">{{ number_format($reports->total()) }} total &middot; page {{ $reports->currentPage() }} of {{ $reports->lastPage() }}</span>
        </div>

        <div style="overflow-x:auto;">
            <table class="bwa-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Submitted by</th>
                        <th>District</th>
                        <th>Location (block)</th>
                        <th>Gram panchayat (participants)</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Total</th>
                        <th>Participant rows</th>
                        <th>Photos</th>
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
                            <td style="color:var(--bwa-muted);font-size:0.77rem;font-weight:600;">{{ $rowNum }}</td>

                            <td>
                                <span class="bwa-date-badge">{{ $row->visit_date?->format('d M Y') }}</span>
                            </td>

                            <td>
                                <div style="font-size:0.82rem;font-weight:600;color:var(--bwa-text);">{{ $row->field_coordinator_name ?: ($row->coordinator?->name ?? '—') }}</div>
                            </td>

                            <td>
                                @if ($row->district?->name)
                                    <span class="bwa-pill bwa-pill--teal">{{ $row->district->name }}</span>
                                @else
                                    <span style="color:var(--bwa-muted);">—</span>
                                @endif
                            </td>

                            <td>
                                @if ($row->area || $row->block)
                                    @if ($row->area)
                                        <div class="bwa-loc-main">{{ $row->area }}</div>
                                    @endif
                                    @if ($row->block)
                                        <div class="bwa-loc-sub"><i class="fa-solid fa-grip-lines-vertical" style="font-size:0.6rem;margin-right:2px;"></i>{{ $row->block }}</div>
                                    @endif
                                @else
                                    <span style="color:var(--bwa-muted);">—</span>
                                @endif
                            </td>

                            <td style="font-size:0.82rem;min-width:10rem;">
                                @if ($gpCounts !== [])
                                    @if ($row->gramPanchayat?->name)
                                        <div class="bwa-gp-primary" title="Workshop GP">
                                            <i class="fa-solid fa-location-dot" style="font-size:0.65rem;color:var(--bwa-teal);"></i>
                                            {{ $row->gramPanchayat->name }}
                                        </div>
                                    @endif
                                    <ul class="bwa-gp-list">
                                        @foreach ($gpCounts as $gp)
                                            <li class="bwa-gp-item" title="{{ $gp['name'] }}">
                                                <span class="bwa-gp-item__name">{{ $gp['name'] }}</span>
                                                <span class="bwa-gp-item__count">{{ number_format($gp['count']) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @elseif ($row->gramPanchayat?->name)
                                    <div class="bwa-gp-primary">{{ $row->gramPanchayat->name }}</div>
                                @else
                                    <span style="color:var(--bwa-muted);">—</span>
                                @endif
                            </td>

                            <td>
                                @if ((int) $row->participants_male_count > 0)
                                    <span class="bwa-pill bwa-pill--indigo">{{ number_format((int) $row->participants_male_count) }}</span>
                                @else
                                    <span style="color:var(--bwa-muted);">0</span>
                                @endif
                            </td>
                            <td>
                                @if ((int) $row->participants_female_count > 0)
                                    <span class="bwa-pill bwa-pill--rose">{{ number_format((int) $row->participants_female_count) }}</span>
                                @else
                                    <span style="color:var(--bwa-muted);">0</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-weight:800;font-size:0.95rem;color:var(--bwa-text);">{{ number_format((int) $row->participants_total) }}</span>
                            </td>

                            <td>
                                @if (count($participantRows) > 0)
                                    <span style="font-size:0.8rem;color:var(--bwa-teal);font-weight:700;">
                                        {{ $filledRows }}/{{ count($participantRows) }} filled
                                    </span>
                                @else
                                    <span class="bwa-pill bwa-pill--muted">No rows</span>
                                @endif
                            </td>

                            <td style="min-width:8rem;">
                                @if (count($media) > 0)
                                    <div class="bwa-photo-row">
                                        @foreach (array_slice($media, 0, 3) as $idx => $item)
                                            <a href="{{ route('admin.block-workshops.attachment', ['blockWorkshop' => $row, 'index' => $idx, 'inline' => 1]) }}"
                                               target="_blank" rel="noopener"
                                               class="bwa-photo-thumb-link"
                                               title="Photo {{ $idx + 1 }}">
                                                <img class="bwa-photo-thumb"
                                                     src="{{ route('admin.block-workshops.attachment', ['blockWorkshop' => $row, 'index' => $idx, 'inline' => 1]) }}"
                                                     alt="Photo {{ $idx + 1 }}"
                                                     loading="lazy">
                                            </a>
                                        @endforeach
                                        @if (count($media) > 3)
                                            <span class="bwa-photo-more">+{{ count($media) - 3 }}</span>
                                        @endif
                                    </div>
                                @elseif ($row->attachment_path)
                                    <a href="{{ route('admin.block-workshops.attachment', $row) }}" class="bwa-btn bwa-btn--sm bwa-btn--ghost" style="padding:0.3rem 0.55rem;font-size:0.72rem;">
                                        <i class="fa-solid fa-image"></i> 1 file
                                    </a>
                                @else
                                    <span style="color:var(--bwa-muted);font-size:0.8rem;">—</span>
                                @endif
                            </td>

                            <td style="white-space:nowrap;">
                                <a href="{{ route('admin.block-workshops.show', $row) }}" class="bwa-btn bwa-btn--sm bwa-btn--teal" style="text-decoration:none;">
                                    <i class="fa-solid fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12">
                                <div class="bwa-empty">
                                    <i class="fa-regular fa-folder-open"></i>
                                    No block level workshops found matching the current filters.
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

@extends('layouts.admin')

@section('title', 'CFA — Phase 1 legacy')
@section('heading', 'CFA — Phase 1 legacy (FY 2024-25)')

@push('styles')
    <script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
    <strong>Read-only.</strong> This list reads Phase 1 legacy database
    (<code>ukrbiin_rbi.tblapplication</code>), not MUY <code>cfa_submissions</code>.
</div>

@if ($phase1Unavailable ?? false)
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        Phase 1 database is not configured. Set <code>PHASE1_DB_DATABASE</code> (and optionally <code>PHASE1_DB_*</code>)
        in <code>.env</code> — see connection <code>legacy_phase1</code> in <code>config/database.php</code>.
    </div>
@elseif ($phase1MissingTables ?? false)
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        Phase 1 connection works but required table <code>tblapplication</code> was not found.
    </div>
@else
    <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="get" action="{{ route('admin.phase1-cfa.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1">
                <label for="p1cfa-hub" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hub</label>
                <div class="relative">
                    <select id="p1cfa-hub" name="hub"
                            class="min-w-44 appearance-none rounded-lg border border-slate-300 bg-white px-3 py-2 pr-8 text-sm text-slate-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        <option value="">All hubs</option>
                        @foreach (($hubs ?? []) as $hub)
                            <option value="{{ $hub }}" @selected(request('hub') === $hub)>{{ $hub }}</option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none">
                        <path d="M6 8l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>

            <div class="flex flex-col gap-1 flex-1 min-w-48">
                <label for="p1cfa-search" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                <input id="p1cfa-search" type="text" name="search" value="{{ request('search') }}"
                       placeholder="Application no, name or mobile..."
                       class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 w-full">
            </div>

            <div class="flex gap-2 pb-0.5">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                    Apply
                </button>
                @if (request()->hasAny(['hub','search']))
                    <a href="{{ route('admin.phase1-cfa.index') }}"
                       class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50 transition">
                        Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    <div class="mb-2 flex items-center justify-between">
        <p class="text-sm text-slate-500">
            <span class="font-semibold text-slate-700">{{ number_format($rows->total()) }}</span>
            application{{ $rows->total() !== 1 ? 's' : '' }} found
        </p>
        <p class="text-xs text-slate-400">Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}</p>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th class="px-4 py-3 border-b border-slate-200">#</th>
                    <th class="px-4 py-3 border-b border-slate-200">App. No.</th>
                    <th class="px-4 py-3 border-b border-slate-200">Date</th>
                    <th class="px-4 py-3 border-b border-slate-200">Applicant</th>
                    <th class="px-4 py-3 border-b border-slate-200">Mobile</th>
                    <th class="px-4 py-3 border-b border-slate-200">Hub</th>
                    <th class="px-4 py-3 border-b border-slate-200">City</th>
                    <th class="px-4 py-3 border-b border-slate-200">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-2.5 text-slate-400 text-xs">{{ ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration }}</td>
                        <td class="px-4 py-2.5 font-semibold text-indigo-700 whitespace-nowrap">{{ $row->application_no ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500 whitespace-nowrap text-xs">
                            @if ($row->application_date)
                                {{ \Carbon\Carbon::parse($row->application_date)->timezone(config('app.timezone'))->format('d M Y H:i') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2.5 font-medium text-slate-800">{{ $row->full_name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap">{{ $row->mobile_number ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $row->hub_name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $row->city_name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $row->application_status ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">
                            No Phase 1 applications found for the selected filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($rows->hasPages())
        <div class="mt-4">{{ $rows->links() }}</div>
    @endif
@endif
@endsection

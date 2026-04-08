@extends('layouts.admin')

@section('title', 'CFA — Phase 2 legacy')
@section('heading', 'CFA — Phase 2 legacy')

@push('styles')
    <script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')

{{-- Info banner --}}
<div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
    <strong>Read-only.</strong> This list reads the legacy Phase 2 database
    (<code>rbi_applications</code> / <code>rbi_applicant_details</code>), not MUY <code>cfa_submissions</code>.
    FY filter uses the same window as Phase 2 targets (<code>submission_date</code> between fiscal year start and end).
</div>

@if ($legacyUnavailable ?? false)
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        Legacy database is not configured. Set <code>LEGACY_DB_DATABASE</code> (and if needed <code>LEGACY_DB_*</code>)
        in <code>.env</code> — see <code>config/database.php</code> connection <code>legacy</code>.
    </div>

@elseif ($legacyMissingTables ?? false)
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        Legacy connection works but required tables were not found
        (<code>rbi_applications</code>, <code>rbi_applicant_details</code>).
    </div>

@elseif ($fiscalYears->isEmpty())
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        No fiscal year configured. Add FY rows in the database first.
    </div>

@else
    {{-- Filter bar --}}
    <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="get" action="{{ route('admin.phase2-cfa.index') }}"
              class="flex flex-wrap items-end gap-3">

            {{-- Fiscal Year --}}
            <div class="flex flex-col gap-1">
                <label for="p2cfa-fy" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fiscal Year</label>
                <div class="relative">
                    <select id="p2cfa-fy" name="fiscal_year_id" onchange="this.form.submit()"
                            class="min-w-40 appearance-none rounded-lg border border-slate-300 bg-white px-3 py-2 pr-8 text-sm text-slate-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        @foreach ($fiscalYears as $fy)
                            <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none">
                        <path d="M6 8l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>

            {{-- District filter --}}
            @if (!empty($districts))
            <div class="flex flex-col gap-1">
                <label for="p2cfa-district" class="text-xs font-semibold uppercase tracking-wide text-slate-500">District</label>
                <div class="relative">
                    <select id="p2cfa-district" name="district"
                            class="min-w-44 appearance-none rounded-lg border border-slate-300 bg-white px-3 py-2 pr-8 text-sm text-slate-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                        <option value="">All districts</option>
                        @foreach ($districts as $d)
                            <option value="{{ $d }}" @selected(request('district') === $d)>{{ $d }}</option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none">
                        <path d="M6 8l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            @endif

            {{-- Search by name / phone --}}
            <div class="flex flex-col gap-1 flex-1 min-w-48">
                <label for="p2cfa-search" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Search (name / phone)</label>
                <input id="p2cfa-search" type="text" name="search"
                       value="{{ request('search') }}"
                       placeholder="Applicant name or mobile…"
                       class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 w-full">
            </div>

            {{-- Buttons --}}
            <div class="flex gap-2 pb-0.5">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                    Apply
                </button>
                @if (request()->hasAny(['district','search']))
                <a href="{{ route('admin.phase2-cfa.index', ['fiscal_year_id' => $fiscalYearId]) }}"
                   class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50 transition">
                    Clear
                </a>
                @endif
            </div>
        </form>

        @if ($fiscalYear)
        <p class="mt-3 text-xs text-slate-400">
            FY window: {{ $fiscalYear->starts_on?->format('d M Y') }} — {{ $fiscalYear->ends_on?->format('d M Y') }}
            · showing up to 100 per page · newest first
        </p>
        @endif
    </div>

    {{-- Result count --}}
    <div class="mb-2 flex items-center justify-between">
        <p class="text-sm text-slate-500">
            <span class="font-semibold text-slate-700">{{ number_format($rows->total()) }}</span>
            application{{ $rows->total() !== 1 ? 's' : '' }} found
            @if (request()->hasAny(['district','search']))
                <span class="ml-1 text-indigo-600">(filtered)</span>
            @endif
        </p>
        <p class="text-xs text-slate-400">Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}</p>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th class="px-4 py-3 border-b border-slate-200">#</th>
                    <th class="px-4 py-3 border-b border-slate-200">App. No.</th>
                    <th class="px-4 py-3 border-b border-slate-200">Submitted</th>
                    <th class="px-4 py-3 border-b border-slate-200">Applicant</th>
                    <th class="px-4 py-3 border-b border-slate-200">Phone</th>
                    <th class="px-4 py-3 border-b border-slate-200">Category</th>
                    <th class="px-4 py-3 border-b border-slate-200">District</th>
                    <th class="px-4 py-3 border-b border-slate-200">Block</th>
                    <th class="px-4 py-3 border-b border-slate-200">Stage</th>
                    <th class="px-4 py-3 border-b border-slate-200">Submitted by</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $i => $row)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-2.5 text-slate-400 text-xs">{{ ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration }}</td>
                        <td class="px-4 py-2.5 font-semibold text-indigo-700 whitespace-nowrap">{{ $row->application_no ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500 whitespace-nowrap text-xs">
                            @if ($row->submission_date)
                                {{ \Carbon\Carbon::parse($row->submission_date)->timezone(config('app.timezone'))->format('d M Y H:i') }}
                            @else —
                            @endif
                        </td>
                        <td class="px-4 py-2.5 font-medium text-slate-800">{{ $row->applicant_name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap">{{ $row->phone ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            @if ($row->category)
                                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold
                                    {{ $row->category === 'SHG' ? 'bg-purple-100 text-purple-700' : ($row->category === 'CBO' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $row->category }}
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $row->district ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-slate-500 text-xs">{{ $row->block ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            @if ($row->form_stage)
                                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold
                                    {{ $row->form_stage === 'Growth' ? 'bg-green-100 text-green-700' : ($row->form_stage === 'Early' ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-100 text-slate-500') }}">
                                    {{ $row->form_stage }}
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-slate-500 text-xs">{{ $row->submitted_by_name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-10 text-center text-slate-400">
                            No applications found for the selected filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($rows->hasPages())
        <div class="mt-4">{{ $rows->links() }}</div>
    @endif

@endif
@endsection

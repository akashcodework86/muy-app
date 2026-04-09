@extends('layouts.admin')

@section('title', 'CFA applications')
@section('heading', 'CFA applications')

@push('styles')
    <script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
    @if ($fiscalYears->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            No fiscal year configured. Add FY rows in the database first.
        </div>
    @else
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="get" action="{{ route('admin.cfa.index') }}" class="flex flex-wrap items-center gap-3">
                <label for="cfa-fy" class="text-sm font-medium text-slate-700">Fiscal year</label>
                <div class="relative">
                    <select
                        id="cfa-fy"
                        name="fiscal_year_id"
                        onchange="this.form.submit()"
                        class="min-w-48 appearance-none rounded-lg border border-slate-300 bg-white px-3 py-2 pr-9 text-sm text-slate-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                    >
                    @foreach ($fiscalYears as $fy)
                        <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                    @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M6 8l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </form>

            <p class="mt-3 text-sm text-slate-500">
                Public form submissions (referral-linked) for the selected FY. Newest first.
            </p>
        </div>

        <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                        <th class="px-3 py-3">App. no.</th>
                        <th class="px-3 py-3">Date</th>
                        <th class="px-3 py-3">Applicant</th>
                        <th class="px-3 py-3">Phone</th>
                        <th class="px-3 py-3">District</th>
                        <th class="px-3 py-3">LGD (st / dist / blk)</th>
                        <th class="px-3 py-3">Source / Referral staff</th>
                        <th class="no-print-col whitespace-nowrap px-3 py-3">View</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse ($submissions as $row)
                        <tr class="hover:bg-slate-50/80">
                            <td class="whitespace-nowrap px-3 py-3 font-semibold text-slate-900">{{ $row->application_no ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ $row->created_at?->format('Y-m-d H:i') }} IST</td>
                            <td class="px-3 py-3">{{ $row->applicant_name }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ $row->phone }}</td>
                            <td class="px-3 py-3 text-slate-500">{{ $row->district?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-xs text-slate-500" title="MoPR LGD snapshot at submit">{{ $row->lgd_state_code ?? '—' }} / {{ $row->lgd_district_code ?? '—' }} / {{ $row->lgd_block_code ?? '—' }}</td>
                            <td class="px-3 py-3 text-slate-500">
                                @if ($row->source === 'public_form')
                                    <span class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-200">
                                        🌐 Public / Walk-in
                                    </span>
                                @else
                                    {{ $row->referralUser?->name ?? '—' }}
                                @endif
                            </td>
                            <td class="no-print-col px-3 py-3">
                                <a href="{{ route('admin.cfa.show', $row) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-800">
                                    View
                                    <svg class="h-3.5 w-3.5 text-white" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="M7 5l6 5-6 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">No applications yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($submissions->hasPages())
            <div class="mt-4 flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
                <p class="text-slate-600">
                    Showing {{ $submissions->firstItem() }} to {{ $submissions->lastItem() }} of {{ $submissions->total() }}
                </p>
                <div class="flex items-center gap-2">
                    @if ($submissions->onFirstPage())
                        <span class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-slate-400">
                            <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M12.5 15L7.5 10l5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span aria-hidden="true">←</span>
                            Previous
                        </span>
                    @else
                        <a href="{{ $submissions->previousPageUrl() }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 transition hover:bg-slate-50">
                            <svg class="h-4 w-4 shrink-0 text-slate-700" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M12.5 15L7.5 10l5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span aria-hidden="true">←</span>
                            Previous
                        </a>
                    @endif

                    <span class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 font-medium text-indigo-700">
                        Page {{ $submissions->currentPage() }} of {{ $submissions->lastPage() }}
                    </span>

                    @if ($submissions->hasMorePages())
                        <a href="{{ $submissions->nextPageUrl() }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 transition hover:bg-slate-50">
                            Next
                            <span aria-hidden="true">→</span>
                            <svg class="h-4 w-4 shrink-0 text-slate-700" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M7.5 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-slate-400">
                            Next
                            <span aria-hidden="true">→</span>
                            <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M7.5 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    @endif
                </div>
            </div>
        @endif
    @endif
@endsection

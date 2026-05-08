@extends('layouts.admin')

@section('title', 'Training Package Attendance Details')
@section('heading', 'Training Package Attendance Details')

@section('content')
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a href="{{ route('staff.training-packages.index') }}" class="font-medium text-indigo-600 hover:text-indigo-800">← Back to dashboard</a>
            <span class="text-slate-300">|</span>
            <a href="{{ route('staff.training-packages.edit', $entry) }}" class="font-medium text-slate-700 hover:text-slate-900">Edit entry</a>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 text-sm text-slate-700 md:grid-cols-2 xl:grid-cols-3">
                <div><strong>Event taken by:</strong> {{ $entry->event_taken_by_name }}</div>
                <div><strong>Date of event:</strong> {{ $entry->event_date?->format('d M Y') }}</div>
                <div><strong>District:</strong> {{ $entry->district?->name ?? $entry->district_name ?? '—' }}</div>
                <div><strong>Block:</strong> {{ $entry->block }}</div>
                <div><strong>Training package:</strong> {{ strtoupper((string) $entry->training_package) }}</div>
                <div><strong>Participants selected:</strong> {{ number_format((int) $entry->selected_incubatees_count) }}</div>
            </div>

            <div class="mt-4 text-sm">
                <strong>Attendance document:</strong>
                <a href="{{ route('staff.training-packages.attachment', $entry) }}" class="text-indigo-600 hover:text-indigo-800">Download file</a>
                <span class="text-slate-500">({{ $entry->attendance_file_name ?: 'Attachment' }})</span>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-800">Applicant Details</h3>
                <a href="{{ route('staff.training-packages.export-single', array_merge(['trainingPackageAttendance' => $entry->id], request()->query())) }}"
                   class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700 hover:bg-emerald-100">
                    Export Excel (CSV)
                </a>
            </div>

            <form method="get" class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
                <input type="text" name="q" value="{{ $applicantSearch ?? '' }}" placeholder="Filter applicants by name, application no, phone, batch, block..."
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                <button type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Apply</button>
                <a href="{{ route('staff.training-packages.show', $entry) }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Clear</a>
            </form>

            <div class="mb-2 text-xs text-slate-500">
                Showing {{ is_countable($applicants ?? null) ? count($applicants) : 0 }} applicant(s)
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">#</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Name</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Application No</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Phone</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Batch</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Block</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">District</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($applicants ?? collect()) as $idx => $row)
                            <tr class="border-t border-slate-100 text-slate-700">
                                <td class="px-3 py-2">{{ $idx + 1 }}</td>
                                <td class="px-3 py-2 font-medium">{{ $row['name'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['application_no'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['phone'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['batch_name'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['block'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $row['district'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-8 text-center text-slate-500">No applicants found for this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

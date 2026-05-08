@extends('layouts.admin')

@section('title', 'Training Package Attendance')
@section('heading', 'Training Package Attendance')

@section('content')
    <div class="space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-800">Filters & Actions</h3>
                <a href="{{ route('admin.training-packages.export', request()->query()) }}"
                    class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700 hover:bg-emerald-100">
                    Export Excel (CSV)
                </a>
            </div>
        <form method="get" id="adminTpFilterForm" class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-7">
            <input type="date" name="from" value="{{ request('from') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
            <input type="date" name="to" value="{{ request('to') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
            <select name="district_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                <option value="">All districts</option>
                @foreach ($districts as $district)
                    <option value="{{ $district->id }}" @selected((string) request('district_id') === (string) $district->id)>{{ $district->name }}</option>
                @endforeach
            </select>
            <select name="block" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                <option value="">All blocks</option>
                @foreach ($blockOptions as $block)
                    <option value="{{ $block }}" @selected(request('block') === $block)>{{ $block }}</option>
                @endforeach
            </select>
            <select name="training_package" class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                <option value="">All packages</option>
                @foreach (['t1' => 'T1', 't2' => 'T2', 't3' => 'T3'] as $val => $label)
                    <option value="{{ $val }}" @selected(request('training_package') === $val)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Apply</button>
            <a href="{{ route('admin.training-packages.index') }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Clear</a>
        </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <th class="whitespace-nowrap px-4 py-3 font-semibold">Event Date</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold">Staff</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold">District / Block</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold">Package</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold">Participants</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold">Submitted On</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-t border-slate-100 text-slate-700">
                            <td class="whitespace-nowrap px-4 py-3">{{ $row->event_date?->format('d M Y') }}</td>
                            <td class="px-4 py-3 font-medium">{{ $row->event_taken_by_name }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $row->district?->name ?? $row->district_name ?? '—' }}</div>
                                <div class="text-xs text-slate-500">{{ $row->block }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">{{ strtoupper((string) $row->training_package) }}</span>
                            </td>
                            <td class="px-4 py-3">{{ number_format((int) $row->selected_incubatees_count) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $row->created_at?->format('d M Y, h:i A') }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <a href="{{ route('admin.training-packages.show', $row) }}" class="text-indigo-600 hover:text-indigo-800">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">No entries found for selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        @if ($rows->hasPages())
            <div>{{ $rows->links() }}</div>
        @endif
    </div>
    <script>
        (function () {
            const form = document.getElementById('adminTpFilterForm');
            if (!form) return;
            const controls = form.querySelectorAll('input[type="date"], select');
            controls.forEach(function (control) {
                control.addEventListener('change', function () {
                    form.submit();
                });
            });
        }());
    </script>
@endsection

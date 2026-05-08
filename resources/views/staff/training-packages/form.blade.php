@extends('layouts.admin')

@php
    $isEdit = isset($entry) && $entry !== null;
    $selectedFromEntry = collect((array) ($entry?->selected_incubatees_json ?? []))
        ->map(fn ($row) => is_array($row) ? $row : [])
        ->values()
        ->all();
    $selectedIds = old('manual_incubatee_ids', $selectedIdsInitial ?? []);
@endphp

@section('title', $isEdit ? 'Edit Training Package Attendance' : 'Training Package Attendance')
@section('heading', $isEdit ? 'Edit Training Package Attendance' : 'Training Package Attendance Submission')

@section('content')
    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="mb-3 text-sm text-slate-600">
                Fill attendance details and upload the attendance sheet. Both document upload and manual incubatee selection are mandatory.
            </p>

            @if (session('status'))
                <div class="mb-3 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-3 rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <strong>Please fix these errors:</strong>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                method="post"
                action="{{ $isEdit ? route('staff.training-packages.update', $entry) : route('staff.training-packages.store') }}"
                enctype="multipart/form-data"
                id="tpAttendanceForm"
            >
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Event Taken By</label>
                        <input type="text" value="{{ $user->name }}" readonly class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Date of Event *</label>
                        <input type="date" name="event_date" value="{{ old('event_date', $entry?->event_date?->format('Y-m-d')) }}" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">District</label>
                        <input type="text" value="{{ $user->district?->name ?? 'Not assigned' }}" readonly class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Block *</label>
                        <select name="block" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                            <option value="">— Select block —</option>
                            @foreach ($blocks as $block)
                                <option value="{{ $block }}" @selected(old('block', $entry?->block) === $block)>{{ $block }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Training Package *</label>
                        <select name="training_package" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                            <option value="">— Select package —</option>
                            @foreach (['t1' => 'T1', 't2' => 'T2', 't3' => 'T3'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('training_package', strtolower((string) ($entry?->training_package ?? ''))) === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Upload Attendance Sheet / Document *
                        @if ($isEdit && !empty($entry?->attendance_file_name))
                            <span class="font-normal text-slate-500">(current: {{ $entry->attendance_file_name }})</span>
                        @endif
                    </label>
                    <input type="file" name="attendance_file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="w-full rounded-md border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Allowed: pdf, jpg, jpeg, png, webp (max 5 MB)</p>
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="mb-1 text-sm font-bold text-slate-800">Manual Attendance Selection *</div>
                    <p class="mb-2 text-sm text-slate-500">
                        Search onboarded incubatees from your district and select participants.
                    </p>
                    <div class="flex items-center gap-2">
                        <input type="text" id="tpSearchInput" placeholder="Search by application no, name, phone, batch..." class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                        <button type="button" id="tpSearchBtn" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Search</button>
                    </div>
                    <div id="tpApplicantStats" class="mt-2 text-xs text-slate-500">
                        Total applicants: 0
                    </div>
                    <div id="tpSearchResults" class="mt-3 flex max-h-80 flex-col gap-2 overflow-auto"></div>
                </div>

                <div id="tpSelectedHiddenContainer"></div>

                <div class="mt-4 flex items-center gap-2">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                        {{ $isEdit ? 'Update Entry' : 'Submit Entry' }}
                    </button>
                    <a href="{{ route('staff.training-packages.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Back to dashboard
                    </a>
                </div>
            </form>
        </div>

        <aside class="sticky top-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-800">Selected Incubatees</h3>
                <span id="tpSelectedCount" class="text-xs text-slate-500">0 selected</span>
            </div>
            <div id="tpSelectedList" class="mt-3 flex max-h-[520px] flex-col gap-2 overflow-auto"></div>
        </aside>
    </div>

    <script>
        (function () {
            const searchUrl = @json(route('staff.training-packages.incubatees.search'));
            const formEl = document.getElementById('tpAttendanceForm');
            const searchInput = document.getElementById('tpSearchInput');
            const searchBtn = document.getElementById('tpSearchBtn');
            const resultsEl = document.getElementById('tpSearchResults');
            const selectedListEl = document.getElementById('tpSelectedList');
            const hiddenContainerEl = document.getElementById('tpSelectedHiddenContainer');
            const selectedCountEl = document.getElementById('tpSelectedCount');
            const applicantStatsEl = document.getElementById('tpApplicantStats');
            if (!formEl || !searchInput || !searchBtn || !resultsEl || !selectedListEl || !hiddenContainerEl || !selectedCountEl || !applicantStatsEl) {
                return;
            }

            const selectedMap = new Map();
            const oldSelectedIds = @json(array_values(array_map('intval', (array) $selectedIds)));
            const selectedFromEntry = @json($selectedFromEntry);

            selectedFromEntry.forEach(function (row) {
                if (!row || !row.cfa_submission_id) return;
                selectedMap.set(Number(row.cfa_submission_id), row);
            });

            function escapeHtml(value) {
                return String(value || '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function renderSelected() {
                hiddenContainerEl.innerHTML = '';
                selectedListEl.innerHTML = '';
                const entries = Array.from(selectedMap.values());
                selectedCountEl.textContent = entries.length + ' selected';

                if (!entries.length) {
                    selectedListEl.innerHTML = '<div style="color:#64748b;font-size:0.84rem;padding:0.35rem 0;">No incubatees selected yet.</div>';
                    return;
                }

                entries.forEach(function (row) {
                    const id = Number(row.cfa_submission_id || 0);
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'manual_incubatee_ids[]';
                    input.value = String(id);
                    hiddenContainerEl.appendChild(input);

                    const card = document.createElement('div');
                    card.style.cssText = 'border:1px solid #e2e8f0;border-radius:9px;padding:0.5rem;background:#f8fafc;';
                    card.innerHTML =
                        '<div style="display:flex;justify-content:space-between;gap:0.5rem;align-items:flex-start;">' +
                            '<div>' +
                                '<div style="font-weight:600;">' + escapeHtml(row.name) + '</div>' +
                                '<div style="font-size:0.78rem;color:#64748b;">App: ' + escapeHtml(row.application_no || '—') + '</div>' +
                                '<div style="font-size:0.78rem;color:#64748b;">Batch: ' + escapeHtml(row.batch_name || '—') + '</div>' +
                            '</div>' +
                            '<button type="button" data-remove-id="' + id + '" style="border:1px solid #fecaca;background:#fff1f2;color:#b91c1c;border-radius:6px;padding:0.2rem 0.45rem;cursor:pointer;">Remove</button>' +
                        '</div>';
                    selectedListEl.appendChild(card);
                });

                selectedListEl.querySelectorAll('[data-remove-id]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const id = Number(this.getAttribute('data-remove-id') || 0);
                        if (id > 0) {
                            selectedMap.delete(id);
                            renderSelected();
                            renderSearchResults(window.__tpLastResults || []);
                        }
                    });
                });
            }

            function renderSearchResults(rows) {
                window.__tpLastResults = rows;
                resultsEl.innerHTML = '';
                if (!rows.length) {
                    resultsEl.innerHTML = '<div style="font-size:0.84rem;color:#64748b;">No onboarded incubatees found.</div>';
                    return;
                }
                rows.forEach(function (row) {
                    const id = Number(row.cfa_submission_id || 0);
                    const checked = selectedMap.has(id);
                    const card = document.createElement('label');
                    card.style.cssText = 'display:block;border:1px solid #e2e8f0;border-radius:9px;padding:0.5rem;background:#fff;cursor:pointer;';
                    card.innerHTML =
                        '<div style="display:flex;gap:0.5rem;align-items:flex-start;">' +
                            '<input type="checkbox" data-row-id="' + id + '"' + (checked ? ' checked' : '') + ' style="margin-top:0.2rem;">' +
                            '<div>' +
                                '<div style="font-weight:600;">' + escapeHtml(row.name) + '</div>' +
                                '<div style="font-size:0.78rem;color:#64748b;">App: ' + escapeHtml(row.application_no || '—') + ' | Phone: ' + escapeHtml(row.phone || '—') + '</div>' +
                                '<div style="font-size:0.78rem;color:#64748b;">Batch: ' + escapeHtml(row.batch_name || '—') + ' | Block: ' + escapeHtml(row.block || '—') + '</div>' +
                            '</div>' +
                        '</div>';
                    resultsEl.appendChild(card);
                });

                resultsEl.querySelectorAll('input[type="checkbox"][data-row-id]').forEach(function (checkbox) {
                    checkbox.addEventListener('change', function () {
                        const id = Number(this.getAttribute('data-row-id') || 0);
                        if (id <= 0) return;
                        const row = rows.find(function (r) { return Number(r.cfa_submission_id) === id; });
                        if (!row) return;
                        if (this.checked) {
                            selectedMap.set(id, row);
                        } else {
                            selectedMap.delete(id);
                        }
                        renderSelected();
                    });
                });
            }

            async function runSearch() {
                const q = String(searchInput.value || '').trim();
                resultsEl.innerHTML = '<div style="font-size:0.84rem;color:#64748b;">Searching...</div>';
                try {
                    const response = await fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!response.ok) {
                        throw new Error('Search failed');
                    }
                    const payload = await response.json();
                    const totalApplicants = Number(payload.total_applicants || 0);
                    const filteredCount = Number(payload.filtered_count || 0);
                    applicantStatsEl.textContent = q === ''
                        ? ('Total applicants: ' + totalApplicants)
                        : ('Showing ' + filteredCount + ' of ' + totalApplicants + ' applicants');
                    renderSearchResults(Array.isArray(payload.data) ? payload.data : []);
                } catch (e) {
                    resultsEl.innerHTML = '<div style="font-size:0.84rem;color:#b91c1c;">Unable to search right now.</div>';
                    applicantStatsEl.textContent = 'Total applicants: —';
                }
            }

            if (Array.isArray(oldSelectedIds) && oldSelectedIds.length) {
                Promise.resolve().then(async function () {
                    await runSearch();
                    (window.__tpLastResults || []).forEach(function (row) {
                        const id = Number(row.cfa_submission_id || 0);
                        if (oldSelectedIds.includes(id)) {
                            selectedMap.set(id, row);
                        }
                    });
                    renderSelected();
                    renderSearchResults(window.__tpLastResults || []);
                });
            } else {
                renderSelected();
                runSearch();
            }

            searchBtn.addEventListener('click', function () {
                runSearch();
            });

            searchInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    runSearch();
                }
            });

            let searchDebounce = null;
            searchInput.addEventListener('input', function () {
                if (searchDebounce) {
                    clearTimeout(searchDebounce);
                }
                searchDebounce = setTimeout(function () {
                    runSearch();
                }, 250);
            });

            formEl.addEventListener('submit', function (event) {
                if (selectedMap.size < 1) {
                    event.preventDefault();
                    alert('Please select at least one incubatee in Manual Attendance Selection.');
                }
            });
        })();
    </script>
@endsection

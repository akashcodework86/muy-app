@extends('layouts.admin')

@section('title', 'Phase 3 Service Cases')
@section('heading', 'Phase 3 Service Cases')

@section('content')
    @php
        $statusLabel = [
            'draft' => 'Draft',
            'pending_approval' => 'Pending approval',
            'approved' => 'Approved',
            'sent_back' => 'Sent back',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ];
        $statusStyle = [
            'draft' => 'background:#f3f4f6;color:#374151;border:1px solid #d1d5db;',
            'pending_approval' => 'background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;',
            'approved' => 'background:#ecfdf5;color:#166534;border:1px solid #bbf7d0;',
            'sent_back' => 'background:#fff7ed;color:#b45309;border:1px solid #fed7aa;',
            'rejected' => 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;',
            'cancelled' => 'background:#f8fafc;color:#475569;border:1px solid #e2e8f0;',
        ];
        $activeFilterCount = collect($filters)->filter(fn ($v) => (string) $v !== '')->count();
        $legacyPreviews = $legacyPreviews ?? [];
    @endphp

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0.7rem;margin-bottom:1rem;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:0.75rem 0.85rem;">
            <div style="font-size:0.78rem;color:#6b7280;">Total cases</div>
            <div style="font-size:1.3rem;font-weight:800;color:#111827;">{{ number_format($summary['total']) }}</div>
        </div>
        <div style="background:#fff;border:1px solid #bbf7d0;border-radius:10px;padding:0.75rem 0.85rem;">
            <div style="font-size:0.78rem;color:#15803d;">Approved</div>
            <div style="font-size:1.3rem;font-weight:800;color:#166534;">{{ number_format($summary['approved']) }}</div>
        </div>
        <div style="background:#fff;border:1px solid #fde68a;border-radius:10px;padding:0.75rem 0.85rem;">
            <div style="font-size:0.78rem;color:#92400e;">Pending approval</div>
            <div style="font-size:1.3rem;font-weight:800;color:#9a3412;">{{ number_format($summary['pending_approval']) }}</div>
        </div>
        <div style="background:#fff;border:1px solid #fed7aa;border-radius:10px;padding:0.75rem 0.85rem;">
            <div style="font-size:0.78rem;color:#b45309;">Sent back</div>
            <div style="font-size:1.3rem;font-weight:800;color:#c2410c;">{{ number_format($summary['sent_back']) }}</div>
        </div>
        <div style="background:#fff;border:1px solid #fecaca;border-radius:10px;padding:0.75rem 0.85rem;">
            <div style="font-size:0.78rem;color:#b91c1c;">Rejected</div>
            <div style="font-size:1.3rem;font-weight:800;color:#b91c1c;">{{ number_format($summary['rejected']) }}</div>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:10px;padding:0.75rem 0.85rem;margin-bottom:1rem;">
        <div style="font-weight:700;margin-bottom:0.5rem;">District-wise count</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.45rem;">
            @foreach ($districtCounts as $dc)
                @php
                    $isSelectedDistrict = (int) ($filters['district_id'] ?? 0) === (int) $dc['id'];
                    $pillStyle = $isSelectedDistrict
                        ? 'border:1px solid #93c5fd;background:#dbeafe;color:#1d4ed8;'
                        : ($dc['total'] > 0
                            ? 'border:1px solid #d1fae5;background:#ecfdf5;color:#065f46;'
                            : 'border:1px solid #e5e7eb;background:#f9fafb;color:#6b7280;');
                @endphp
                <a
                    href="{{ route('admin.phase3-services.index', array_merge(request()->query(), ['district_id' => $dc['id']])) }}"
                    style="display:flex;justify-content:space-between;gap:0.35rem;padding:0.38rem 0.45rem;border-radius:8px;text-decoration:none;{{ $pillStyle }}"
                >
                    <span style="font-size:0.82rem;font-weight:600;">{{ $dc['name'] }}</span>
                    <span style="font-size:0.82rem;font-weight:800;">{{ number_format($dc['total']) }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <form id="phase3FilterForm" method="get" action="{{ route('admin.phase3-services.index') }}" style="background:#fff;border:1px solid #e4e4e7;border-radius:10px;padding:0.75rem 0.85rem;margin-bottom:1rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;margin-bottom:0.6rem;flex-wrap:wrap;">
            <strong>Filters</strong>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <span style="font-size:0.8rem;color:#475569;">{{ $activeFilterCount }} active</span>
                <a href="{{ route('admin.phase3-services.export', request()->query()) }}" style="text-decoration:none;background:#065f46;color:#fff;padding:0.38rem 0.7rem;border-radius:8px;font-size:0.82rem;font-weight:600;">⬇ Export .xlsx</a>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0.55rem;">
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Applicant / app no / ref no" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">

            <select name="district_id" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="0">All districts</option>
                @foreach ($districts as $district)
                    <option value="{{ $district->id }}" @selected((int) $filters['district_id'] === (int) $district->id)>{{ $district->name }}</option>
                @endforeach
            </select>

            <select name="service_id" id="serviceFilter" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="0">All services</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}" data-category-id="{{ $service->service_category_id }}" @selected((int) $filters['service_id'] === (int) $service->id)>{{ $service->name }}</option>
                @endforeach
            </select>

            <select name="status" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="">All statuses</option>
                @foreach ($statusLabel as $statusKey => $statusText)
                    <option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ $statusText }}</option>
                @endforeach
            </select>

            <select name="reporting_tier" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="">All reporting tiers</option>
                <option value="key" @selected($filters['reporting_tier'] === 'key')>Key</option>
                <option value="non_key" @selected($filters['reporting_tier'] === 'non_key')>Non-Key</option>
                <option value="unset" @selected($filters['reporting_tier'] === 'unset')>Unset</option>
            </select>

            <select name="has_docs" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
                <option value="">All document states</option>
                <option value="1" @selected($filters['has_docs'] === '1')>With documents only</option>
                <option value="0" @selected($filters['has_docs'] === '0')>Without document entry</option>
            </select>

            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center;margin-top:0.65rem;">
            <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.45rem 0.85rem;border-radius:8px;font-weight:600;cursor:pointer;">Filter</button>
            <a href="{{ route('admin.phase3-services.index') }}" style="text-decoration:none;border:1px solid #d4d4d8;padding:0.45rem 0.85rem;border-radius:8px;color:#111827;">Clear</a>
        </div>
    </form>

    <div style="margin-bottom:0.6rem;color:#475569;font-size:0.88rem;">
        Showing {{ number_format($cases->count()) }} of {{ number_format($cases->total()) }} cases
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:10px;font-size:0.86rem;">
            <thead>
                <tr style="text-align:left;background:#f8fafc;">
                    <th style="padding:0.55rem;border-bottom:1px solid #e4e4e7;">#</th>
                    <th style="padding:0.55rem;border-bottom:1px solid #e4e4e7;">Reference</th>
                    <th style="padding:0.55rem;border-bottom:1px solid #e4e4e7;">Applicant</th>
                    <th style="padding:0.55rem;border-bottom:1px solid #e4e4e7;">District</th>
                    <th style="padding:0.55rem;border-bottom:1px solid #e4e4e7;">Service</th>
                    <th style="padding:0.55rem;border-bottom:1px solid #e4e4e7;">Tier</th>
                    <th style="padding:0.55rem;border-bottom:1px solid #e4e4e7;">Status</th>
                    <th style="padding:0.55rem;border-bottom:1px solid #e4e4e7;">SLA</th>
                    <th style="padding:0.55rem;border-bottom:1px solid #e4e4e7;">Submitted</th>
                    <th style="padding:0.55rem;border-bottom:1px solid #e4e4e7;">SPOC</th>
                    <th style="padding:0.55rem;border-bottom:1px solid #e4e4e7;">Documents</th>
                    <th style="padding:0.55rem;border-bottom:1px solid #e4e4e7;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cases as $case)
                    @php
                        $lp = $legacyPreviews[(int) ($case->legacy_application_id ?? 0)] ?? null;
                        $attachments = $case->attachments->map(fn ($a) => [
                            'id' => (int) $a->id,
                            'name' => (string) ($a->original_name ?: 'Attachment'),
                            'size' => (int) ($a->size_bytes ?? 0),
                            'url' => route('admin.phase3-services.attachments.view', ['service_case' => $case->id, 'attachment' => $a->id]),
                        ])->values()->all();
                        $isSlaBreached = $case->sla_deadline_at && \Illuminate\Support\Carbon::parse($case->sla_deadline_at)->isPast() && ! in_array($case->status, ['approved', 'rejected', 'cancelled'], true);
                        $serviceCodeLower = strtolower((string) ($case->service?->code ?? ''));
                        $serviceNameLower = strtolower((string) ($case->service?->name ?? ''));
                        $isUdyamService = $serviceCodeLower === 'udyam_registration'
                            || str_contains($serviceCodeLower, 'udyam')
                            || str_contains($serviceNameLower, 'udyam');
                        $udyamTypeLabel = match ((string) ($case->udyam_registration_type ?? '')) {
                            'existing' => 'Existing',
                            'new' => 'New',
                            default => '',
                        };
                        $udyamTypeDisplay = $udyamTypeLabel !== ''
                            ? $udyamTypeLabel
                            : (($isUdyamService && $case->status === 'pending_approval')
                                ? 'Awaiting SPOC selection'
                                : '');
                    @endphp
                    <tr>
                        <td style="padding:0.5rem;border-bottom:1px solid #f4f4f5;">{{ $loop->iteration + (($cases->currentPage() - 1) * $cases->perPage()) }}</td>
                        <td style="padding:0.5rem;border-bottom:1px solid #f4f4f5;">
                            <span style="font-weight:600;">{{ $case->reference_number ?: '—' }}</span>
                        </td>
                        <td style="padding:0.5rem;border-bottom:1px solid #f4f4f5;">
                            <div style="font-weight:700;color:#111827;">{{ $case->cfaSubmission?->applicant_name ?? ($lp['applicant_name'] ?? null) ?: '—' }}</div>
                            <div style="font-size:0.78rem;color:#64748b;">{{ $case->cfaSubmission?->application_no ?? ($lp['application_no'] ?? null) ?: '—' }}</div>
                        </td>
                        <td style="padding:0.5rem;border-bottom:1px solid #f4f4f5;">{{ $case->cfaSubmission?->district?->name ?? ($lp['district'] ?? null) ?: '—' }}</td>
                        <td style="padding:0.5rem;border-bottom:1px solid #f4f4f5;">
                            <div style="font-weight:700;color:#111827;">{{ $case->service?->name ?? '—' }}</div>
                            <div style="font-size:0.78rem;color:#64748b;">{{ $case->service?->category?->name ?? '—' }}</div>
                            @if ($isUdyamService && $udyamTypeDisplay !== '')
                                <div style="margin-top:0.26rem;">
                                    <span style="display:inline-flex;align-items:center;padding:0.14rem 0.45rem;border-radius:999px;font-size:0.72rem;font-weight:800;letter-spacing:0.01em;background:#fffbeb;border:1px solid #fcd34d;color:#92400e;">
                                        Udyam: {{ $udyamTypeDisplay }}
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td style="padding:0.5rem;border-bottom:1px solid #f4f4f5;">
                            <span style="font-size:0.75rem;padding:0.12rem 0.4rem;border-radius:999px;background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;">
                                {{ strtoupper((string) ($case->service?->reporting_tier ?? 'unset')) }}
                            </span>
                        </td>
                        <td style="padding:0.5rem;border-bottom:1px solid #f4f4f5;">
                            <span style="font-size:0.75rem;padding:0.12rem 0.4rem;border-radius:999px;{{ $statusStyle[$case->status] ?? $statusStyle['draft'] }}">
                                {{ $statusLabel[$case->status] ?? ucfirst((string) $case->status) }}
                            </span>
                        </td>
                        <td style="padding:0.5rem;border-bottom:1px solid #f4f4f5;{{ $isSlaBreached ? 'color:#b91c1c;font-weight:700;' : 'color:#475569;' }}">
                            {{ $case->sla_deadline_at ? \Illuminate\Support\Carbon::parse($case->sla_deadline_at)->format('d M Y') : '—' }}
                            @if ($isSlaBreached)
                                <div style="font-size:0.74rem;">Breached</div>
                            @endif
                        </td>
                        <td style="padding:0.5rem;border-bottom:1px solid #f4f4f5;color:#475569;">
                            {{ $case->submitted_at ? \Illuminate\Support\Carbon::parse($case->submitted_at)->format('d M Y, h:i A') : '—' }}
                        </td>
                        <td style="padding:0.5rem;border-bottom:1px solid #f4f4f5;">{{ $case->spoc?->name ?? 'Unassigned' }}</td>
                        <td style="padding:0.5rem;border-bottom:1px solid #f4f4f5;white-space:nowrap;">
                            <span style="font-weight:700;">{{ count($attachments) }}</span>
                            <button
                                type="button"
                                class="js-documents-open"
                                data-case-label="{{ $case->service?->name ?? 'Service case' }}"
                                data-case-ref="{{ $case->reference_number ?: '—' }}"
                                data-documents='@json($attachments)'
                                style="margin-left:0.35rem;background:#fff;border:1px solid #cbd5e1;color:#1e293b;padding:0.24rem 0.5rem;border-radius:6px;cursor:pointer;"
                            >View</button>
                        </td>
                        <td style="padding:0.5rem;border-bottom:1px solid #f4f4f5;">
                            @if ($case->cfaSubmission)
                                <a href="{{ route('admin.cfa.show', $case->cfaSubmission) }}">View Details</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" style="padding:1rem;color:#64748b;">No Phase 3 service cases found for selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($cases->hasPages())
        <div style="margin-top:1rem;">{{ $cases->links() }}</div>
    @endif

    <div id="docModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:70;padding:1rem;">
        <div style="max-width:42rem;margin:2rem auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
            <div style="padding:0.75rem 0.9rem;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-weight:700;" id="docModalTitle">Documents</div>
                    <div style="font-size:0.8rem;color:#64748b;" id="docModalRef"></div>
                </div>
                <button type="button" id="docModalClose" style="background:none;border:none;font-size:1.2rem;line-height:1;cursor:pointer;">×</button>
            </div>
            <div id="docModalBody" style="padding:0.75rem 0.9rem;max-height:24rem;overflow:auto;"></div>
            <div style="padding:0.75rem 0.9rem;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;">
                <button type="button" id="docModalFooterClose" style="border:1px solid #d1d5db;background:#fff;padding:0.4rem 0.75rem;border-radius:7px;cursor:pointer;">Close</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const filterForm = document.getElementById('phase3FilterForm');
            const serviceFilter = document.getElementById('serviceFilter');
            let autoSubmitTimer = null;
            function queueSubmit(delayMs) {
                if (!filterForm) return;
                if (autoSubmitTimer) {
                    clearTimeout(autoSubmitTimer);
                }
                autoSubmitTimer = setTimeout(function () {
                    filterForm.submit();
                }, delayMs);
            }

            if (filterForm) {
                filterForm.querySelectorAll('select,input[type="date"]').forEach(function (el) {
                    el.addEventListener('change', function () {
                        queueSubmit(120);
                    });
                });

                const searchInput = filterForm.querySelector('input[name="q"]');
                if (searchInput) {
                    searchInput.addEventListener('input', function () {
                        queueSubmit(450);
                    });
                }
            }

            const modal = document.getElementById('docModal');
            const modalTitle = document.getElementById('docModalTitle');
            const modalRef = document.getElementById('docModalRef');
            const modalBody = document.getElementById('docModalBody');
            const closeBtn = document.getElementById('docModalClose');
            const footerCloseBtn = document.getElementById('docModalFooterClose');

            function closeModal() {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
            function openModal() {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }

            document.querySelectorAll('.js-documents-open').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const docs = JSON.parse(btn.dataset.documents || '[]');
                    modalTitle.textContent = 'Documents — ' + (btn.dataset.caseLabel || 'Service case');
                    modalRef.textContent = 'Case Ref: ' + (btn.dataset.caseRef || '—');

                    if (!docs.length) {
                        modalBody.innerHTML = '<p style="margin:0;color:#6b7280;">No documents uploaded for this case.</p>';
                    } else {
                        modalBody.innerHTML = docs.map(function (doc, idx) {
                            const kb = Math.max(1, Math.round((Number(doc.size || 0) / 1024)));
                            return '<div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;padding:0.4rem 0;border-bottom:1px solid #f1f5f9;">'
                                + '<div><div style="font-weight:600;">' + (idx + 1) + '. ' + doc.name + '</div><div style="font-size:0.75rem;color:#64748b;">' + kb + ' KB</div></div>'
                                + '<a href="' + doc.url + '" target="_blank" rel="noopener" style="text-decoration:none;border:1px solid #cbd5e1;padding:0.25rem 0.55rem;border-radius:6px;">View</a>'
                                + '</div>';
                        }).join('');
                    }
                    openModal();
                });
            });

            closeBtn && closeBtn.addEventListener('click', closeModal);
            footerCloseBtn && footerCloseBtn.addEventListener('click', closeModal);
            modal && modal.addEventListener('click', function (event) {
                if (event.target === modal) closeModal();
            });
        })();
    </script>
@endsection

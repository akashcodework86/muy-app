@extends('layouts.admin')

@section('title', 'Add market linkage')
@section('heading', 'Add market linkage')

@section('content')
    <style>
        .ml-partners-fieldset {
            margin: 0 0 1rem;
            padding: 0.75rem 0.9rem;
            border: 1px solid #e4e4e7;
            border-radius: 8px;
        }
        .ml-partners-fieldset legend {
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0 0.25rem;
            color: #0f172a;
        }
        .ml-prior-group {
            margin-bottom: 0.85rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px dashed #c7d2fe;
        }
        .ml-prior-group:last-of-type {
            border-bottom: none;
            margin-bottom: 0.5rem;
            padding-bottom: 0;
        }
        .ml-prior-group__head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem 0.65rem;
            margin-bottom: 0.55rem;
            font-size: 0.78rem;
            color: #64748b;
        }
        .ml-prior-group__head strong {
            color: #4c1d95;
            font-size: 0.82rem;
        }
        .ml-partner-row--prior {
            background: #f8fafc !important;
            border-color: #e2e8f0 !important;
        }
        .ml-partner-row--prior input:disabled,
        .ml-partner-row--prior select:disabled {
            background: #f1f5f9;
            color: #334155;
            cursor: default;
        }
        .ml-prior-badge {
            font-size: 0.72rem;
            font-weight: 700;
            color: #6d28d9;
            background: #ede9fe;
            border: 1px solid #ddd6fe;
            padding: 0.12rem 0.45rem;
            border-radius: 999px;
        }
        .ml-prior-empty {
            margin: 0 0 0.75rem;
            font-size: 0.82rem;
            color: #71717a;
        }
        #partners_container {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        #prior_history_content {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
    </style>
    <p style="margin:0 0 1rem;">
        <a href="{{ route($dashboardRoute) }}">← Market linkage records</a>
        · <a href="{{ route('staff.services.create') }}">Service submission</a>
    </p>

    @if (!empty($migrationMissing))
        <p style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:0.75rem 1rem;border-radius:8px;font-size:0.88rem;">
            Database tables are missing. Run <code>php artisan migrate</code>.
        </p>
    @else
        @if ($errors->any())
            <ul style="color:#b91c1c;margin:0 0 0.75rem;padding-left:1.2rem;font-size:0.88rem;">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        @endif

        <form method="post" action="{{ route($storeRoute) }}" enctype="multipart/form-data" style="max-width:46rem;">
            @csrf

            <p style="margin:0 0 0.65rem;font-size:0.82rem;color:#52525b;">
                Choose <strong>one</strong> incubatee (Phase 3 CFA or Phase 2 legacy). Earlier partners appear below as form fields (read-only). Use <strong>+ Add partner</strong> for new entries in this submission.
            </p>

            @if ($submissions->isNotEmpty())
                <div style="margin-bottom:0.85rem;">
                    <label for="cfa_submission_id" style="display:block;font-weight:600;margin-bottom:0.25rem;font-size:0.9rem;">Incubatee — Phase 3 CFA</label>
                    <select id="cfa_submission_id" name="cfa_submission_id" style="width:100%;padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;">
                        <option value="">— Not using Phase 3 CFA —</option>
                        @foreach ($submissions as $sub)
                            <option value="{{ $sub->id }}" @selected((int) old('cfa_submission_id', (int) ($defaultCfaSubmissionId ?? 0)) === (int) $sub->id)>
                                {{ $sub->applicant_name }} @if ($sub->application_no) · {{ $sub->application_no }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" name="cfa_submission_id" id="cfa_submission_id" value="">
            @endif

            @if ($legacyRows->isNotEmpty())
                <div style="margin-bottom:0.85rem;">
                    <label for="legacy_application_id" style="display:block;font-weight:600;margin-bottom:0.25rem;font-size:0.9rem;">Incubatee — Phase 2 legacy</label>
                    <select id="legacy_application_id" name="legacy_application_id" style="width:100%;padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;">
                        <option value="">— Not using legacy —</option>
                        @foreach ($legacyRows as $lr)
                            <option value="{{ $lr->id }}" @selected((int) old('legacy_application_id', (int) ($defaultLegacyApplicationId ?? 0)) === (int) $lr->id)>
                                {{ $lr->applicant_name }} @if ($lr->application_no) · {{ $lr->application_no }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" name="legacy_application_id" id="legacy_application_id" value="">
            @endif

            <fieldset class="ml-partners-fieldset">
                <legend>Partner details</legend>

                <p id="prior_history_empty" class="ml-prior-empty" style="display:none;">No earlier market linkage for this incubatee.</p>
                <div id="prior_history_wrap" style="display:none;">
                    <p style="margin:0 0 0.5rem;font-size:0.8rem;font-weight:600;color:#4c1d95;">Previously recorded</p>
                    <div id="prior_history_content"></div>
                </div>

                <p id="new_partners_label" style="margin:0.75rem 0 0.5rem;font-size:0.8rem;font-weight:600;color:#0f172a;display:none;">Add new (this submission)</p>
                <div id="partners_container"></div>

                <button type="button" id="add_partner_btn" style="margin-top:0.65rem;background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe;padding:0.45rem 0.85rem;border-radius:8px;font-weight:700;cursor:pointer;font-size:0.85rem;">
                    + Add partner
                </button>
            </fieldset>

            <button type="submit" style="margin-top:0.25rem;background:#18181b;color:#fff;border:none;padding:0.55rem 1.1rem;border-radius:8px;font-weight:600;cursor:pointer;">Save market linkage</button>
        </form>

        <template id="partner_row_template">
            <div class="ml-partner-row" style="border:1px solid #e2e8f0;border-radius:8px;padding:0.75rem;background:#fff;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                    <strong class="ml-partner-label" style="font-size:0.85rem;color:#312e81;">Partner 1</strong>
                    <button type="button" class="ml-remove-partner" style="display:none;background:#fff;border:1px solid #fecaca;color:#b91c1c;padding:0.25rem 0.55rem;border-radius:6px;font-size:0.78rem;font-weight:600;cursor:pointer;">Remove</button>
                </div>
                <div class="ml-partner-fields" style="display:flex;flex-direction:column;gap:0.55rem;">
                    <div>
                        <label style="display:block;font-weight:600;font-size:0.85rem;margin-bottom:0.2rem;">Partner name <span class="ml-req" style="color:#b91c1c;">*</span></label>
                        <input type="text" data-field="partner_name" maxlength="191" required style="width:100%;padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;font-size:0.85rem;margin-bottom:0.2rem;">Online or offline <span class="ml-req" style="color:#b91c1c;">*</span></label>
                        <select data-field="linkage_mode" required style="width:100%;padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;">
                            <option value="">— Select —</option>
                            <option value="online">Online</option>
                            <option value="offline">Offline</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;font-size:0.85rem;margin-bottom:0.2rem;">Linkage date <span class="ml-req" style="color:#b91c1c;">*</span></label>
                        <input type="date" data-field="linkage_date" required style="width:100%;padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;">
                    </div>
                    <div class="ml-bill-wrap">
                        <label style="display:block;font-weight:600;font-size:0.85rem;margin-bottom:0.2rem;">Bill / document <span style="font-weight:400;color:#71717a;">(optional)</span></label>
                        <input type="file" data-field="bill_document" accept=".pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf" style="font-size:0.85rem;">
                    </div>
                </div>
            </div>
        </template>

        @php
            $oldPartnersForForm = old('partners');
            if (! is_array($oldPartnersForForm) || $oldPartnersForForm === []) {
                $oldPartnersForForm = [['partner_name' => '', 'linkage_mode' => '', 'linkage_date' => '']];
            }
        @endphp
        <script>
            (function () {
                const container = document.getElementById('partners_container');
                const template = document.getElementById('partner_row_template');
                const addBtn = document.getElementById('add_partner_btn');
                const oldPartners = @json($oldPartnersForForm);
                const PRIOR_MARKET_LINKAGE = @json($priorMarketLinkageJson ?? ['cfa' => [], 'legacy' => []]);
                const selCfa = document.getElementById('cfa_submission_id');
                const selLegacy = document.getElementById('legacy_application_id');
                const priorWrap = document.getElementById('prior_history_wrap');
                const priorContent = document.getElementById('prior_history_content');
                const priorEmpty = document.getElementById('prior_history_empty');
                const newPartnersLabel = document.getElementById('new_partners_label');

                function esc(s) {
                    const d = document.createElement('div');
                    d.textContent = s == null ? '' : String(s);
                    return d.innerHTML;
                }

                function buildPartnerRow(data, options) {
                    const opts = options || {};
                    const clone = template.content.cloneNode(true);
                    const row = clone.querySelector('.ml-partner-row');
                    if (opts.prior) {
                        row.classList.add('ml-partner-row--prior');
                    }

                    const nameIn = row.querySelector('[data-field="partner_name"]');
                    const modeSel = row.querySelector('[data-field="linkage_mode"]');
                    const dateIn = row.querySelector('[data-field="linkage_date"]');
                    const billWrap = row.querySelector('.ml-bill-wrap');
                    const removeBtn = row.querySelector('.ml-remove-partner');

                    if (data) {
                        nameIn.value = data.partner_name || '';
                        modeSel.value = data.linkage_mode_raw || data.linkage_mode || '';
                        dateIn.value = data.linkage_date || '';
                    } else if (!opts.prior) {
                        dateIn.value = new Date().toISOString().slice(0, 10);
                    }

                    if (opts.prior) {
                        row.querySelector('.ml-partner-label').innerHTML =
                            '<span class="ml-prior-badge">Recorded earlier</span> ' + esc(data.partner_name || 'Partner');
                        removeBtn.remove();
                        row.querySelectorAll('.ml-req').forEach(function (el) { el.remove(); });
                        [nameIn, modeSel, dateIn].forEach(function (el) {
                            el.disabled = true;
                            el.removeAttribute('name');
                            el.removeAttribute('required');
                        });
                        if (data && data.has_document && data.document_url) {
                            billWrap.innerHTML =
                                '<label style="display:block;font-weight:600;font-size:0.85rem;margin-bottom:0.2rem;">Bill / document</label>' +
                                '<a href="' + esc(data.document_url) + '" style="font-size:0.85rem;font-weight:600;">Download attached bill</a>';
                        } else {
                            billWrap.innerHTML =
                                '<label style="display:block;font-weight:600;font-size:0.85rem;margin-bottom:0.2rem;">Bill / document</label>' +
                                '<span style="font-size:0.82rem;color:#94a3b8;">—</span>';
                        }
                    } else {
                        removeBtn.addEventListener('click', function () {
                            row.remove();
                            reindexRows();
                        });
                    }

                    return row;
                }

                function renderPriorHistory() {
                    if (!priorContent) return;
                    const leg = selLegacy ? parseInt(selLegacy.value || '0', 10) : 0;
                    const cfa = selCfa ? parseInt(selCfa.value || '0', 10) : 0;
                    let entries = [];
                    if (leg > 0) entries = (PRIOR_MARKET_LINKAGE.legacy && PRIOR_MARKET_LINKAGE.legacy[leg]) ? PRIOR_MARKET_LINKAGE.legacy[leg] : [];
                    else if (cfa > 0) entries = (PRIOR_MARKET_LINKAGE.cfa && PRIOR_MARKET_LINKAGE.cfa[cfa]) ? PRIOR_MARKET_LINKAGE.cfa[cfa] : [];

                    priorContent.innerHTML = '';
                    const hasIncubatee = leg > 0 || cfa > 0;
                    const hasPrior = Array.isArray(entries) && entries.length > 0;

                    if (priorWrap) priorWrap.style.display = hasPrior ? 'block' : 'none';
                    if (priorEmpty) {
                        priorEmpty.style.display = hasIncubatee && !hasPrior ? 'block' : 'none';
                    }
                    if (newPartnersLabel) {
                        newPartnersLabel.style.display = hasPrior ? 'block' : 'none';
                    }

                    if (!hasPrior) return;

                    entries.forEach(function (entry) {
                        const group = document.createElement('div');
                        group.className = 'ml-prior-group';
                        const view = entry.show_url
                            ? '<a href="' + esc(entry.show_url) + '" style="font-weight:600;">View submission</a>'
                            : '';
                        group.innerHTML =
                            '<div class="ml-prior-group__head">' +
                            '<strong>' + esc(entry.created_at) + '</strong>' +
                            '<span>By ' + esc(entry.staff_name) + '</span>' + view +
                            '</div>';

                        const partners = Array.isArray(entry.partners) ? entry.partners : [];
                        partners.forEach(function (p) {
                            group.appendChild(buildPartnerRow(p, { prior: true }));
                        });
                        priorContent.appendChild(group);
                    });
                }

                function syncIncubateePickers(changed) {
                    if (!selCfa || !selLegacy || selCfa.tagName !== 'SELECT' || selLegacy.tagName !== 'SELECT') return;
                    if (changed === 'cfa' && selCfa.value) selLegacy.value = '';
                    if (changed === 'legacy' && selLegacy.value) selCfa.value = '';
                    renderPriorHistory();
                }
                if (selCfa && selCfa.tagName === 'SELECT') selCfa.addEventListener('change', function () { syncIncubateePickers('cfa'); });
                if (selLegacy && selLegacy.tagName === 'SELECT') selLegacy.addEventListener('change', function () { syncIncubateePickers('legacy'); });

                function reindexRows() {
                    const rows = container.querySelectorAll('.ml-partner-row');
                    rows.forEach(function (row, idx) {
                        row.querySelector('.ml-partner-label').textContent = 'New partner ' + (idx + 1);
                        const removeBtn = row.querySelector('.ml-remove-partner');
                        if (removeBtn) removeBtn.style.display = rows.length > 1 ? 'inline-block' : 'none';
                        row.querySelectorAll('[data-field]').forEach(function (el) {
                            const field = el.getAttribute('data-field');
                            if (field) el.name = 'partners[' + idx + '][' + field + ']';
                        });
                    });
                }

                function addRow(data) {
                    container.appendChild(buildPartnerRow(data, { prior: false }));
                    reindexRows();
                }

                addBtn.addEventListener('click', function () { addRow(null); });

                if (Array.isArray(oldPartners) && oldPartners.length > 0) {
                    oldPartners.forEach(function (p) { addRow(p); });
                } else {
                    addRow(null);
                }

                renderPriorHistory();
            })();
        </script>
    @endif
@endsection

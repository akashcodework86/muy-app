@php
    $prefill = $prefillApplicant ?? null;
    $editingSession = $editingSession ?? null;
    $prefillPayloads = $prefillPayloads ?? [];
    $existingMedia = $existingMedia ?? [];
    $checkedKeys = $checkedKeys ?? ['service_detail' => [], 'cross_cutting' => [], 'partnership' => []];
    $sections = [
        'service_detail' => ['title' => 'In-house — Service details', 'input' => 'service_detail', 'custom' => 'custom_service_detail'],
        'cross_cutting' => ['title' => 'Cross-cutting initiative', 'input' => 'cross_cutting', 'custom' => 'custom_cross_cutting'],
        'partnership' => ['title' => 'External — Co-incubation partners', 'input' => 'partnership', 'custom' => 'custom_partnership'],
    ];
    $itemSchemas = [];
    foreach ($catalog ?? [] as $sectionKey => $items) {
        foreach ($items as $item) {
            $itemSchemas[$item['key']] = \App\Support\AccelerationItemSchemas::forKey($item['key'], $sectionKey);
        }
    }
    $itemSchemas['_partnership'] = \App\Support\AccelerationItemSchemas::forKey('tbi_graphic_era', 'partnership');
    $itemSchemas['_custom'] = \App\Support\AccelerationItemSchemas::forKey('custom_fallback');
    $formAction = $editingSession && !empty($updateRoute)
        ? route($updateRoute, $editingSession)
        : route($storeRoute);
    $sessionDateDefault = old('service_date', $editingSession?->service_date?->toDateString() ?? now()->toDateString());
@endphp
@include('acceleration-services.partials.styles')

<div class="accel-entry-layout" id="accel-form">
    <div class="accel-form-wrap">
    <div class="accel-card accel-card--form">
        <div class="accel-card__head">
            <h3 class="accel-card__title">{{ $editingSession ? 'Edit acceleration entry' : 'New acceleration entry' }}</h3>
            <p class="accel-card__sub">
                Phase 1 applicants only. Tick a service to fill its details. Each service needs its own date.
                <span id="accel_autosave_status" class="accel-autosave-status" aria-live="polite"></span>
            </p>
            @if (!empty($inHouseOnly))
                <div class="accel-alert accel-alert--info" style="margin-top:0.75rem;">
                    District staff can record <strong>In-house — Service details</strong> only.
                    Cross-cutting and co-incubation partners are filled by the state SPOC.
                </div>
            @endif
            @if ($editingSession && (string) ($editingSession->status ?? '') === \App\Support\AccelerationServicesApproval::STATUS_SENT_BACK && $editingSession->sent_back_remarks)
                <div class="accel-alert accel-alert--warning" style="margin-top:0.75rem;">
                    <strong>Sent back by {{ $editingSession->sent_back_by_name ?: 'checker' }}:</strong>
                    {{ $editingSession->sent_back_remarks }}
                    <br><span style="font-size:0.8rem;">Fix the entry and save — it will be resubmitted for approval.</span>
                </div>
            @endif
        </div>

        @if (empty($legacyPhase1Available))
            <div class="accel-alert accel-alert--warning">Legacy Phase 1 database is not configured. Applicant search will be empty.</div>
        @endif

        <form method="post" action="{{ $formAction }}" enctype="multipart/form-data" id="accelSubmitForm">
            @csrf
            @if ($editingSession && !empty($updateRoute))
                @method('PUT')
            @endif
            <input type="hidden" name="session_id" id="accel_session_id" value="{{ old('session_id', $editingSession?->id) }}">

            <div class="accel-block">
                <p class="accel-block__title">1. Session &amp; applicant</p>
                <div class="accel-form-top">
                    <div class="accel-field">
                        <label for="service_date">Session date <span class="accel-required">*</span></label>
                        <input type="date" id="service_date" name="service_date" value="{{ $sessionDateDefault }}" required>
                        <p class="accel-field-hint">Overall visit / logging date for this entry.</p>
                    </div>
                    <div class="accel-field">
                        <label for="accel_search">Search Phase 1 applicant <span class="accel-required">*</span></label>
                        <input type="text" id="accel_search" class="accel-search-input" placeholder="Name, application no, or phone (min 2 characters)" autocomplete="off" @if($prefill) disabled @endif>
                        <p class="accel-field-hint">Hover a row to preview; click <strong>Add</strong> to select.</p>
                    </div>
                </div>

                <div class="accel-picker" id="accel_picker">
                    <div class="accel-picker__grid">
                        <div>
                            <p class="accel-picker__col-head">Applicants</p>
                            <div id="accel_search_results" class="accel-picker__list">
                                <p class="accel-picker__detail-empty" style="padding:0.85rem;">Type in the search box to load applicants.</p>
                            </div>
                        </div>
                        <div class="accel-picker__detail" id="accel_detail_card">
                            <h4 class="accel-picker__detail-title">Applicant details</h4>
                            <p class="accel-picker__detail-empty" id="accel_detail_empty">Hover or click <strong>View</strong> on an applicant to see details here.</p>
                            <div id="accel_detail_body" hidden></div>
                        </div>
                    </div>
                </div>

                <div id="accel_selected_applicant" class="accel-selected" style="{{ $prefill ? '' : 'display:none;' }}">
                    @if ($prefill)
                        <strong>Selected:</strong> {{ $prefill['applicant_name'] }}
                        @if (!empty($prefill['application_no'])) · {{ $prefill['application_no'] }} @endif
                        <br><span>CFA: {{ $prefill['application_date'] ?? '—' }} · {{ $prefill['district_name'] ?: '—' }} · {{ $prefill['phone'] ?: '—' }} · {{ $prefill['onboard_label'] ?: '—' }}</span>
                    @endif
                </div>
                <input type="hidden" name="legacy_phase1_application_id" id="legacy_phase1_application_id" value="{{ old('legacy_phase1_application_id', $prefill['legacy_phase1_application_id'] ?? '') }}" required>
                <input type="hidden" id="incubatee_key" value="{{ $prefill['incubatee_key'] ?? '' }}">
            </div>

            @foreach ($sections as $sectionKey => $meta)
                @continue(empty($catalog[$sectionKey]))
                <div class="accel-block">
                    <p class="accel-block__title">{{ $loop->iteration + 1 }}. {{ $meta['title'] }}</p>
                    <div class="accel-section__items">
                        @foreach ($catalog[$sectionKey] ?? [] as $item)
                            @php
                                $key = $item['key'];
                                $schema = $itemSchemas[$key] ?? [];
                                $sectionChecked = (array) ($checkedKeys[$sectionKey] ?? []);
                                $oldChecked = in_array($key, (array) old($meta['input'], $sectionChecked), true);
                            @endphp
                            <div class="accel-item {{ $oldChecked ? 'is-checked' : '' }}" data-item-key="{{ $key }}" data-section="{{ $sectionKey }}">
                                <div class="accel-item__head">
                                    <input type="checkbox" name="{{ $meta['input'] }}[]" value="{{ $key }}" id="item_{{ $key }}" class="accel-item-check" @checked($oldChecked)>
                                    <label for="item_{{ $key }}">{{ $item['label'] }}</label>
                                </div>
                                <div class="accel-item__extra">
                                    <div class="accel-item__schema" data-schema-for="{{ $key }}">
                                        @foreach ($schema as $field)
                                            @php
                                                $fkey = $field['key'];
                                                // Per-support-type specify fields are rendered inline next to ticks.
                                                if (str_starts_with($fkey, 'support_topic_')) {
                                                    continue;
                                                }
                                                $ftype = $field['type'] ?? 'text';
                                                $fname = 'payload['.$key.']['.$fkey.']';
                                                $savedVal = $prefillPayloads[$key][$fkey] ?? null;
                                                $oldVal = old('payload.'.$key.'.'.$fkey, $savedVal);
                                                $vif = $field['visible_if'] ?? null;
                                                $htmlInput = $field['html_input'] ?? null;
                                                $condHidden = false;
                                                if (! empty($vif['field'])) {
                                                    $depSaved = old('payload.'.$key.'.'.$vif['field'], $prefillPayloads[$key][$vif['field']] ?? null);
                                                    if (! empty($vif['any'])) {
                                                        $condHidden = empty($depSaved);
                                                    } else {
                                                        if (is_array($depSaved)) {
                                                            $condHidden = ! in_array((string) ($vif['value'] ?? ''), array_map('strval', $depSaved), true);
                                                        } else {
                                                            $condHidden = (string) ($depSaved ?? '') !== (string) ($vif['value'] ?? '');
                                                        }
                                                    }
                                                }
                                                $isSupportTypes = $fkey === 'support_types' && $ftype === 'multiselect';
                                            @endphp
                                            <div
                                                class="accel-field{{ $condHidden ? ' is-cond-hidden' : '' }}"
                                                data-field-key="{{ $fkey }}"
                                                @if ($condHidden) hidden @endif
                                                @if (!empty($vif['field']))
                                                    data-visible-if-field="{{ $vif['field'] }}"
                                                    @if (!empty($vif['any'])) data-visible-if-any="1" @endif
                                                    @if (isset($vif['value'])) data-visible-if-value="{{ $vif['value'] }}" @endif
                                                @endif
                                            >
                                                <label>
                                                    {{ $field['label'] ?? $fkey }}
                                                    @if (!empty($field['required'])) <span class="accel-required">*</span> @endif
                                                </label>
                                                @if (!empty($field['help']))
                                                    <p class="accel-field-hint">{{ $field['help'] }}</p>
                                                @endif

                                                @if ($ftype === 'textarea')
                                                    <textarea name="{{ $fname }}" rows="2" @if(!empty($field['required'])) data-schema-required="1" @endif>{{ is_array($oldVal) ? '' : $oldVal }}</textarea>
                                                @elseif ($ftype === 'select')
                                                    <select name="{{ $fname }}" @if(!empty($field['required'])) data-schema-required="1" @endif>
                                                        <option value="">—</option>
                                                        @foreach ($field['options'] ?? [] as $opt)
                                                            <option value="{{ $opt['value'] }}" @selected((string) $oldVal === (string) $opt['value'])>{{ $opt['label'] ?? $opt['value'] }}</option>
                                                        @endforeach
                                                    </select>
                                                @elseif ($isSupportTypes)
                                                    <div class="accel-support-types" data-multiselect-required="{{ !empty($field['required']) ? '1' : '0' }}">
                                                        @foreach ($field['options'] ?? [] as $opt)
                                                            @php
                                                                $optVal = (string) ($opt['value'] ?? '');
                                                                $topicKey = 'support_topic_'.$optVal;
                                                                $topicName = 'payload['.$key.']['.$topicKey.']';
                                                                $topicSaved = $prefillPayloads[$key][$topicKey] ?? null;
                                                                $topicVal = old('payload.'.$key.'.'.$topicKey, $topicSaved);
                                                                $optChecked = is_array($oldVal) && in_array($optVal, $oldVal, true);
                                                            @endphp
                                                            <div class="accel-support-type{{ $optChecked ? ' is-on' : '' }}" data-support-opt="{{ $optVal }}">
                                                                <label class="accel-support-type__tick">
                                                                    <input
                                                                        type="checkbox"
                                                                        name="{{ $fname }}[]"
                                                                        value="{{ $optVal }}"
                                                                        class="accel-multi-check accel-support-type-check"
                                                                        @checked($optChecked)
                                                                    >
                                                                    <span>{{ $opt['label'] ?? $optVal }}</span>
                                                                </label>
                                                                <div
                                                                    class="accel-support-type__specify{{ $optChecked ? '' : ' is-cond-hidden' }}"
                                                                    data-field-key="{{ $topicKey }}"
                                                                    data-visible-if-field="support_types"
                                                                    data-visible-if-value="{{ $optVal }}"
                                                                    @if (! $optChecked) hidden @endif
                                                                >
                                                                    <label>
                                                                        Specify — what topic / what taught
                                                                        <span class="accel-required">*</span>
                                                                    </label>
                                                                    <textarea
                                                                        name="{{ $topicName }}"
                                                                        rows="2"
                                                                        placeholder="What was taught / topics covered…"
                                                                        data-schema-required="1"
                                                                        @if (! $optChecked) disabled @endif
                                                                    >{{ is_array($topicVal) ? '' : $topicVal }}</textarea>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <p class="accel-field-hint">Tick a type to open its specify box beside it.</p>
                                                @elseif ($ftype === 'multiselect')
                                                    <div class="accel-check-grid" data-multiselect-required="{{ !empty($field['required']) ? '1' : '0' }}">
                                                        @foreach ($field['options'] ?? [] as $opt)
                                                            <label>
                                                                <input
                                                                    type="checkbox"
                                                                    name="{{ $fname }}[]"
                                                                    value="{{ $opt['value'] }}"
                                                                    class="accel-multi-check"
                                                                    @checked(is_array($oldVal) && in_array($opt['value'], $oldVal, true))
                                                                >
                                                                <span>{{ $opt['label'] ?? $opt['value'] }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                    <p class="accel-field-hint">Select all that apply.</p>
                                                @elseif ($ftype === 'checkbox')
                                                    <label style="font-weight:500;display:inline-flex;align-items:center;gap:0.35rem;">
                                                        <input type="checkbox" name="{{ $fname }}" value="1" @checked($oldVal) @if(!empty($field['required'])) data-schema-required="1" @endif>
                                                        Yes
                                                    </label>
                                                @elseif ($ftype === 'radio')
                                                    <div class="accel-radio-row">
                                                        @foreach ($field['options'] ?? [] as $opt)
                                                            <label>
                                                                <input type="radio" name="{{ $fname }}" value="{{ $opt['value'] }}" @checked((string) $oldVal === (string) $opt['value']) @if(!empty($field['required'])) data-schema-required="1" @endif>
                                                                {{ $opt['label'] ?? $opt['value'] }}
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @elseif ($ftype === 'amount')
                                                    <div class="accel-input-affix">
                                                        <span class="accel-input-affix__prefix">₹</span>
                                                        <input type="number" step="0.01" min="0" name="{{ $fname }}" value="{{ is_array($oldVal) ? '' : $oldVal }}" placeholder="0.00" class="{{ $fkey === 'order_value' ? 'accel-order-value' : '' }}" @if(!empty($field['required'])) data-schema-required="1" @endif>
                                                    </div>
                                                @elseif ($ftype === 'number')
                                                    <div class="accel-input-affix">
                                                        <input type="number" step="any" min="0" name="{{ $fname }}" value="{{ is_array($oldVal) ? '' : $oldVal }}" placeholder="0" @if(!empty($field['required'])) data-schema-required="1" @endif>
                                                        @if (str_contains($fkey, 'duration_days') || str_contains(strtolower((string) ($field['label'] ?? '')), 'day'))
                                                            <span class="accel-input-affix__suffix">days</span>
                                                        @endif
                                                    </div>
                                                @elseif ($ftype === 'date')
                                                    <input type="date" name="{{ $fname }}" value="{{ is_array($oldVal) ? '' : $oldVal }}" @if(!empty($field['required'])) data-schema-required="1" @endif>
                                                @elseif ($ftype === 'url')
                                                    <input type="url" name="{{ $fname }}" value="{{ is_array($oldVal) ? '' : $oldVal }}" placeholder="https://…" @if(!empty($field['required'])) data-schema-required="1" @endif>
                                                @else
                                                    <input type="{{ $htmlInput === 'month' ? 'month' : ($ftype === 'email' ? 'email' : ($ftype === 'phone' ? 'tel' : 'text')) }}" name="{{ $fname }}" value="{{ is_array($oldVal) ? '' : $oldVal }}" @if(!empty($field['required'])) data-schema-required="1" @endif>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="accel-field accel-media-field {{ in_array($key, ['market_linkage', 'buyer_seller_meet'], true) ? 'accel-media-field--order-proof' : '' }}" data-media-for="{{ $key }}">
                                        <label>
                                            Documents / photos
                                            @if (in_array($key, ['market_linkage', 'buyer_seller_meet'], true))
                                                <span class="accel-proof-note" hidden> — <strong>Proof of order / PO required</strong> when order value is entered</span>
                                            @else
                                                <span style="font-weight:500;color:#64748b;">(optional)</span>
                                            @endif
                                        </label>
                                        <input type="file" name="media[{{ $key }}][]" accept=".pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf" multiple class="accel-media-input" data-preview="preview_{{ $key }}" data-item-key="{{ $key }}">
                                        <div id="preview_{{ $key }}" class="accel-media-preview"></div>
                                        @if (!empty($existingMedia[$key]))
                                            <div class="accel-existing-media">
                                                @foreach ($existingMedia[$key] as $mediaRow)
                                                    <a class="accel-link" href="{{ route($mediaRoute, $mediaRow['id']) }}" target="_blank" rel="noopener">{{ $mediaRow['name'] }}</a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="accel-custom-row">
                        <input type="text" name="{{ $meta['custom'] }}[]" placeholder="+ Add new service (saved for next time)">
                    </div>
                </div>
            @endforeach

            <div class="accel-form-actions">
                <button type="submit" class="accel-btn">{{ $editingSession ? 'Update entry' : 'Save entry' }}</button>
                <span class="accel-field-hint" style="margin:0;">Drafts autosave after you select an applicant.</span>
            </div>
        </form>
    </div>
    </div>

    <aside class="accel-ticked-sidebar" id="accel_ticked_now" aria-label="Ticked services">
        <div class="accel-ticked-sidebar__inner">
            <p class="accel-ticked-sidebar__title">Ticked now</p>
            <p class="accel-ticked-sidebar__sub">Services selected on this entry</p>
            <div id="accel_ticked_list" class="accel-ticked-list">
                <p class="accel-picker__detail-empty">No services ticked yet.</p>
            </div>
        </div>
    </aside>
</div>

@push('scripts')
<script>
(function () {
    const searchUrl = @json(route($searchRoute));
    const historyUrl = @json(route($historyRoute));
    const autosaveUrl = @json(!empty($autosaveRoute) ? route($autosaveRoute) : null);
    const csrfToken = @json(csrf_token());
    const prefillApplicant = @json($prefill ?? null);
    const existingMediaMap = @json($existingMedia ?? []);
    const searchInput = document.getElementById('accel_search');
    const searchResults = document.getElementById('accel_search_results');
    const selectedPanel = document.getElementById('accel_selected_applicant');
    const legacyInput = document.getElementById('legacy_phase1_application_id');
    const incubateeKeyInput = document.getElementById('incubatee_key');
    const detailEmpty = document.getElementById('accel_detail_empty');
    const detailBody = document.getElementById('accel_detail_body');
    const tickedList = document.getElementById('accel_ticked_list');
    const sessionIdInput = document.getElementById('accel_session_id');
    const autosaveStatus = document.getElementById('accel_autosave_status');
    const form = document.getElementById('accelSubmitForm');
    let searchTimer = null;
    let autosaveTimer = null;
    let applicants = [];
    let focusedId = null;
    let lockedId = null;
    let selectedId = null;
    const servicesCache = {};
    let autosaveBusy = false;

    function collectTickedServices() {
        const items = [];
        document.querySelectorAll('.accel-item-check:checked').forEach((el) => {
            const wrap = el.closest('.accel-item');
            const labelEl = wrap ? wrap.querySelector('.accel-item__head label') : null;
            const sectionBlock = wrap ? wrap.closest('.accel-block') : null;
            const sectionTitle = sectionBlock
                ? String((sectionBlock.querySelector('.accel-block__title') || {}).textContent || '').replace(/^\d+\.\s*/, '').trim()
                : '';
            items.push({
                key: el.value,
                label: labelEl ? labelEl.textContent.trim() : el.value,
                section: sectionTitle,
            });
        });
        return items;
    }

    function renderTickedNow() {
        if (!tickedList) return;
        const items = collectTickedServices();
        if (!items.length) {
            tickedList.innerHTML = '<p class="accel-picker__detail-empty">No services ticked yet.</p>';
            return;
        }
        tickedList.innerHTML = items.map((item, index) => {
            return '<div class="accel-ticked-chip">'
                + '<span class="accel-ticked-chip__num">' + (index + 1) + '</span>'
                + '<span class="accel-ticked-chip__body">'
                + '<strong>' + item.label + '</strong>'
                + (item.section ? '<span class="accel-ticked-chip__section">' + item.section + '</span>' : '')
                + '</span>'
                + '</div>';
        }).join('');
    }

    function fieldDepValue(schemaRoot, depField) {
        const controls = schemaRoot.querySelectorAll('input, select, textarea');
        const multi = [];
        let single = null;
        controls.forEach((el) => {
            const name = el.getAttribute('name') || '';
            if (name.endsWith('[' + depField + '][]')) {
                multi.push(el);
            } else if (name.endsWith('[' + depField + ']')) {
                single = el;
            }
        });
        if (multi.length) {
            return multi.filter((el) => el.checked).map((el) => el.value);
        }
        if (!single) return null;
        if (single.type === 'checkbox' || single.type === 'radio') {
            if (single.type === 'radio') {
                const checked = Array.from(controls).find((el) => {
                    const name = el.getAttribute('name') || '';
                    return name.endsWith('[' + depField + ']') && el.checked;
                });
                return checked ? checked.value : null;
            }
            return single.checked ? single.value : null;
        }
        return single.value;
    }

    function syncVisibleIf(wrap) {
        const schemaRoot = wrap.querySelector('.accel-item__schema');
        if (!schemaRoot) return;
        schemaRoot.querySelectorAll('[data-visible-if-field]').forEach((row) => {
            const dep = row.getAttribute('data-visible-if-field') || '';
            const any = row.getAttribute('data-visible-if-any') === '1';
            const expected = row.getAttribute('data-visible-if-value') || '';
            const actual = fieldDepValue(schemaRoot, dep);
            let show = false;
            if (any) {
                show = Array.isArray(actual) ? actual.length > 0 : !!(actual && String(actual).trim() !== '');
            } else if (Array.isArray(actual)) {
                show = actual.map(String).includes(String(expected));
            } else {
                show = String(actual ?? '') === String(expected);
            }
            row.hidden = !show;
            row.classList.toggle('is-cond-hidden', !show);
            const parentType = row.closest('.accel-support-type');
            if (parentType) {
                parentType.classList.toggle('is-on', show);
            }
            row.querySelectorAll('input, select, textarea').forEach((el) => {
                if (!show) {
                    el.disabled = true;
                    el.required = false;
                }
            });
        });
        const check = wrap.querySelector('.accel-item-check');
        syncItemRequired(wrap, !!(check && check.checked));
    }

    function syncItemRequired(wrap, checked) {
        wrap.querySelectorAll('.accel-field, .accel-support-type__specify').forEach((field) => {
            if (field.hidden || field.classList.contains('is-cond-hidden')) {
                field.querySelectorAll('input, select, textarea').forEach((el) => {
                    el.disabled = true;
                    el.required = false;
                });
                return;
            }
            field.querySelectorAll('[data-schema-required="1"]').forEach((el) => {
                el.required = !!checked;
                el.disabled = !checked;
            });
        });
        wrap.querySelectorAll('.accel-multi-check, .accel-media-input').forEach((el) => {
            el.disabled = !checked;
        });
        syncOrderProofMedia(wrap);
    }

    function syncOrderProofMedia(wrap) {
        if (!wrap) return;
        const itemKey = wrap.getAttribute('data-item-key') || '';
        if (itemKey !== 'market_linkage' && itemKey !== 'buyer_seller_meet') return;
        const orderInput = wrap.querySelector('.accel-order-value');
        const mediaField = wrap.querySelector('.accel-media-field--order-proof');
        const mediaInput = wrap.querySelector('.accel-media-input');
        const note = wrap.querySelector('.accel-proof-note');
        const checked = !!(wrap.querySelector('.accel-item-check') || {}).checked;
        const orderVal = parseFloat(orderInput && orderInput.value ? orderInput.value : '0') || 0;
        const needsProof = checked && orderVal > 0;
        const existing = existingMediaMap[itemKey];
        const hasExisting = Array.isArray(existing) && existing.length > 0;
        if (mediaField) mediaField.classList.toggle('is-required-proof', needsProof);
        if (note) note.hidden = !needsProof;
        if (mediaInput) {
            mediaInput.required = needsProof && !hasExisting;
            mediaInput.disabled = !checked;
        }
    }

    function bindItemChecks() {
        document.querySelectorAll('.accel-item-check').forEach((el) => {
            const wrap = el.closest('.accel-item');
            const sync = () => {
                wrap.classList.toggle('is-checked', el.checked);
                syncVisibleIf(wrap);
                renderTickedNow();
                scheduleAutosave();
            };
            el.addEventListener('change', sync);
            wrap.querySelectorAll('input, select, textarea').forEach((input) => {
                if (input.classList.contains('accel-item-check')) return;
                input.addEventListener('change', () => {
                    syncVisibleIf(wrap);
                    scheduleAutosave();
                });
                input.addEventListener('input', () => {
                    if (input.classList.contains('accel-order-value')) syncOrderProofMedia(wrap);
                    scheduleAutosave();
                });
            });
            sync();
        });
    }

    function setAutosaveStatus(text, isError) {
        if (!autosaveStatus) return;
        autosaveStatus.textContent = text ? ' · ' + text : '';
        autosaveStatus.style.color = isError ? '#b91c1c' : '#0f766e';
    }

    function scheduleAutosave() {
        if (!autosaveUrl || !form) return;
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(runAutosave, 1200);
    }

    function runAutosave() {
        if (!autosaveUrl || !form || autosaveBusy) return;
        if (!legacyInput || !legacyInput.value) {
            setAutosaveStatus('Select an applicant to autosave', false);
            return;
        }
        autosaveBusy = true;
        setAutosaveStatus('Saving draft…', false);
        const data = new FormData(form);
        data.set('_token', csrfToken);
        // Autosave should not send _method PUT confusion for create drafts
        data.delete('_method');
        fetch(autosaveUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data,
        })
            .then(async (r) => {
                const json = await r.json().catch(() => ({}));
                if (!r.ok || !json.ok) {
                    throw new Error((json.errors && Object.values(json.errors).flat()[0]) || 'Autosave failed');
                }
                if (sessionIdInput && json.session_id) {
                    sessionIdInput.value = String(json.session_id);
                }
                if (json.update_url && form) {
                    form.action = json.update_url;
                    if (!form.querySelector('input[name="_method"]')) {
                        const methodInput = document.createElement('input');
                        methodInput.type = 'hidden';
                        methodInput.name = '_method';
                        methodInput.value = 'PUT';
                        form.appendChild(methodInput);
                    }
                }
                if (json.edit_url && window.history && window.history.replaceState && !String(window.location.pathname).includes('/edit')) {
                    window.history.replaceState({}, '', json.edit_url);
                }
                setAutosaveStatus('Draft saved', false);
            })
            .catch((err) => {
                setAutosaveStatus(err.message || 'Autosave failed', true);
            })
            .finally(() => {
                autosaveBusy = false;
            });
    }

    function bindMediaPreviews() {
        document.querySelectorAll('.accel-media-input').forEach((input) => {
            input.addEventListener('change', function () {
                const preview = document.getElementById(this.dataset.preview || '');
                if (!preview) return;
                preview.innerHTML = '';
                Array.from(this.files || []).forEach((file) => {
                    if ((file.type || '').startsWith('image/')) {
                        const img = document.createElement('img');
                        img.alt = file.name;
                        img.src = URL.createObjectURL(file);
                        preview.appendChild(img);
                    } else {
                        const chip = document.createElement('span');
                        chip.className = 'accel-media-chip';
                        chip.textContent = file.name;
                        preview.appendChild(chip);
                    }
                });
                const wrap = this.closest('.accel-item');
                syncOrderProofMedia(wrap);
                scheduleAutosave();
            });
        });
    }

    function applicantId(app) {
        return String(app.legacy_phase1_application_id || '');
    }

    function applicantBriefMeta(app) {
        return [
            'CFA: ' + (app.application_date || '—'),
            app.district_name || '—',
            app.phone || '—',
            app.onboard_label || '—',
        ].join(' · ');
    }

    function renderServicesHtml(items) {
        if (!items.length) {
            return '<p class="accel-picker__detail-empty">No services recorded yet.</p>';
        }
        return items.map((row) => {
            let badge = '';
            if (row.badge === 'initiation') {
                badge = '<span class="accel-badge accel-badge--init">Initiation</span>';
            } else if (row.badge === 'follow-up') {
                badge = '<span class="accel-badge accel-badge--follow">Follow-up</span>';
            }
            const status = row.status ? ' · ' + row.status.replace(/_/g, ' ') : '';
            const detail = row.detail
                ? '<div class="accel-side-entry__meta">' + row.detail + '</div>'
                : '';
            return '<div class="accel-side-entry">'
                + '<div class="accel-side-entry__date">' + (row.date || '—') + (badge ? ' ' + badge : '') + '</div>'
                + '<div>' + (row.label || '—') + '</div>'
                + '<div class="accel-side-entry__meta">By ' + (row.assigned_by || '—')
                + (row.media_count > 0 ? ' · ' + row.media_count + ' file(s)' : '')
                + status
                + (row.source_label ? ' · ' + row.source_label : '')
                + '</div>'
                + detail
                + '</div>';
        }).join('');
    }

    function loadServices(app) {
        if (!app) {
            return Promise.resolve([]);
        }
        const legacyId = applicantId(app);
        const key = String(app.incubatee_key || '');
        const cacheKey = legacyId + ':' + key;
        if (servicesCache[cacheKey]) {
            return Promise.resolve(servicesCache[cacheKey]);
        }
        const params = new URLSearchParams({
            legacy_phase1_application_id: legacyId,
            incubatee_key: key,
        });
        return fetch(historyUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then((r) => r.json())
            .then((data) => {
                const items = data.services || [];
                servicesCache[cacheKey] = items;
                return items;
            })
            .catch(() => []);
    }

    function renderDetail(app, serviceItems) {
        if (!app) {
            detailEmpty.hidden = false;
            detailBody.hidden = true;
            detailBody.innerHTML = '';
            return;
        }
        detailEmpty.hidden = true;
        detailBody.hidden = false;
        detailBody.innerHTML = ''
            + '<dl class="accel-picker__meta">'
            + '<div><dt>Name</dt><dd>' + (app.applicant_name || 'Unnamed') + '</dd></div>'
            + '<div><dt>Application no</dt><dd>' + (app.application_no || '—') + '</dd></div>'
            + '<div><dt>CFA date</dt><dd>' + (app.application_date || '—') + '</dd></div>'
            + '<div><dt>District</dt><dd>' + (app.district_name || '—') + '</dd></div>'
            + '<div><dt>Block</dt><dd>' + (app.block_name || '—') + '</dd></div>'
            + '<div><dt>Phone</dt><dd>' + (app.phone || '—') + '</dd></div>'
            + '<div><dt>Onboarding</dt><dd>' + (app.onboard_label || '—') + '</dd></div>'
            + '</dl>'
            + '<p class="accel-picker__history-title">All services given</p>'
            + '<div class="accel-picker__history">' + renderServicesHtml(serviceItems || []) + '</div>';
    }

    function showApplicantDetail(app, lock) {
        if (!app) return;
        const id = applicantId(app);
        focusedId = id;
        if (lock) {
            lockedId = id;
        }
        highlightListRows();
        loadServices(app).then((items) => renderDetail(app, items));
    }

    function highlightListRows() {
        searchResults.querySelectorAll('.accel-search-item').forEach((row) => {
            const id = row.dataset.applicantId || '';
            row.classList.toggle('is-active', id === focusedId || id === lockedId);
            row.classList.toggle('is-selected', id === selectedId);
        });
    }

    function renderSelectedApplicant(app) {
        selectedPanel.style.display = '';
        selectedPanel.innerHTML = '<strong>Selected:</strong> ' + (app.applicant_name || 'Unnamed')
            + (app.application_no ? ' · ' + app.application_no : '')
            + '<br><span>' + applicantBriefMeta(app) + '</span>';

        // Prefill "to whom" on funding/convergence if empty
        document.querySelectorAll('input[name*="[to_whom]"]').forEach((el) => {
            if (!el.value) {
                el.value = app.applicant_name || '';
            }
        });
    }

    function selectApplicant(app) {
        const id = applicantId(app);
        selectedId = id;
        legacyInput.value = id;
        incubateeKeyInput.value = String(app.incubatee_key || '');
        renderSelectedApplicant(app);
        showApplicantDetail(app, true);
        highlightListRows();
        scheduleAutosave();
    }

    function renderApplicantList(list) {
        applicants = list;
        searchResults.innerHTML = '';
        if (!list.length) {
            searchResults.innerHTML = '<p class="accel-picker__detail-empty" style="padding:0.85rem;">No applicants found.</p>';
            renderDetail(null);
            return;
        }

        list.forEach((app) => {
            const id = applicantId(app);
            const div = document.createElement('div');
            div.className = 'accel-search-item';
            div.dataset.applicantId = id;

            const nameLine = document.createElement('div');
            nameLine.className = 'accel-search-item__name';
            nameLine.textContent = (app.applicant_name || 'Unnamed')
                + (app.application_no ? ' · ' + app.application_no : '');

            const metaLine = document.createElement('div');
            metaLine.className = 'accel-search-item__meta';
            metaLine.textContent = applicantBriefMeta(app);

            const actions = document.createElement('div');
            actions.className = 'accel-search-item__actions';

            const viewBtn = document.createElement('button');
            viewBtn.type = 'button';
            viewBtn.className = 'accel-btn accel-btn--xs accel-btn--ghost';
            viewBtn.textContent = 'View';
            viewBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                showApplicantDetail(app, true);
            });

            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'accel-btn accel-btn--xs accel-btn--add';
            addBtn.textContent = 'Add';
            addBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                selectApplicant(app);
            });

            actions.appendChild(viewBtn);
            actions.appendChild(addBtn);

            div.appendChild(nameLine);
            div.appendChild(metaLine);
            div.appendChild(actions);

            div.addEventListener('mouseenter', () => {
                showApplicantDetail(app, false);
            });

            div.addEventListener('mouseleave', () => {
                if (lockedId) {
                    const lockedApp = applicants.find((a) => applicantId(a) === lockedId);
                    if (lockedApp) {
                        showApplicantDetail(lockedApp, true);
                    }
                    return;
                }
                focusedId = null;
                highlightListRows();
                renderDetail(null);
            });

            searchResults.appendChild(div);
        });

        highlightListRows();
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            const q = (this.value || '').trim();
            lockedId = null;
            focusedId = null;
            if (q.length < 2) {
                searchResults.innerHTML = '<p class="accel-picker__detail-empty" style="padding:0.85rem;">Type at least 2 characters to search.</p>';
                renderDetail(null);
                return;
            }
            searchTimer = setTimeout(() => {
                searchResults.innerHTML = '<p class="accel-picker__detail-empty" style="padding:0.85rem;">Searching…</p>';
                fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                    .then((r) => r.json())
                    .then((data) => renderApplicantList(data.applicants || []));
            }, 250);
        });
    }

    bindItemChecks();
    bindMediaPreviews();

    if (prefillApplicant && prefillApplicant.legacy_phase1_application_id) {
        selectApplicant(prefillApplicant);
    } else if (incubateeKeyInput && incubateeKeyInput.value && legacyInput.value) {
        const stub = {
            legacy_phase1_application_id: legacyInput.value,
            incubatee_key: incubateeKeyInput.value,
            applicant_name: selectedPanel.textContent || 'Selected applicant',
            application_no: '',
            application_date: '—',
            district_name: '—',
            phone: '—',
            onboard_label: '—',
            block_name: '—',
        };
        showApplicantDetail(stub, true);
        selectedId = String(legacyInput.value);
        highlightListRows();
    }
}());
</script>
@endpush

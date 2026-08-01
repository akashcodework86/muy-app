@extends('layouts.admin')

@section('title', 'Edit service case')
@section('heading', 'Edit service case')

@section('content')
    <style>
        .svc-doc-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            border-radius: 8px;
            padding: 0.25rem 0.55rem;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
        }
        .svc-doc-btn:hover { background: #e2e8f0; }
        .svc-att-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.55rem;
            padding: 0.55rem 0.65rem;
            border: 1px solid #e4e4e7;
            border-radius: 8px;
            background: #fafafa;
            margin-bottom: 0.45rem;
        }
        .svc-att-row.is-marked-remove {
            opacity: 0.55;
            background: #fff1f2;
            border-color: #fecdd3;
        }
        .svc-att-name {
            flex: 1 1 12rem;
            font-size: 0.82rem;
            color: #334155;
            min-width: 0;
            word-break: break-word;
        }
        .svc-att-meta { font-size: 0.74rem; color: #71717a; }
        .svc-att-remove {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.78rem;
            color: #9f1239;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
        }
        .svc-doc-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.55);
            z-index: 80;
            padding: 1rem;
        }
        .svc-doc-modal.is-open { display: flex; }
        .svc-doc-modal__card {
            width: min(980px, 96vw);
            max-height: 92vh;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.28);
            display: flex;
            flex-direction: column;
        }
        .svc-doc-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.8rem;
            padding: 0.7rem 0.9rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .svc-doc-modal__title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .svc-doc-modal__close {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            border-radius: 8px;
            padding: 0.25rem 0.55rem;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .svc-doc-modal__body {
            padding: 0.8rem;
            overflow: auto;
            background: #f8fafc;
            min-height: 320px;
        }
        .svc-doc-modal__frame {
            width: 100%;
            min-height: 72vh;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }
        .svc-doc-modal__img {
            max-width: 100%;
            max-height: 72vh;
            display: block;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }
    </style>

    <p style="margin:0 0 1rem;">
        <a href="{{ route('staff.services.index') }}">← Service cases</a>
    </p>

    @if ($errors->any())
        <ul style="color:#b91c1c;margin:0 0 0.75rem;padding-left:1.2rem;font-size:0.88rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:10px;padding:0.8rem 0.9rem;max-width:48rem;margin-bottom:0.8rem;">
        <p style="margin:0;font-size:0.86rem;">
            <strong>Incubatee:</strong>
            @if ($case->cfaSubmission)
                {{ $case->cfaSubmission->applicant_name }}
                @if ($case->cfaSubmission->application_no)
                    · {{ $case->cfaSubmission->application_no }}
                @endif
            @elseif (! empty($legacyIncubateePreview))
                {{ $legacyIncubateePreview['applicant_name'] ?: '—' }}
                @if ($legacyIncubateePreview['application_no'] !== '')
                    · {{ $legacyIncubateePreview['application_no'] }}
                @endif
                <span style="color:#64748b;font-size:0.78rem;">(Phase 2 legacy #{{ $case->legacy_application_id }})</span>
            @else
                —
            @endif
        </p>
        <p style="margin:0.25rem 0 0;font-size:0.86rem;"><strong>Service:</strong> {{ $case->service?->name ?? '—' }}</p>
        <p style="margin:0.25rem 0 0;font-size:0.8rem;color:#71717a;">Status: {{ str_replace('_', ' ', $case->status) }}</p>
    </div>

    <form method="post" action="{{ route('staff.services.update', $case) }}" enctype="multipart/form-data" style="max-width:48rem;">
        @csrf
        @method('PATCH')

        <div style="margin-bottom:0.85rem;">
            <label for="reference_number" style="display:block;font-weight:600;margin-bottom:0.25rem;font-size:0.9rem;">Reference / certificate no. <span style="font-weight:400;color:#71717a;">(optional)</span></label>
            <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number', $case->reference_number) }}" maxlength="191" style="width:100%;padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;">
        </div>

        @if (! $case->service?->requires_approval)
            <div style="margin-bottom:0.85rem;">
                <label for="delivered_on" style="display:block;font-weight:600;margin-bottom:0.25rem;font-size:0.9rem;">Delivered on</label>
                <x-activity-date-input name="delivered_on" id="delivered_on" :value="optional($case->delivered_on)->format('Y-m-d')" :required="false" style="padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;" />
            </div>
        @endif

        <fieldset style="margin:0 0 1rem;padding:0.75rem 0.9rem;border:1px solid #e4e4e7;border-radius:8px;">
            <legend style="font-size:0.85rem;font-weight:600;">Service details</legend>
            @if ($schema === [] && ! ($isConvergenceService ?? false) && ! ($isReapSupportService ?? false))
                <p style="margin:0;font-size:0.82rem;color:#71717a;">No extra fields configured for this service.</p>
            @elseif ($schema !== [])
                <div style="display:flex;flex-direction:column;gap:0.65rem;">
                    @foreach ($schema as $field)
                        @php
                            $key = $field['key'];
                            $type = $field['type'] ?? 'text';
                            $oldValue = old("payload.$key");
                            $currentValue = is_array($oldValue) ? $oldValue : ($oldValue ?? ($payload[$key] ?? null));
                            if ($key === 'scheme_name' && ($currentValue === null || $currentValue === '') && $case->service?->name) {
                                $currentValue = $case->service->name;
                            }
                            $matchingAttachment = null;
                            if ($type === 'file' && ! empty($payload[$key])) {
                                $matchingAttachment = $case->attachments->first(
                                    fn ($att) => strcasecmp((string) $att->original_name, (string) $payload[$key]) === 0
                                );
                            }
                        @endphp
                        <div class="svc-field-row" data-field-key="{{ $key }}" data-visible-if-field="{{ $field['visible_if']['field'] ?? '' }}" data-visible-if-value="{{ $field['visible_if']['value'] ?? '' }}">
                            <label style="display:block;font-size:0.82rem;font-weight:600;margin-bottom:0.2rem;">
                                {{ $field['label'] }}
                                @if (!empty($field['required']))<span style="color:#b91c1c">*</span>@endif
                            </label>
                            @if (!empty($field['help']))
                                <p style="margin:0 0 0.25rem;font-size:0.74rem;color:#71717a;">{{ $field['help'] }}</p>
                            @endif

                            @if ($type === 'textarea')
                                <textarea name="payload[{{ $key }}]" rows="3" style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;font-size:0.85rem;" @if (!empty($field['required'])) required @endif>{{ is_scalar($currentValue) ? (string) $currentValue : '' }}</textarea>
                            @elseif ($type === 'select')
                                <select name="payload[{{ $key }}]" style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;" @if (!empty($field['required'])) required @endif>
                                    <option value="">—</option>
                                    @foreach (($field['options'] ?? []) as $opt)
                                        @php
                                            $ov = (string) ($opt['value'] ?? '');
                                            $ol = (string) ($opt['label'] ?? $ov);
                                        @endphp
                                        <option value="{{ $ov }}" @selected((string) $currentValue === $ov)>{{ $ol }}</option>
                                    @endforeach
                                </select>
                            @elseif ($type === 'multiselect')
                                @php $selected = is_array($currentValue) ? array_map('strval', $currentValue) : []; @endphp
                                <select name="payload[{{ $key }}][]" multiple size="{{ min(6, max(3, count($field['options'] ?? []))) }}" style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;" @if (!empty($field['required'])) required @endif>
                                    @foreach (($field['options'] ?? []) as $opt)
                                        @php
                                            $ov = (string) ($opt['value'] ?? '');
                                            $ol = (string) ($opt['label'] ?? $ov);
                                        @endphp
                                        <option value="{{ $ov }}" @selected(in_array($ov, $selected, true))>{{ $ol }}</option>
                                    @endforeach
                                </select>
                            @elseif ($type === 'checkbox')
                                <label style="display:inline-flex;align-items:center;gap:0.35rem;">
                                    <input type="hidden" name="payload[{{ $key }}]" value="0">
                                    <input type="checkbox" name="payload[{{ $key }}]" value="1" @checked((bool) $currentValue) @if (!empty($field['required'])) required @endif>
                                    Yes
                                </label>
                            @elseif ($type === 'file')
                                <input type="file" name="payload_files[{{ $key }}]" accept=".pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf" style="font-size:0.85rem;" @if (!empty($field['required']) && empty($payload[$key])) required @endif>
                                @if (!empty($payload[$key]))
                                    <p style="margin:0.35rem 0 0;font-size:0.74rem;color:#71717a;display:flex;flex-wrap:wrap;align-items:center;gap:0.45rem;">
                                        <span>Current: {{ (string) $payload[$key] }}</span>
                                        @if ($matchingAttachment)
                                            <button
                                                type="button"
                                                class="svc-doc-btn js-doc-open"
                                                data-doc-url="{{ route('staff.services.attachments.download', [$case, $matchingAttachment]) }}"
                                                data-doc-name="{{ $matchingAttachment->original_name }}"
                                            >View</button>
                                        @endif
                                    </p>
                                @endif
                            @elseif ($type === 'radio')
                                <div style="display:flex;flex-wrap:wrap;gap:0.8rem;">
                                    @foreach (($field['options'] ?? []) as $idx => $opt)
                                        @php
                                            $ov = (string) ($opt['value'] ?? '');
                                            $ol = (string) ($opt['label'] ?? $ov);
                                        @endphp
                                        <label style="display:inline-flex;align-items:center;gap:0.35rem;">
                                            <input type="radio" name="payload[{{ $key }}]" value="{{ $ov }}" @checked((string) $currentValue === $ov) @if (!empty($field['required']) && $idx === 0) required @endif>
                                            {{ $ol }}
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                @php
                                    $htmlType = match($type) {
                                        'amount', 'number' => 'number',
                                        'date' => 'date',
                                        'email' => 'email',
                                        'url' => 'url',
                                        'phone' => 'tel',
                                        default => 'text',
                                    };
                                @endphp
                                <input type="{{ $htmlType }}" name="payload[{{ $key }}]" value="{{ is_scalar($currentValue) ? (string) $currentValue : '' }}" style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;font-size:0.85rem;" @if (!empty($field['required'])) required @endif>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div style="margin-top:0.85rem;margin-bottom:0.35rem;">
                <label style="display:block;font-weight:600;margin-bottom:0.25rem;font-size:0.9rem;">
                    Documents
                    <span style="font-weight:400;color:#71717a;">(max 3, PDF or image, 5 MB each)</span>
                    @if ($case->service?->requires_document)
                        <span style="color:#b91c1c">*</span>
                    @endif
                </label>
                <p style="margin:0 0 0.45rem;font-size:0.76rem;color:#64748b;">
                    View existing files below. Tick <strong>Remove</strong> to delete, then upload replacements if needed.
                </p>
                <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf" style="font-size:0.85rem;">
            </div>

            @if ($isReapSupportService ?? false)
                @include('staff.services.partials.through-reap-dedicated-field', [
                    'payload' => $payload,
                    'case' => $case,
                    'reapTargetsProgress' => $reapTargetsProgress ?? null,
                ])
            @elseif ($isConvergenceService ?? false)
                @include('staff.services.partials.through-reap-field', [
                    'payload' => $payload,
                    'case' => $case,
                    'reapTargetsProgress' => $reapTargetsProgress ?? null,
                ])
            @endif
        </fieldset>

        @if ($case->attachments->isNotEmpty())
            @php
                $oldRemoveIds = collect(old('remove_attachment_ids', []))->map(fn ($id) => (int) $id)->all();
                $reapDocName = trim((string) ($payload[\App\Support\ConvergenceReapSupport::REAP_DOCUMENT_KEY] ?? ''));
            @endphp
            <div style="margin-bottom:0.9rem;background:#fff;border:1px solid #e4e4e7;border-radius:8px;padding:0.65rem 0.75rem;">
                <p style="margin:0 0 0.45rem;font-size:0.82rem;font-weight:600;">Existing attachments</p>
                @foreach ($case->attachments as $att)
                    @php
                        $isReapDoc = $reapDocName !== '' && strcasecmp((string) $att->original_name, $reapDocName) === 0;
                        $isMarked = in_array((int) $att->id, $oldRemoveIds, true);
                    @endphp
                    <div class="svc-att-row{{ $isMarked ? ' is-marked-remove' : '' }}" data-att-row>
                        <div class="svc-att-name">
                            {{ $att->original_name }}
                            @if ($isReapDoc)
                                <span style="display:inline-block;margin-left:0.25rem;padding:0.08rem 0.4rem;border-radius:999px;background:#ffedd5;color:#9a3412;font-size:0.68rem;font-weight:700;">REAP</span>
                            @endif
                            <div class="svc-att-meta">{{ number_format((int) ($att->size_bytes / 1024), 0) }} KB</div>
                        </div>
                        <button
                            type="button"
                            class="svc-doc-btn js-doc-open"
                            data-doc-url="{{ route('staff.services.attachments.download', [$case, $att]) }}"
                            data-doc-name="{{ $att->original_name }}"
                        >View</button>
                        <label class="svc-att-remove">
                            <input
                                type="checkbox"
                                name="remove_attachment_ids[]"
                                value="{{ $att->id }}"
                                @checked($isMarked)
                                data-remove-att
                            >
                            Remove
                        </label>
                    </div>
                @endforeach
            </div>
        @endif

        <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.55rem 1.1rem;border-radius:8px;font-weight:600;cursor:pointer;">Update case</button>
    </form>

    <div id="svcDocModal" class="svc-doc-modal" aria-hidden="true">
        <div class="svc-doc-modal__card" role="dialog" aria-modal="true" aria-label="Document preview">
            <div class="svc-doc-modal__head">
                <div id="svcDocTitle" class="svc-doc-modal__title">Document</div>
                <button type="button" id="svcDocClose" class="svc-doc-modal__close">Close</button>
            </div>
            <div id="svcDocBody" class="svc-doc-modal__body"></div>
        </div>
    </div>

    <script>
        (function () {
            const form = document.querySelector('form[action*="services/"]');
            if (!form) return;
            const rows = Array.from(form.querySelectorAll('.svc-field-row'));
            const reapCheckbox = form.querySelector('input[name="payload[through_reap]"][type="checkbox"]');
            const reapDetailsWrap = document.getElementById('reap_details_wrap');
            const reapAlwaysOn = !reapCheckbox && !!form.querySelector('input[name="payload[through_reap]"][value="1"]');

            function payloadValue(key) {
                const els = form.querySelectorAll('[name="payload[' + key + ']"], [name="payload[' + key + '][]"]');
                if (!els.length) return '';
                const checkbox = Array.from(els).find(function (el) { return el.type === 'checkbox'; });
                if (checkbox) return checkbox.checked ? '1' : '0';
                const first = els[0];
                if (first.type === 'radio') {
                    const checked = Array.from(els).find(el => el.checked);
                    return checked ? checked.value : '';
                }
                if (first.tagName === 'SELECT' && first.multiple) {
                    return Array.from(first.selectedOptions).map(o => o.value);
                }
                return first.value || '';
            }

            function syncReapDetails() {
                if (!reapDetailsWrap) return;
                const show = reapAlwaysOn || (reapCheckbox && reapCheckbox.checked);
                reapDetailsWrap.style.display = show ? 'flex' : 'none';
                reapDetailsWrap.querySelectorAll('input,select,textarea').forEach(function (el) {
                    if (!show) {
                        el.dataset.wasRequired = (el.dataset.reapRequired === '1' || el.required) ? '1' : '0';
                        el.required = false;
                        el.disabled = true;
                        if (el.type === 'file') {
                            el.value = '';
                        } else if (el.tagName !== 'SELECT') {
                            el.value = '';
                        } else {
                            el.selectedIndex = 0;
                        }
                    } else {
                        el.disabled = false;
                        if (el.dataset.reapRequired === '1' || el.dataset.wasRequired === '1') {
                            if (el.type === 'file') {
                                const hasCurrent = el.parentElement && el.parentElement.querySelector('p') && (el.parentElement.textContent || '').indexOf('Current:') >= 0;
                                el.required = !hasCurrent;
                            } else {
                                el.required = true;
                            }
                        }
                    }
                });
            }

            function syncVisibility() {
                rows.forEach(function (row) {
                    const depField = row.getAttribute('data-visible-if-field') || '';
                    const depValue = row.getAttribute('data-visible-if-value') || '';
                    let show = true;
                    if (depField && depValue) {
                        const actual = payloadValue(depField);
                        show = Array.isArray(actual) ? actual.indexOf(depValue) >= 0 : String(actual) === depValue;
                    }
                    row.style.display = show ? '' : 'none';
                    row.querySelectorAll('input,select,textarea').forEach(function (el) {
                        if (!show) {
                            el.dataset.wasRequired = el.required ? '1' : '0';
                            el.required = false;
                            el.disabled = true;
                        } else {
                            el.disabled = false;
                            if (el.dataset.wasRequired === '1') el.required = true;
                        }
                    });
                });
            }

            form.querySelectorAll('[data-remove-att]').forEach(function (cb) {
                cb.addEventListener('change', function () {
                    const row = cb.closest('[data-att-row]');
                    if (row) row.classList.toggle('is-marked-remove', cb.checked);
                });
            });

            if (reapCheckbox) {
                reapCheckbox.addEventListener('change', syncReapDetails);
                syncReapDetails();
            } else if (reapAlwaysOn) {
                syncReapDetails();
            }
            form.addEventListener('input', syncVisibility);
            form.addEventListener('change', syncVisibility);
            syncVisibility();
        })();

        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('svcDocModal');
            var modalBody = document.getElementById('svcDocBody');
            var modalTitle = document.getElementById('svcDocTitle');
            var closeBtn = document.getElementById('svcDocClose');

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                modalBody.innerHTML = '';
            }

            function openModal(url, name) {
                modalTitle.textContent = name || 'Document';
                modalBody.innerHTML = '';

                var lower = (name || url || '').toLowerCase();
                if (lower.endsWith('.pdf')) {
                    var frame = document.createElement('iframe');
                    frame.className = 'svc-doc-modal__frame';
                    frame.src = url;
                    frame.title = name || 'Document';
                    modalBody.appendChild(frame);
                } else if (/\.(png|jpg|jpeg|webp|gif)$/i.test(lower)) {
                    var img = document.createElement('img');
                    img.className = 'svc-doc-modal__img';
                    img.alt = name || 'Document image';
                    img.src = url;
                    modalBody.appendChild(img);
                } else {
                    var fallback = document.createElement('div');
                    fallback.style.fontSize = '0.86rem';
                    fallback.style.color = '#334155';
                    fallback.innerHTML = 'Preview not supported for this file type. <a href="' + url + '" target="_blank" rel="noopener">Open document</a>.';
                    modalBody.appendChild(fallback);
                }

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            }

            document.querySelectorAll('.js-doc-open').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openModal(btn.getAttribute('data-doc-url') || '', btn.getAttribute('data-doc-name') || 'Document');
                });
            });

            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeModal();
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });
        });
    </script>
    @if (! empty($reapTargetsProgress))
        @include('partials.reap-incubatee-targets-panel-script')
    @endif
@endsection

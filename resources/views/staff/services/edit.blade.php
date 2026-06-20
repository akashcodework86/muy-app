@extends('layouts.admin')

@section('title', 'Edit service case')
@section('heading', 'Edit service case')

@section('content')
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
                <input type="date" id="delivered_on" name="delivered_on" value="{{ old('delivered_on', optional($case->delivered_on)->format('Y-m-d')) }}" style="padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;">
            </div>
        @endif

        <fieldset style="margin:0 0 1rem;padding:0.75rem 0.9rem;border:1px solid #e4e4e7;border-radius:8px;">
            <legend style="font-size:0.85rem;font-weight:600;">Service details</legend>
            @if ($schema === [] && ! ($isConvergenceService ?? false))
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
                                <input type="file" name="payload_files[{{ $key }}]" accept=".pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf" style="font-size:0.85rem;">
                                @if (!empty($payload[$key]))
                                    <p style="margin:0.2rem 0 0;font-size:0.74rem;color:#71717a;">Current: {{ (string) $payload[$key] }}</p>
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
            @if ($case->service?->requires_document)
                <div style="margin-top:0.65rem;margin-bottom:0.65rem;">
                    <label style="display:block;font-weight:600;margin-bottom:0.25rem;font-size:0.9rem;">Documents <span style="font-weight:400;color:#71717a;">(max 3, PDF or image, 5 MB each)</span></label>
                    <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf" style="font-size:0.85rem;">
                </div>
            @endif
            @if ($isConvergenceService ?? false)
                @include('staff.services.partials.through-reap-field', ['payload' => $payload])
            @endif
        </fieldset>

        @if ($case->attachments->isNotEmpty())
            <div style="margin-bottom:0.9rem;background:#fff;border:1px solid #e4e4e7;border-radius:8px;padding:0.65rem 0.75rem;">
                <p style="margin:0 0 0.35rem;font-size:0.82rem;font-weight:600;">Existing attachments</p>
                <ul style="margin:0;padding-left:1rem;font-size:0.8rem;color:#52525b;">
                    @foreach ($case->attachments as $att)
                        <li>{{ $att->original_name }} ({{ number_format((int) ($att->size_bytes / 1024), 0) }} KB)</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.55rem 1.1rem;border-radius:8px;font-weight:600;cursor:pointer;">Update case</button>
    </form>
    <script>
        (function () {
            const form = document.querySelector('form[action*="staff/services"]');
            if (!form) return;
            const rows = Array.from(form.querySelectorAll('.svc-field-row'));
            const reapCheckbox = form.querySelector('input[name="payload[through_reap]"][type="checkbox"]');
            const reapDetailsWrap = document.getElementById('reap_details_wrap');

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
                const show = reapCheckbox && reapCheckbox.checked;
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

            if (reapCheckbox) {
                reapCheckbox.addEventListener('change', syncReapDetails);
                syncReapDetails();
            }
            form.addEventListener('input', syncVisibility);
            form.addEventListener('change', syncVisibility);
            syncVisibility();
        })();
    </script>
@endsection


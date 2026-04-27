@php
    /** @var \App\Models\CfaSubmission $submission */
    use App\Models\ServiceCase;
    use App\Support\SchemaValueFormatter;
    use App\Support\ServiceFieldTypes;

    $submission = $serviceCasesUi['submission'];
    $cases = $serviceCasesUi['cases'];
    $pickerServices = $serviceCasesUi['pickerServices'];
@endphp

<section class="cfa-print-section" style="margin-top:1.25rem;">
    <h2>Service delivery (cases)</h2>
    <p class="cfa-print-sub" style="margin-bottom:0.75rem;">Add a draft case, then <strong>submit</strong> it with details and documents (if required). <strong>Approved</strong> cases count toward service achievement. Single-instance services allow only one active row per incubatee.</p>

    @if (session('status'))
        <p style="color:#166534; font-size:0.9rem; margin:0 0 0.75rem;">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul style="color:#b91c1c; font-size:0.85rem; margin:0 0 0.75rem; padding-left:1.2rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    @if ($pickerServices->isEmpty())
        <p style="font-size:0.88rem; color:#71717a;">No catalog services yet. Ask a <strong>state admin</strong> to open <strong>Admin → Service catalog</strong> and add category → service.</p>
    @else
        <form method="post" action="{{ route('staff.applications.service-cases.store', $submission) }}" style="margin-bottom:1.25rem; display:flex; flex-wrap:wrap; gap:0.5rem; align-items:flex-end;">
            @csrf
            <div>
                <label for="service_id" style="display:block; font-size:0.82rem; font-weight:500; margin-bottom:0.2rem;">Add case — service</label>
                <select id="service_id" name="service_id" required style="min-width:18rem; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                    @foreach ($pickerServices as $svc)
                        <option value="{{ $svc->id }}">
                            {{ $svc->category?->name ?? '?' }} — {{ $svc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.45rem 0.85rem; border-radius:6px; font-size:0.88rem;">Add draft case</button>
        </form>
    @endif

    @if ($cases->isEmpty())
        <p style="font-size:0.88rem; color:#71717a;">No service cases yet.</p>
    @else
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #e4e4e7; border-radius:8px; font-size:0.85rem;">
                <thead>
                    <tr style="background:#fafafa; text-align:left;">
                        <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Service</th>
                        <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Status</th>
                        <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Reference</th>
                        <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Updated</th>
                        <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cases as $case)
                        @php
                            $svc = $case->service;
                            $schema = ServiceFieldTypes::normalizeSchema($svc?->field_schema ?? []);
                            $payload = is_array($case->payload) ? $case->payload : [];
                        @endphp
                        <tr>
                            <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; vertical-align:top;">
                                <strong>{{ $svc?->name ?? '—' }}</strong>
                                @if ($schema !== [] && $payload !== [])
                                    <div style="font-size:0.78rem; color:#52525b; margin-top:0.25rem;">
                                        @foreach ($schema as $field)
                                            @php $k = $field['key']; @endphp
                                            @if (array_key_exists($k, $payload))
                                                <div><strong>{{ $field['label'] }}:</strong> {!! SchemaValueFormatter::renderHtml($field, $payload[$k]) !!}</div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ str_replace('_', ' ', $case->status) }}</td>
                            <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $case->reference_number ?: '—' }}</td>
                            <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; white-space:nowrap;">{{ $case->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; vertical-align:top;">
                                @if (in_array($case->status, [ServiceCase::STATUS_DRAFT, ServiceCase::STATUS_SENT_BACK], true))
                                    <form method="post" action="{{ route('staff.applications.service-cases.complete', [$submission, $case]) }}" enctype="multipart/form-data" style="max-width:22rem;">
                                        @csrf
                                        @method('PATCH')
                                        <div style="margin-bottom:0.35rem;">
                                            <label style="font-size:0.78rem;">Reference / certificate no.</label>
                                            <input type="text" name="reference_number" value="{{ old('reference_number') }}" maxlength="191" style="width:100%; padding:0.35rem 0.45rem; border:1px solid #d4d4d8; border-radius:4px; font-size:0.82rem;">
                                        </div>
                                        @if ($svc && ! $svc->requires_approval)
                                            <div style="margin-bottom:0.35rem;">
                                                <label style="font-size:0.78rem;">Delivered on</label>
                                                <input type="date" name="delivered_on" value="{{ old('delivered_on') }}" style="width:100%; padding:0.35rem 0.45rem; border:1px solid #d4d4d8; border-radius:4px; font-size:0.82rem;">
                                            </div>
                                        @endif
                                        @foreach ($schema as $row)
                                            @if (! empty($row['key']))
                                                @php
                                                    $k = $row['key'];
                                                    $label = $row['label'] ?? $k;
                                                    $type = $row['type'] ?? 'text';
                                                @endphp
                                                <div style="margin-bottom:0.35rem;">
                                                    <label style="font-size:0.78rem;">{{ $label }} @if (! empty($row['required']))<span style="color:#b91c1c">*</span>@endif</label>
                                                    @if ($type === 'textarea')
                                                        <textarea name="payload[{{ $k }}]" rows="2" style="width:100%; padding:0.35rem 0.45rem; border:1px solid #d4d4d8; border-radius:4px; font-size:0.82rem;">{{ old('payload.'.$k) }}</textarea>
                                                    @elseif ($type === 'select' && ! empty($row['options']))
                                                        <select name="payload[{{ $k }}]" style="width:100%; padding:0.35rem 0.45rem; border:1px solid #d4d4d8; border-radius:4px; font-size:0.82rem;">
                                                            <option value="">—</option>
                                                            @foreach ($row['options'] as $opt)
                                                                @php $ov = is_array($opt) ? ($opt['value'] ?? '') : $opt; $ol = is_array($opt) ? ($opt['label'] ?? $ov) : $opt; @endphp
                                                                <option value="{{ $ov }}" @selected(old('payload.'.$k) == $ov)>{{ $ol }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif ($type === 'radio' && ! empty($row['options']))
                                                        <div style="display:flex;flex-wrap:wrap;gap:0.65rem;">
                                                            @foreach ($row['options'] as $opt)
                                                                @php $ov = is_array($opt) ? ($opt['value'] ?? '') : $opt; $ol = is_array($opt) ? ($opt['label'] ?? $ov) : $opt; @endphp
                                                                <label style="display:inline-flex;align-items:center;gap:0.25rem;font-size:0.82rem;">
                                                                    <input type="radio" name="payload[{{ $k }}]" value="{{ $ov }}" @checked(old('payload.'.$k) == $ov)>
                                                                    {{ $ol }}
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    @elseif ($type === 'multiselect' && ! empty($row['options']))
                                                        <select name="payload[{{ $k }}][]" multiple size="4" style="width:100%; padding:0.35rem 0.45rem; border:1px solid #d4d4d8; border-radius:4px; font-size:0.82rem;">
                                                            @foreach ($row['options'] as $opt)
                                                                @php $ov = is_array($opt) ? ($opt['value'] ?? '') : $opt; $ol = is_array($opt) ? ($opt['label'] ?? $ov) : $opt; @endphp
                                                                <option value="{{ $ov }}">{{ $ol }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif ($type === 'checkbox')
                                                        <label style="display:flex;align-items:center;gap:0.25rem;font-size:0.82rem;"><input type="checkbox" name="payload[{{ $k }}]" value="1" @checked(old('payload.'.$k))> Yes</label>
                                                    @else
                                                        @php
                                                            $inputType = match ($type) {
                                                                'number', 'amount' => 'number',
                                                                'date' => 'date',
                                                                'email' => 'email',
                                                                'url' => 'url',
                                                                'phone' => 'tel',
                                                                default => 'text',
                                                            };
                                                        @endphp
                                                        <input type="{{ $inputType }}" name="payload[{{ $k }}]" value="{{ old('payload.'.$k) }}" style="width:100%; padding:0.35rem 0.45rem; border:1px solid #d4d4d8; border-radius:4px; font-size:0.82rem;">
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                        @if ($svc?->requires_document)
                                            <div style="margin-bottom:0.35rem;">
                                                <label style="font-size:0.78rem;">Documents (max 3, PDF/image, 5 MB)</label>
                                                <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" style="font-size:0.78rem;">
                                            </div>
                                        @endif
                                        <button type="submit" style="margin-top:0.25rem; background:#166534; color:#fff; border:none; padding:0.35rem 0.65rem; border-radius:4px; font-size:0.82rem;">Submit case</button>
                                    </form>
                                @elseif ($case->status === ServiceCase::STATUS_PENDING_APPROVAL)
                                    <span style="color:#92400e; font-size:0.8rem;">Pending SPOC approval @if ($case->sla_deadline_at) (SLA {{ $case->sla_deadline_at->timezone(config('app.timezone'))->format('d M') }}) @endif</span>
                                @elseif ($case->status === ServiceCase::STATUS_APPROVED)
                                    <span style="color:#166534; font-size:0.8rem;">Approved@if ($case->delivered_on) · {{ $case->delivered_on->format('d M Y') }}@elseif ($case->approved_at) · {{ $case->approved_at->timezone(config('app.timezone'))->format('d M Y') }}@endif</span>
                                @else
                                    <span style="color:#71717a;">{{ str_replace('_', ' ', $case->status) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

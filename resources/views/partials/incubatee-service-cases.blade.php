@php
    /** @var \App\Models\CfaSubmission $submission */
    $submission = $serviceCasesUi['submission'];
    $cases = $serviceCasesUi['cases'];
    $pickerServices = $serviceCasesUi['pickerServices'];
@endphp

<section class="cfa-print-section" style="margin-top:1.25rem;">
    <h2>Service delivery (cases)</h2>
    <p class="cfa-print-sub" style="margin-bottom:0.75rem;">Add a case for this incubatee (CFA). <strong>Completed</strong> cases count toward service achievement. Single-instance services allow only one open/completed row per incubatee.</p>

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
        <p style="font-size:0.88rem; color:#71717a;">No catalog services under subcategories yet. Ask a <strong>state admin</strong> to open <strong>Admin → Service catalog</strong> and add category → subcategory → service.</p>
    @else
        <form method="post" action="{{ route('staff.applications.service-cases.store', $submission) }}" style="margin-bottom:1.25rem; display:flex; flex-wrap:wrap; gap:0.5rem; align-items:flex-end;">
            @csrf
            <div>
                <label for="service_id" style="display:block; font-size:0.82rem; font-weight:500; margin-bottom:0.2rem;">Add case — service</label>
                <select id="service_id" name="service_id" required style="min-width:18rem; padding:0.45rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px;">
                    @foreach ($pickerServices as $svc)
                        <option value="{{ $svc->id }}">
                            {{ $svc->category?->parent?->name ?? '?' }} → {{ $svc->category?->name ?? '?' }} — {{ $svc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.45rem 0.85rem; border-radius:6px; font-size:0.88rem;">Add case</button>
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
                            $schema = is_array($svc?->field_schema) ? $svc->field_schema : [];
                        @endphp
                        <tr>
                            <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; vertical-align:top;">
                                <strong>{{ $svc?->name ?? '—' }}</strong>
                                @if ($case->payload && count($case->payload))
                                    <div style="font-size:0.78rem; color:#52525b; margin-top:0.25rem;">
                                        @foreach ($case->payload as $k => $v)
                                            <div><code>{{ $k }}</code>: {{ is_scalar($v) ? (string) $v : '—' }}</div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $case->status }}</td>
                            <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $case->reference_number ?: '—' }}</td>
                            <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; white-space:nowrap;">{{ $case->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; vertical-align:top;">
                                @if ($case->status === \App\Models\ServiceCase::STATUS_OPEN)
                                    <form method="post" action="{{ route('staff.applications.service-cases.complete', [$submission, $case]) }}" style="max-width:22rem;">
                                        @csrf
                                        @method('PATCH')
                                        <div style="margin-bottom:0.35rem;">
                                            <label style="font-size:0.78rem;">Reference / certificate no.</label>
                                            <input type="text" name="reference_number" value="{{ old('reference_number') }}" maxlength="191" style="width:100%; padding:0.35rem 0.45rem; border:1px solid #d4d4d8; border-radius:4px; font-size:0.82rem;">
                                        </div>
                                        @foreach ($schema as $row)
                                            @if (is_array($row) && ! empty($row['key']))
                                                @php $k = $row['key']; $label = $row['label'] ?? $k; $type = strtolower((string) ($row['type'] ?? 'text')); @endphp
                                                <div style="margin-bottom:0.35rem;">
                                                    <label style="font-size:0.78rem;">{{ $label }}</label>
                                                    @if ($type === 'select' && ! empty($row['options']) && is_array($row['options']))
                                                        <select name="payload[{{ $k }}]" style="width:100%; padding:0.35rem 0.45rem; border:1px solid #d4d4d8; border-radius:4px; font-size:0.82rem;">
                                                            <option value="">—</option>
                                                            @foreach ($row['options'] as $opt)
                                                                @if (is_string($opt))
                                                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <input type="text" name="payload[{{ $k }}]" value="{{ old('payload.'.$k) }}" style="width:100%; padding:0.35rem 0.45rem; border:1px solid #d4d4d8; border-radius:4px; font-size:0.82rem;">
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                        <button type="submit" style="margin-top:0.25rem; background:#166534; color:#fff; border:none; padding:0.35rem 0.65rem; border-radius:4px; font-size:0.82rem;">Mark completed</button>
                                    </form>
                                @elseif ($case->status === \App\Models\ServiceCase::STATUS_COMPLETED)
                                    <span style="color:#166534; font-size:0.8rem;">Completed{{ $case->completed_at ? ' '.$case->completed_at->timezone(config('app.timezone'))->format('d M Y') : '' }}</span>
                                @else
                                    <span style="color:#71717a;">{{ $case->status }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

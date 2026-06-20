@php
    use App\Support\ConvergenceReapSupport;
    $throughReapValue = old('payload.through_reap', $payload[ConvergenceReapSupport::PAYLOAD_KEY] ?? null);
    $throughReapChecked = ConvergenceReapSupport::payloadValueIsThroughReap($throughReapValue);
    $reapSchema = ConvergenceReapSupport::reapDetailSchema();
@endphp
<div id="reap_details_wrap" style="display:{{ $throughReapChecked ? 'flex' : 'none' }};flex-direction:column;gap:0.65rem;margin-top:0.75rem;padding-top:0.65rem;border-top:1px solid #fed7aa;">
    @foreach ($reapSchema as $field)
        @php
            $key = $field['key'];
            $type = $field['type'] ?? 'text';
            $oldValue = old("payload.$key");
            $currentValue = is_array($oldValue) ? $oldValue : ($oldValue ?? ($payload[$key] ?? null));
        @endphp
        <div class="reap-detail-row">
            <label style="display:block;font-size:0.82rem;font-weight:600;margin-bottom:0.2rem;">
                {{ $field['label'] }}
                <span style="color:#b91c1c">*</span>
            </label>

            @if ($type === 'textarea')
                <textarea
                    name="payload[{{ $key }}]"
                    rows="3"
                    data-reap-required="1"
                    style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;font-size:0.85rem;"
                    @if ($throughReapChecked) required @endif
                    @if (! $throughReapChecked) disabled @endif
                >{{ is_scalar($currentValue) ? (string) $currentValue : '' }}</textarea>
            @elseif ($type === 'select')
                <select
                    name="payload[{{ $key }}]"
                    data-reap-required="1"
                    style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;"
                    @if ($throughReapChecked) required @endif
                    @if (! $throughReapChecked) disabled @endif
                >
                    <option value="">—</option>
                    @foreach (($field['options'] ?? []) as $opt)
                        @php
                            $ov = (string) ($opt['value'] ?? '');
                            $ol = (string) ($opt['label'] ?? $ov);
                        @endphp
                        <option value="{{ $ov }}" @selected((string) $currentValue === $ov)>{{ $ol }}</option>
                    @endforeach
                </select>
            @elseif ($type === 'file')
                <input
                    type="file"
                    name="payload_files[{{ $key }}]"
                    data-reap-required="1"
                    style="width:100%;padding:0.35rem 0.45rem;border:1px solid #d4d4d8;border-radius:6px;background:#fff;font-size:0.82rem;"
                    @if ($throughReapChecked && empty($payload[$key])) required @endif
                    @if (! $throughReapChecked) disabled @endif
                >
                @if (!empty($payload[$key]))
                    <p style="margin:0.2rem 0 0;font-size:0.74rem;color:#71717a;">Current: {{ (string) $payload[$key] }}</p>
                @endif
                <p style="margin:0.2rem 0 0;font-size:0.75rem;color:#71717a;">Any document type, max 5 MB.</p>
            @endif
        </div>
    @endforeach
</div>

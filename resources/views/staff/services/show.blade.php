@extends('layouts.admin')

@section('title', 'Service case')
@section('heading', 'Service case')

@section('content')
    <p style="margin:0 0 1rem;">
        <a href="{{ route('staff.services.index') }}">← Service cases</a>
    </p>

    @php
        use App\Support\SchemaValueFormatter;
        use App\Support\ServiceFieldTypes;
        $schema = ServiceFieldTypes::normalizeSchema($case->service?->field_schema ?? []);
        $payload = is_array($case->payload) ? $case->payload : [];
    @endphp

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:44rem;margin-bottom:1rem;">
        <h2 style="margin:0 0 0.35rem;font-size:1.05rem;">{{ $case->service?->name ?? 'Service' }}</h2>
        <p style="margin:0;font-size:0.85rem;color:#52525b;">
            <strong>Status:</strong> {{ str_replace('_', ' ', $case->status) }}
            @if ($case->reference_number)
                · <strong>Ref:</strong> {{ $case->reference_number }}
            @endif
        </p>
        @if ($case->delivered_on)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#52525b;"><strong>Delivered on:</strong> {{ $case->delivered_on->format('d M Y') }}</p>
        @endif
        @if ($case->sla_deadline_at)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#52525b;"><strong>SPOC SLA target:</strong> {{ $case->sla_deadline_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</p>
        @endif
    </div>

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:44rem;margin-bottom:1rem;">
        <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Incubatee</h3>
        <p style="margin:0;font-size:0.88rem;"><strong>{{ $case->cfaSubmission?->applicant_name ?? '—' }}</strong></p>
        @if ($case->cfaSubmission?->application_no)
            <p style="margin:0.25rem 0 0;font-size:0.82rem;color:#52525b;">{{ $case->cfaSubmission->application_no }}</p>
        @endif
    </div>

    @if ($schema !== [])
        <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:44rem;margin-bottom:1rem;">
            <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Submitted details</h3>
            <dl style="margin:0;display:grid;gap:0.5rem;">
                @foreach ($schema as $field)
                    @php $k = $field['key']; @endphp
                    <div>
                        <dt style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:#64748b;font-weight:700;">{{ $field['label'] }}</dt>
                        <dd style="margin:0.15rem 0 0;font-size:0.88rem;">{!! SchemaValueFormatter::renderHtml($field, $payload[$k] ?? null) !!}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    @if ($case->attachments->isNotEmpty())
        <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:44rem;margin-bottom:1rem;">
            <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Attachments</h3>
            <ul style="margin:0;padding-left:1.1rem;font-size:0.85rem;">
                @foreach ($case->attachments as $att)
                    <li>{{ $att->original_name }} <span style="color:#71717a;">({{ number_format((int) ($att->size_bytes / 1024), 0) }} KB)</span></li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($case->canBeDeletedByStaff())
        <form method="post" action="{{ route('staff.services.destroy', $case) }}" onsubmit="return confirm('Delete this case?');" style="margin-top:0.5rem;">
            @csrf
            @method('DELETE')
            <button type="submit" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:0.45rem 0.85rem;border-radius:8px;font-weight:600;cursor:pointer;">Delete case</button>
        </form>
    @endif
@endsection

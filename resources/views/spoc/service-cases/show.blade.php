@extends('layouts.admin')

@section('title', 'SPOC case review')
@section('heading', 'Case review')

@section('content')
    <p style="margin:0 0 1rem;">
        <a href="{{ route('spoc.service-cases.index') }}">← Back to queue</a>
    </p>

    @if (session('status'))
        <p style="background:#dcfce7;color:#166534;padding:0.5rem 0.75rem;border-radius:6px;font-size:0.88rem;margin:0 0 0.75rem;">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul style="color:#b91c1c;margin:0 0 0.75rem;padding-left:1.2rem;font-size:0.88rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    @php
        use App\Models\ServiceCase;
        use App\Support\SchemaValueFormatter;
        use App\Support\ServiceFieldTypes;
        $schema = ServiceFieldTypes::normalizeSchema($case->service?->field_schema ?? []);
        $payload = is_array($case->payload) ? $case->payload : [];
        $isPending = $case->status === ServiceCase::STATUS_PENDING_APPROVAL;
    @endphp

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
        <h2 style="margin:0 0 0.35rem;font-size:1.05rem;">{{ $case->service?->name ?? 'Service' }}</h2>
        <p style="margin:0;font-size:0.85rem;color:#52525b;">
            <strong>Status:</strong> {{ str_replace('_', ' ', $case->status) }}
            @if ($case->reference_number)
                · <strong>Ref:</strong> {{ $case->reference_number }}
            @endif
            @if ($case->submitter?->name)
                · <strong>Submitted by:</strong> {{ $case->submitter->name }}
            @endif
        </p>
        @if ($case->delivered_on)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#52525b;"><strong>Delivered on:</strong> {{ $case->delivered_on->format('d M Y') }}</p>
        @endif
        @if ($case->sent_back_note)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#9a3412;"><strong>Last send-back note:</strong> {{ $case->sent_back_note }}</p>
        @endif
        @if ($case->rejected_note)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#991b1b;"><strong>Rejection note:</strong> {{ $case->rejected_note }}</p>
        @endif
    </div>

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
        <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Incubatee</h3>
        <p style="margin:0;font-size:0.88rem;"><strong>{{ $case->cfaSubmission?->applicant_name ?? '—' }}</strong></p>
        <p style="margin:0.25rem 0 0;font-size:0.82rem;color:#52525b;">
            {{ $case->cfaSubmission?->application_no ?? '—' }}
            @if ($case->cfaSubmission?->district?->name)
                · {{ $case->cfaSubmission->district->name }}
            @endif
        </p>
    </div>

    @if ($schema !== [])
        <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
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
        <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
            <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Attachments</h3>
            <ul style="margin:0;padding-left:1.1rem;font-size:0.85rem;">
                @foreach ($case->attachments as $att)
                    <li>
                        <a href="{{ route('spoc.service-cases.attachments.download', [$case, $att]) }}" style="color:#4338ca;">
                            {{ $att->original_name }}
                        </a>
                        <span style="color:#71717a;">({{ number_format((int) ($att->size_bytes / 1024), 0) }} KB)</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($isPending)
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:0.75rem;max-width:52rem;">
            <form method="post" action="{{ route('spoc.service-cases.approve', $case) }}" onsubmit="return confirm('Approve this case?');" style="background:#fff;border:1px solid #dcfce7;border-radius:10px;padding:0.8rem;">
                @csrf
                <button type="submit" style="background:#166534;color:#fff;border:none;padding:0.45rem 0.85rem;border-radius:8px;font-weight:600;cursor:pointer;">Approve</button>
                <p style="margin:0.5rem 0 0;font-size:0.78rem;color:#52525b;">Marks case as approved and completed.</p>
            </form>

            <form method="post" action="{{ route('spoc.service-cases.send-back', $case) }}" style="background:#fff;border:1px solid #fed7aa;border-radius:10px;padding:0.8rem;">
                @csrf
                <label for="send_back_note" style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:0.3rem;">Send back note</label>
                <textarea id="send_back_note" name="note" rows="3" required style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;font-size:0.82rem;"></textarea>
                <button type="submit" style="margin-top:0.45rem;background:#9a3412;color:#fff;border:none;padding:0.45rem 0.85rem;border-radius:8px;font-weight:600;cursor:pointer;">Send back</button>
            </form>

            <form method="post" action="{{ route('spoc.service-cases.reject', $case) }}" style="background:#fff;border:1px solid #fecaca;border-radius:10px;padding:0.8rem;">
                @csrf
                <label for="reject_note" style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:0.3rem;">Rejection reason</label>
                <textarea id="reject_note" name="note" rows="3" required style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;font-size:0.82rem;"></textarea>
                <button type="submit" style="margin-top:0.45rem;background:#991b1b;color:#fff;border:none;padding:0.45rem 0.85rem;border-radius:8px;font-weight:600;cursor:pointer;">Reject</button>
            </form>
        </div>
    @endif
@endsection


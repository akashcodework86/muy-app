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

    @php
        $status = (string) $case->status;
        $statusLabel = ucwords(str_replace('_', ' ', $status));
        $statusStyles = [
            'draft' => ['bg' => '#f1f5f9', 'fg' => '#334155', 'bd' => '#cbd5e1'],
            'pending_approval' => ['bg' => '#fef3c7', 'fg' => '#92400e', 'bd' => '#fcd34d'],
            'sent_back' => ['bg' => '#fee2e2', 'fg' => '#991b1b', 'bd' => '#fecaca'],
            'approved' => ['bg' => '#dcfce7', 'fg' => '#166534', 'bd' => '#86efac'],
            'rejected' => ['bg' => '#ffe4e6', 'fg' => '#9f1239', 'bd' => '#fda4af'],
        ][$status] ?? ['bg' => '#f4f4f5', 'fg' => '#3f3f46', 'bd' => '#e4e4e7'];
    @endphp

    <div style="background:linear-gradient(135deg,#ffffff 0%,#f8fafc 100%);border:1px solid #e4e4e7;border-radius:14px;padding:1rem 1.1rem;max-width:48rem;margin-bottom:1rem;box-shadow:0 8px 22px -18px rgba(15,23,42,0.3);">
        <h2 style="margin:0 0 0.4rem;font-size:1.15rem;">{{ $case->service?->name ?? 'Service' }}</h2>
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.45rem;font-size:0.85rem;color:#52525b;">
            <span style="display:inline-flex;align-items:center;padding:0.15rem 0.55rem;border-radius:999px;border:1px solid {{ $statusStyles['bd'] }};background:{{ $statusStyles['bg'] }};color:{{ $statusStyles['fg'] }};font-weight:700;">
                {{ $statusLabel }}
            </span>
            @if ($case->reference_number)
                <span><strong>Ref:</strong> {{ $case->reference_number }}</span>
            @endif
        </div>
        @if ($case->delivered_on)
            <p style="margin:0.45rem 0 0;font-size:0.85rem;color:#52525b;"><strong>Delivered on:</strong> {{ $case->delivered_on->format('d M Y') }}</p>
        @endif
        @if ($case->sla_deadline_at)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#52525b;"><strong>SPOC SLA target:</strong> {{ $case->sla_deadline_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</p>
        @endif
    </div>

    @if ($case->sent_back_note)
        <div style="max-width:48rem;margin-bottom:1rem;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:12px;padding:0.8rem 0.95rem;">
            <p style="margin:0;font-size:0.76rem;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">SPOC send-back remark</p>
            <p style="margin:0.25rem 0 0;font-size:0.9rem;line-height:1.5;">{{ $case->sent_back_note }}</p>
        </div>
    @endif

    @if ($case->rejected_note)
        <div style="max-width:48rem;margin-bottom:1rem;background:#fff1f2;border:1px solid #fecdd3;color:#9f1239;border-radius:12px;padding:0.8rem 0.95rem;">
            <p style="margin:0;font-size:0.76rem;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">SPOC rejection reason</p>
            <p style="margin:0.25rem 0 0;font-size:0.9rem;line-height:1.5;">{{ $case->rejected_note }}</p>
        </div>
    @endif

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:48rem;margin-bottom:1rem;box-shadow:0 8px 22px -18px rgba(15,23,42,0.3);">
        <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Incubatee</h3>
        <p style="margin:0;font-size:0.88rem;"><strong>{{ $case->cfaSubmission?->applicant_name ?? '—' }}</strong></p>
        @if ($case->cfaSubmission?->application_no)
            <p style="margin:0.25rem 0 0;font-size:0.82rem;color:#52525b;">{{ $case->cfaSubmission->application_no }}</p>
        @endif
    </div>

    @if ($schema !== [])
        <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:48rem;margin-bottom:1rem;box-shadow:0 8px 22px -18px rgba(15,23,42,0.3);">
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
        <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:48rem;margin-bottom:1rem;box-shadow:0 8px 22px -18px rgba(15,23,42,0.3);">
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

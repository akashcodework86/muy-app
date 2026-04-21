@extends('layouts.admin')

@section('title', 'Services')
@section('heading', 'Service cases')

@section('content')
    <p style="margin:0 0 0.75rem;">
        <a href="{{ route('staff.services.create') }}" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:0.45rem 0.9rem;border-radius:8px;font-size:0.88rem;font-weight:600;">+ New submission</a>
        <a href="{{ route('staff.applications') }}" style="margin-left:0.5rem;font-size:0.88rem;color:#4338ca;">Applications</a>
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
        $tabs = [
            '' => 'All',
            ServiceCase::STATUS_DRAFT => 'Draft',
            ServiceCase::STATUS_PENDING_APPROVAL => 'Pending approval',
            ServiceCase::STATUS_SENT_BACK => 'Sent back',
            ServiceCase::STATUS_APPROVED => 'Approved',
            ServiceCase::STATUS_REJECTED => 'Rejected',
        ];
    @endphp

    <div style="display:flex;flex-wrap:wrap;gap:0.35rem;margin-bottom:1rem;">
        @foreach ($tabs as $val => $label)
            <a href="{{ route('staff.services.index', $val !== '' ? ['status' => $val] : []) }}"
               style="padding:0.4rem 0.75rem;border-radius:999px;font-size:0.82rem;font-weight:600;text-decoration:none;border:1px solid {{ ($filterStatus === $val) ? '#4f46e5' : '#e4e4e7' }};background:{{ ($filterStatus === $val) ? '#eef2ff' : '#fff' }};color:{{ ($filterStatus === $val) ? '#3730a3' : '#3f3f46' }};">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if ($cases->isEmpty())
        <p style="color:#71717a;font-size:0.9rem;">No cases in this view.</p>
    @else
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:8px;font-size:0.85rem;">
                <thead>
                    <tr style="background:#fafafa;text-align:left;">
                        <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Incubatee</th>
                        <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Service</th>
                        <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Status</th>
                        <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;">Updated</th>
                        <th style="padding:0.5rem 0.65rem;border-bottom:1px solid #e4e4e7;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cases as $case)
                        <tr>
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">
                                <strong>{{ $case->cfaSubmission?->applicant_name ?? '—' }}</strong>
                                @if ($case->cfaSubmission?->application_no)
                                    <div style="font-size:0.75rem;color:#71717a;">{{ $case->cfaSubmission->application_no }}</div>
                                @endif
                            </td>
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">{{ $case->service?->name ?? '—' }}</td>
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">
                                <span style="font-size:0.78rem;font-weight:600;">{{ str_replace('_', ' ', $case->status) }}</span>
                            </td>
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;white-space:nowrap;">{{ $case->updated_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                            <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">
                                <a href="{{ route('staff.services.show', $case) }}" style="font-size:0.82rem;color:#4338ca;">View</a>
                                @if ($case->canBeDeletedByStaff())
                                    <form method="post" action="{{ route('staff.services.destroy', $case) }}" style="display:inline;margin-left:0.5rem;" onsubmit="return confirm('Delete this case?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background:none;border:none;color:#b91c1c;font-size:0.82rem;cursor:pointer;padding:0;">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:0.75rem;">{{ $cases->links() }}</div>
    @endif
@endsection

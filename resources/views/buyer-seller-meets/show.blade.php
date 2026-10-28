@extends('layouts.admin')

@section('title', 'Buyer-Seller Meet')
@section('heading', \App\Models\BuyerSellerMeetEntry::MODULE_LABEL)

@push('styles')
@include('branding-communication.partials.form-styles')
@endpush

@section('content')
<div class="bc-shell">
    @if (session('status'))<div class="bc-alert bc-alert--success">{{ session('status') }}</div>@endif
    <div class="bc-card">
        <p><a href="{{ route($dashboardRoute) }}" class="bc-link">← Back to dashboard</a></p>
        <h3 class="bc-card__title">{{ $row->title }}</h3>
        <dl style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.75rem 1rem; margin:0;">
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Date</dt><dd>{{ $row->meet_date?->format('d M Y') }}</dd></div>
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">District</dt><dd>{{ $row->district?->name ?? '—' }}</dd></div>
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Venue</dt><dd>{{ $row->venue }}</dd></div>
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Total participants</dt><dd>{{ number_format($row->total_participants) }}</dd></div>
            <div><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Submitted by</dt><dd>{{ $row->submitted_by_name }}</dd></div>
            @if ($row->remarks)
            <div style="grid-column:1/-1;"><dt style="font-size:0.72rem;font-weight:700;color:#64748b;">Remarks</dt><dd>{{ $row->remarks }}</dd></div>
            @endif
        </dl>

        @if ($row->participants->isNotEmpty())
            <div style="margin-top:1rem;">
                <p style="font-size:0.82rem;font-weight:700;margin:0 0 0.5rem;">Buyer / seller list</p>
                <table class="bc-table">
                    <thead><tr><th>Role</th><th>Name</th><th>App no.</th><th>Phone</th></tr></thead>
                    <tbody>
                    @foreach ($row->participants as $p)
                        <tr>
                            <td>{{ $p->roleLabel() }}</td>
                            <td>{{ $p->participant_name }}</td>
                            <td>{{ $p->application_no ?: '—' }}</td>
                            <td>{{ $p->phone ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($row->photos->isNotEmpty())
            <div style="margin-top:1rem;">
                <p style="font-size:0.82rem;font-weight:700;margin:0 0 0.5rem;">Photos</p>
                <div class="bc-media-preview">
                    @foreach ($row->photos as $photo)
                        <a href="{{ route($photoRoute, $row) }}?photo={{ $photo->id }}&amp;inline=1" target="_blank" rel="noopener">
                            <img class="bc-media-thumb" src="{{ route($photoRoute, $row) }}?photo={{ $photo->id }}&amp;inline=1" alt="{{ $photo->original_name }}" loading="lazy">
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($row->attendanceFiles->isNotEmpty())
            <div style="margin-top:1rem;">
                <p style="font-size:0.82rem;font-weight:700;margin:0 0 0.5rem;">Attendance sheets</p>
                <ul>
                    @foreach ($row->attendanceFiles as $file)
                        <li><a href="{{ route($attendanceRoute, $row) }}?file={{ $file->id }}" class="bc-link">{{ $file->original_name ?: 'File #'.$file->id }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div style="margin-top:1rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
            @if ($editRoute)
                <a href="{{ route($editRoute, $row) }}" class="bc-btn">Edit entry</a>
            @endif
            @if ($destroyRoute)
                <form method="post" action="{{ route($destroyRoute, $row) }}" onsubmit="return confirm('Delete this meet? Achievement will decrease.');">
                    @csrf @method('DELETE')
                    <button type="submit" class="bc-btn" style="background:#b91c1c;">Delete entry</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection

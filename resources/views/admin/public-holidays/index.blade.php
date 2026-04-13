@extends('layouts.admin')

@section('title', 'Public holidays')

@section('heading', 'Uttarakhand public holidays')

@section('content')
    <p class="admin-page-meta" style="margin-top:-0.25rem;">Same calendar applies to all districts. Sundays and second Saturdays are off automatically — list only additional gazetted / public holidays.</p>
    <p style="margin-bottom:1rem;"><a href="{{ route('admin.holidays.create') }}" style="display:inline-block;padding:0.45rem 0.9rem;background:#4f46e5;color:#fff;border-radius:8px;font-weight:600;text-decoration:none;font-size:0.9rem;">Add holiday</a></p>

    @if ($holidays->isEmpty())
        <p style="color:#64748b;">No holidays in the list yet.</p>
    @else
        <div style="overflow-x:auto;border:1px solid #e2e8f0;border-radius:12px;">
            <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                <thead>
                    <tr style="background:#f8fafc;text-align:left;">
                        <th style="padding:0.65rem 1rem;">Date</th>
                        <th style="padding:0.65rem 1rem;">Name</th>
                        <th style="padding:0.65rem 1rem;width:8rem;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($holidays as $h)
                        <tr style="border-top:1px solid #f1f5f9;">
                            <td style="padding:0.65rem 1rem;">{{ $h->holiday_date->format('d M Y (D)') }}</td>
                            <td style="padding:0.65rem 1rem;">{{ $h->name }}</td>
                            <td style="padding:0.65rem 1rem;">
                                <a href="{{ route('admin.holidays.edit', $h) }}" style="color:#4f46e5;font-weight:600;">Edit</a>
                                <form method="post" action="{{ route('admin.holidays.destroy', $h) }}" style="display:inline;margin-left:0.75rem;" onsubmit="return confirm('Remove this holiday?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background:none;border:none;color:#b91c1c;cursor:pointer;font-weight:600;padding:0;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:1rem;">{{ $holidays->links() }}</div>
    @endif
@endsection

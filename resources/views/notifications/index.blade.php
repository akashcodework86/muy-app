@extends('layouts.admin')

@section('title', 'Notifications')
@section('heading', 'Notifications')

@section('content')
    <div style="max-width:42rem;">
        <p style="margin:0 0 1rem; color:#64748b; font-size:0.9rem;">
            Mentorship and other alerts for your role. Unread items are highlighted. Opening a row marks it read and opens the CFA when linked.
        </p>

        @if ($notifications->isNotEmpty())
            <form method="post" action="{{ route('notifications.read-all') }}" style="margin-bottom:1rem;">
                @csrf
                <button type="submit" style="padding:0.45rem 0.85rem; border-radius:8px; border:1px solid #e2e8f0; background:#fff; font-weight:600; font-size:0.85rem; cursor:pointer; font-family:inherit;">
                    Mark all as read
                </button>
            </form>
        @endif

        <div style="display:flex; flex-direction:column; gap:0.65rem;">
            @forelse ($notifications as $n)
                @php
                    $d = $n->data ?? [];
                    $unread = $n->read_at === null;
                @endphp
                <article style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.1rem; box-shadow:0 4px 14px rgba(15,23,42,0.04); @if($unread) border-left:3px solid #6366f1; @endif">
                    <div style="display:flex; justify-content:space-between; gap:0.75rem; flex-wrap:wrap; align-items:flex-start;">
                        <div style="min-width:0;">
                            <h2 style="margin:0 0 0.35rem; font-size:0.95rem; font-weight:700; color:#0f172a;">{{ $d['title'] ?? 'Notification' }}</h2>
                            <p style="margin:0; font-size:0.88rem; color:#475569; line-height:1.45;">{{ $d['body'] ?? '' }}</p>
                            @if (!empty($d['comment']))
                                <p style="margin:0.65rem 0 0; padding:0.55rem 0.65rem; background:#f8fafc; border-radius:8px; font-size:0.82rem; color:#334155; white-space:pre-wrap;">{{ $d['comment'] }}</p>
                            @endif
                            @if (!empty($d['district_name']) || !empty($d['hub_name']) || !empty($d['application_no']))
                                <p style="margin:0.5rem 0 0; font-size:0.75rem; color:#64748b;">
                                    @if (!empty($d['application_no'])) CFA {{ $d['application_no'] }}@endif
                                    @if (!empty($d['district_name'])) · {{ $d['district_name'] }}@endif
                                    @if (!empty($d['hub_name'])) · {{ $d['hub_name'] }}@endif
                                </p>
                            @endif
                        </div>
                        <div style="flex-shrink:0; text-align:right;">
                            <time datetime="{{ $n->created_at?->toIso8601String() }}" style="font-size:0.72rem; color:#94a3b8;">{{ $n->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</time>
                            <div style="margin-top:0.5rem;">
                                <a href="{{ route('notifications.open', $n->id) }}" style="display:inline-block; padding:0.4rem 0.75rem; border-radius:8px; background:linear-gradient(135deg,#4f46e5,#6366f1); color:#fff; font-size:0.8rem; font-weight:600; text-decoration:none;">Open</a>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <p style="color:#64748b; font-size:0.9rem;">No notifications yet.</p>
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <div style="margin-top:1.25rem;">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
@endsection

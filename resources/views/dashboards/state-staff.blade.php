@extends('layouts.admin')

@section('title', 'SPOC dashboard')
@section('heading', 'State staff (SPOC) dashboard')

@section('content')
    <div style="max-width:56rem;">
        <div style="background:linear-gradient(135deg,#ffffff 0%,#f0fdfa 60%,#eef2ff 100%); border:1px solid rgba(20,184,166,0.25); border-radius:16px; padding:1.5rem; box-shadow:0 12px 30px -16px rgba(79,70,229,0.2);">
            <h2 style="margin:0 0 0.4rem; font-size:1.25rem; color:#0f172a;">Welcome, {{ $user->name }}</h2>
            <p style="margin:0; font-size:0.95rem; color:#475569;">
                You are a <strong>State Staff (SPOC)</strong> — checker for service cases that require maker-checker verification (e.g. GST registration).
            </p>
            <div style="margin-top:1rem; display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:0.6rem;">
                <div style="background:#fff; border:1px solid rgba(148,163,184,0.3); border-radius:12px; padding:0.85rem 1rem;">
                    <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:0.08em; color:#64748b; font-weight:700;">Districts assigned</div>
                    <div style="font-size:1.3rem; font-weight:700; color:#0f172a;">{{ $spocDistricts->count() }}</div>
                    @if ($spocDistricts->isEmpty())
                        <div style="font-size:0.78rem; color:#b45309;">No districts assigned yet. Ask state admin to assign you.</div>
                    @else
                        <div style="margin-top:0.3rem; display:flex; flex-wrap:wrap; gap:0.2rem;">
                            @foreach ($spocDistricts as $d)
                                <span title="{{ $d->hub?->name ? $d->hub->name.' Hub' : '' }}" style="background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe; padding:0.1rem 0.4rem; border-radius:999px; font-size:0.7rem; font-weight:600;">{{ $d->name }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div style="background:#fff; border:1px solid rgba(148,163,184,0.3); border-radius:12px; padding:0.85rem 1rem;">
                    <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:0.08em; color:#64748b; font-weight:700;">Pending approvals</div>
                    <div style="font-size:1.3rem; font-weight:700; color:#0f172a;">{{ number_format((int) ($pendingApprovals ?? 0)) }}</div>
                    <div style="font-size:0.78rem; color:#64748b;">Cases waiting for your decision.</div>
                </div>
                <div style="background:#fff; border:1px solid rgba(148,163,184,0.3); border-radius:12px; padding:0.85rem 1rem;">
                    <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:0.08em; color:#64748b; font-weight:700;">Overdue (3+ biz days)</div>
                    <div style="font-size:1.3rem; font-weight:700; color:#0f172a;">{{ number_format((int) ($overduePending ?? 0)) }}</div>
                    <div style="font-size:0.78rem; color:#64748b;">Pending cases past SLA deadline.</div>
                </div>
                <div style="background:#fff; border:1px solid rgba(148,163,184,0.3); border-radius:12px; padding:0.85rem 1rem;">
                    <div style="font-size:0.7rem; text-transform:uppercase; letter-spacing:0.08em; color:#64748b; font-weight:700;">Approved by you</div>
                    <div style="font-size:1.3rem; font-weight:700; color:#0f172a;">{{ number_format((int) ($approvedByYou ?? 0)) }}</div>
                    <div style="font-size:0.78rem; color:#64748b;">Total approvals completed by you.</div>
                </div>
            </div>
        </div>

        <div style="margin-top:1.25rem; background:#fff; border:1px solid rgba(148,163,184,0.3); border-radius:14px; padding:1.1rem 1.25rem;">
            <h3 style="margin:0 0 0.5rem; font-size:0.95rem; color:#0f172a;">What's next</h3>
            <ul style="margin:0; padding-left:1.1rem; font-size:0.88rem; color:#475569; line-height:1.6;">
                <li>Wait for the state admin to assign you one or more districts on the <em>Service SPOCs</em> page.</li>
                <li>Once services marked <em>Requires approval</em> are submitted by district staff, they will appear in your queue here.</li>
                <li>You will be able to <strong>approve</strong>, <strong>send back</strong> (with a note), or <strong>reject</strong> each case.</li>
            </ul>
        </div>

        <div style="margin-top:1rem; background:#fff; border:1px solid rgba(148,163,184,0.3); border-radius:14px; padding:1.1rem 1.25rem;">
            <h3 style="margin:0 0 0.35rem; font-size:0.95rem; color:#0f172a;">Documents</h3>
            <p style="margin:0 0 0.65rem; font-size:0.86rem; color:#64748b;">Open role-authorized documents uploaded by state admin and teams.</p>
            <a href="{{ route('library.documents.index') }}" style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.45rem 0.75rem;border-radius:8px;background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;text-decoration:none;font-size:0.8rem;font-weight:700;">
                Open document repository
            </a>
        </div>
    </div>
@endsection

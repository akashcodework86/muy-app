@extends('layouts.admin')

@section('title', 'Audit log')
@section('heading', 'Audit log')

@section('content')
    <p style="color:#64748b;font-size:0.9rem;margin:0 0 1rem;">Who changed what (state admin and district staff). CFA application edits show a <strong>plain-language “what changed”</strong> list first; raw JSON is folded under <em>Raw before / after</em>. Passwords are never stored. Action code for staff CFA saves: <code style="font-size:0.8em;">cfa_submission.updated</code>.</p>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:8px;font-size:0.8125rem;">
            <thead>
                <tr style="text-align:left;background:#f8fafc;">
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;white-space:nowrap;">When (IST)</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">User</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Action</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Subject</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;white-space:nowrap;vertical-align:top;">
                            {{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}
                        </td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;vertical-align:top;">
                            {{ $log->user?->name ?? '—' }}<br>
                            <span style="font-size:0.75rem;color:#64748b;">{{ $log->user?->email }}</span>
                        </td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;font-weight:600;vertical-align:top;">{{ $log->action }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;vertical-align:top;">
                            @if ($log->subject_type)
                                <span style="font-size:0.75rem;">{{ class_basename($log->subject_type) }}</span>
                                @if ($log->subject_id)
                                    #{{ $log->subject_id }}
                                @endif
                            @else
                                —
                            @endif
                            @if ($log->ip_address)
                                <br><span style="font-size:0.72rem;color:#94a3b8;">IP {{ $log->ip_address }}</span>
                            @endif
                        </td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;vertical-align:top;max-width:28rem;">
                            @if ($log->action === \App\Services\CfaSubmissionAuditSnapshot::ACTION_UPDATED)
                                @php
                                    $cfaDiffLines = \App\Services\CfaSubmissionAuditSnapshot::humanDiffLines($log->before ?? [], $log->after ?? []);
                                @endphp
                                @if (count($cfaDiffLines) > 0)
                                    <div style="font-size:0.78rem;font-weight:600;color:#334155;margin-bottom:0.25rem;">What changed (plain language)</div>
                                    <ul style="margin:0 0 0.5rem 1rem;padding:0;line-height:1.45;font-size:0.78rem;color:#334155;">
                                        @foreach ($cfaDiffLines as $line)
                                            <li style="margin-bottom:0.2rem;">{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                @elseif ($log->description && ! \Illuminate\Support\Str::contains(strtolower((string) $log->description), 'json'))
                                    <div style="margin-bottom:0.35rem;font-size:0.78rem;">{{ $log->description }}</div>
                                @endif
                            @elseif ($log->description)
                                <div style="margin-bottom:0.35rem;">{{ $log->description }}</div>
                            @endif
                            @if (($log->before && count($log->before)) || ($log->after && count($log->after)))
                                <details style="font-size:0.75rem;">
                                    <summary style="cursor:pointer;color:#64748b;font-weight:600;">Raw before / after (technical)</summary>
                                    <pre style="margin:0.5rem 0 0;white-space:pre-wrap;word-break:break-word;background:#f8fafc;padding:0.5rem;border-radius:6px;border:1px solid #e2e8f0;max-height:14rem;overflow:auto;">@if ($log->before && count($log->before))<strong>before</strong>
{{ json_encode($log->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

@endif
@if ($log->after && count($log->after))<strong>after</strong>
{{ json_encode($log->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
@endif</pre>
                                </details>
                            @elseif (! $log->description && $log->action !== \App\Services\CfaSubmissionAuditSnapshot::ACTION_UPDATED)
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding:1.25rem;color:#64748b;">No audit entries yet. Saving staff, targets, designations, or editing a CFA from the staff portal will create logs.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($logs->hasPages())
        <div style="margin-top:1rem;">{{ $logs->links() }}</div>
    @endif
@endsection

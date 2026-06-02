@extends('layouts.admin')

@section('title', $task->title)
@section('heading', $task->title)

@section('content')
    @php
        $statusClass = match ($task->status) {
            'draft' => 'background:#f4f4f5;color:#52525b;',
            'published' => 'background:#ecfdf5;color:#047857;',
            'closed' => 'background:#eff6ff;color:#1d4ed8;',
            default => 'background:#fef2f2;color:#b91c1c;',
        };
    @endphp

    <style>
        .sts-grid { display:grid; gap:0.85rem; }
        .sts-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:0.85rem; }
        .sts-meta { display:grid; gap:0.45rem; grid-template-columns:repeat(auto-fit,minmax(11rem,1fr)); font-size:0.84rem; }
        .sts-k { font-size:0.72rem; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:700; }
        .sts-v { margin-top:0.12rem; color:#0f172a; font-weight:700; }
        .sts-actions { display:flex; flex-wrap:wrap; gap:0.5rem; }
        .sts-btn { border-radius:8px; padding:0.42rem 0.75rem; font-size:0.82rem; font-weight:700; border:1px solid #d1d5db; background:#fff; cursor:pointer; text-decoration:none; color:#111827; }
        .sts-btn--dark { background:#18181b; color:#fff; border-color:#18181b; }
        .sts-btn--green { background:#047857; color:#fff; border-color:#047857; }
        .sts-btn--danger { background:#fff; color:#b91c1c; border-color:#fecaca; }
        .sts-table-wrap { overflow:auto; }
        .sts-table { width:100%; min-width:860px; border-collapse:collapse; font-size:0.84rem; }
        .sts-table th { text-align:left; padding:0.55rem 0.6rem; border-bottom:1px solid #e5e7eb; background:#f8fafc; color:#64748b; font-size:0.72rem; text-transform:uppercase; }
        .sts-table td { padding:0.55rem 0.6rem; border-bottom:1px solid #f8fafc; vertical-align:top; }
        .sts-pill { display:inline-flex; border-radius:999px; padding:0.12rem 0.48rem; font-size:0.72rem; font-weight:800; border:1px solid #e5e7eb; }
        .sts-note { font-size:0.78rem; color:#64748b; margin-top:0.2rem; white-space:pre-wrap; }
        .sts-inline-form { display:flex; flex-wrap:wrap; gap:0.35rem; align-items:flex-end; margin-top:0.35rem; }
        .sts-inline-form textarea { min-width:14rem; min-height:2.4rem; border:1px solid #d1d5db; border-radius:8px; padding:0.35rem 0.5rem; font-size:0.82rem; }
    </style>

    <div class="sts-grid">
        <div class="sts-card">
            <div class="sts-actions" style="margin-bottom:0.75rem;">
                <a href="{{ route('admin.state-tasks.index') }}" class="sts-btn">← All tasks</a>
                @if ($task->isEditable())
                    <a href="{{ route('admin.state-tasks.edit', $task) }}" class="sts-btn">Edit</a>
                @endif
                @if ($task->status === 'draft')
                    <form method="post" action="{{ route('admin.state-tasks.publish', $task) }}">@csrf<button class="sts-btn sts-btn--green" type="submit">Publish</button></form>
                @endif
                @if ($task->status === 'published')
                    <form method="post" action="{{ route('admin.state-tasks.close', $task) }}" onsubmit="return confirm('Close this task?');">@csrf<button class="sts-btn sts-btn--dark" type="submit">Close task</button></form>
                @endif
                @if (! in_array($task->status, ['closed', 'cancelled'], true))
                    <form method="post" action="{{ route('admin.state-tasks.cancel', $task) }}" onsubmit="return confirm('Cancel this task?');">@csrf<button class="sts-btn sts-btn--danger" type="submit">Cancel</button></form>
                @endif
            </div>

            <div class="sts-meta">
                <div><div class="sts-k">Status</div><div class="sts-v"><span class="sts-pill" style="{{ $statusClass }}">{{ ucfirst($task->status) }}</span></div></div>
                <div><div class="sts-k">Timeline</div><div class="sts-v">{{ optional($task->start_date)->format('d M Y') ?? '—' }} → {{ optional($task->due_date)->format('d M Y') ?? '—' }}</div></div>
                <div><div class="sts-k">Target per assignee</div><div class="sts-v">{{ $task->target_value ? number_format((int) $task->target_value) : '—' }}</div></div>
                <div><div class="sts-k">Created by</div><div class="sts-v">{{ $task->creator?->name ?? '—' }}</div></div>
            </div>

            @if ($task->description)
                <div style="margin-top:0.85rem;font-size:0.88rem;color:#334155;white-space:pre-wrap;">{{ $task->description }}</div>
            @endif

            @if ($task->attachments->isNotEmpty())
                <div style="margin-top:0.85rem;">
                    <div class="sts-k">Attachments</div>
                    <ul style="margin:0.35rem 0 0;padding-left:1.1rem;font-size:0.84rem;">
                        @foreach ($task->attachments as $attachment)
                            <li><a href="{{ route('admin.state-tasks.attachments.download', [$task, $attachment]) }}">{{ $attachment->original_name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="sts-card">
            <h2 style="margin:0 0 0.65rem;font-size:0.95rem;">Assignee progress</h2>
            <div class="sts-table-wrap">
                <table class="sts-table">
                    <thead>
                        <tr>
                            <th>State staff</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Notes</th>
                            <th>Admin action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($task->assignments as $assignment)
                            @php
                                $percent = $task->progressPercentForAssignment($assignment);
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $assignment->assignee?->name ?? '—' }}</strong>
                                    <div class="sts-note">{{ $assignment->assignee?->email }}</div>
                                </td>
                                <td>
                                    <span class="sts-pill">{{ $assignment->statusLabel() }}</span>
                                    @if ($assignment->isOverdue())
                                        <div><span class="sts-pill" style="background:#fff7ed;color:#c2410c;">Overdue</span></div>
                                    @endif
                                </td>
                                <td>
                                    @if ($task->target_value)
                                        {{ number_format((int) $assignment->progress_value) }} / {{ number_format((int) $task->target_value) }}
                                        @if ($percent !== null)
                                            ({{ $percent }}%)
                                        @endif
                                    @else
                                        {{ $assignment->status === 'completed' ? 'Done' : 'In progress' }}
                                    @endif
                                </td>
                                <td>
                                    @if ($assignment->staff_note)
                                        <div><strong>Staff:</strong> <span class="sts-note">{{ $assignment->staff_note }}</span></div>
                                    @endif
                                    @if ($assignment->admin_note)
                                        <div><strong>Admin:</strong> <span class="sts-note">{{ $assignment->admin_note }}</span></div>
                                    @endif
                                </td>
                                <td>
                                    @if ($assignment->status === 'submitted')
                                        <form method="post" action="{{ route('admin.state-tasks.assignments.complete', [$task, $assignment]) }}" class="sts-inline-form">
                                            @csrf
                                            <textarea name="admin_note" placeholder="Optional note"></textarea>
                                            <button type="submit" class="sts-btn sts-btn--green">Mark complete</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.state-tasks.assignments.send-back', [$task, $assignment]) }}" class="sts-inline-form">
                                            @csrf
                                            <textarea name="admin_note" placeholder="Reason for send back" required></textarea>
                                            <button type="submit" class="sts-btn sts-btn--danger">Send back</button>
                                        </form>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @foreach ($task->assignments as $assignment)
            @if ($assignment->progressLogs->isNotEmpty())
                <div class="sts-card">
                    <h3 style="margin:0 0 0.5rem;font-size:0.88rem;">History — {{ $assignment->assignee?->name }}</h3>
                    <ul style="margin:0;padding-left:1.1rem;font-size:0.82rem;color:#334155;">
                        @foreach ($assignment->progressLogs as $log)
                            <li style="margin-bottom:0.35rem;">
                                {{ $log->created_at?->format('d M Y H:i') }} · {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                @if ($log->progress_value !== null) · {{ number_format((int) $log->progress_value) }} @endif
                                @if ($log->user) · {{ $log->user->name }} @endif
                                @if ($log->note)<div class="sts-note">{{ $log->note }}</div>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </div>
@endsection

@extends('layouts.admin')

@section('title', $task->title)
@section('heading', $task->title)

@section('content')
    @php
        $canEdit = $task->status === 'published' && ! in_array($assignment->status, ['submitted', 'completed'], true);
        $percent = $task->progressPercentForAssignment($assignment);
    @endphp

    <style>
        .sst-show { display:grid; gap:0.85rem; max-width:920px; }
        .sst-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:0.85rem; }
        .sst-meta { display:grid; gap:0.45rem; grid-template-columns:repeat(auto-fit,minmax(11rem,1fr)); font-size:0.84rem; }
        .sst-k { font-size:0.72rem; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:700; }
        .sst-v { margin-top:0.12rem; color:#0f172a; font-weight:700; }
        .sst-form { display:grid; gap:0.65rem; margin-top:0.75rem; }
        .sst-form label { font-size:0.78rem; font-weight:700; color:#475569; }
        .sst-form input, .sst-form textarea { width:100%; border:1px solid #d1d5db; border-radius:8px; padding:0.48rem 0.6rem; font-size:0.88rem; }
        .sst-form textarea { min-height:5rem; }
        .sst-btn { border-radius:8px; padding:0.45rem 0.8rem; font-size:0.84rem; font-weight:700; border:1px solid #d1d5db; background:#fff; cursor:pointer; text-decoration:none; color:#111827; display:inline-flex; }
        .sst-btn--primary { background:#18181b; color:#fff; border-color:#18181b; }
        .sst-btn--green { background:#047857; color:#fff; border-color:#047857; }
        .sst-note { background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:8px; padding:0.65rem 0.75rem; font-size:0.84rem; white-space:pre-wrap; }
        .sst-history { font-size:0.82rem; color:#334155; margin:0; padding-left:1.1rem; }
    </style>

    <div class="sst-show">
        <a href="{{ route('spoc.state-tasks.index') }}" class="sst-btn">← My tasks</a>

        <div class="sst-card">
            <div class="sst-meta">
                <div><div class="sst-k">Your status</div><div class="sst-v">{{ $assignment->statusLabel() }}</div></div>
                <div><div class="sst-k">Due date</div><div class="sst-v">{{ optional($task->due_date)->format('d M Y') ?? '—' }}</div></div>
                <div><div class="sst-k">Target</div><div class="sst-v">{{ $task->target_value ? number_format((int) $task->target_value) : 'Completion only' }}</div></div>
                @if ($percent !== null)
                    <div><div class="sst-k">Progress</div><div class="sst-v">{{ $percent }}%</div></div>
                @endif
            </div>

            @if ($task->description)
                <div style="margin-top:0.85rem;font-size:0.88rem;color:#334155;white-space:pre-wrap;">{{ $task->description }}</div>
            @endif

            @if ($task->attachments->isNotEmpty())
                <div style="margin-top:0.85rem;">
                    <div class="sst-k">Reference documents</div>
                    <ul style="margin:0.35rem 0 0;padding-left:1.1rem;font-size:0.84rem;">
                        @foreach ($task->attachments as $attachment)
                            <li><a href="{{ route('spoc.state-tasks.attachments.download', [$task, $attachment->id]) }}">{{ $attachment->original_name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($assignment->admin_note && $assignment->status === 'sent_back')
                <div class="sst-note" style="margin-top:0.85rem;">
                    <strong>Admin feedback:</strong> {{ $assignment->admin_note }}
                </div>
            @endif
        </div>

        @if ($canEdit)
            <div class="sst-card">
                <h2 style="margin:0 0 0.35rem;font-size:0.95rem;">Update progress</h2>
                <form method="post" action="{{ route('spoc.state-tasks.progress', $task) }}" class="sst-form">
                    @csrf
                    @if ($task->target_value)
                        <div>
                            <label for="progress_value">Achieved (out of {{ number_format((int) $task->target_value) }})</label>
                            <input type="number" name="progress_value" id="progress_value" min="0" max="{{ (int) $task->target_value }}" value="{{ old('progress_value', $assignment->progress_value) }}">
                        </div>
                    @endif
                    <div>
                        <label for="staff_note">Notes / remarks</label>
                        <textarea name="staff_note" id="staff_note">{{ old('staff_note', $assignment->staff_note) }}</textarea>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                        <button type="submit" class="sst-btn sst-btn--primary">Save progress</button>
                    </div>
                </form>

                <form method="post" action="{{ route('spoc.state-tasks.submit', $task) }}" class="sst-form" style="margin-top:0.75rem;border-top:1px solid #f1f5f9;padding-top:0.75rem;">
                    @csrf
                    <p style="margin:0;font-size:0.84rem;color:#64748b;">When finished, submit for admin review.</p>
                    <div>
                        <label for="submit_note">Final remarks (optional)</label>
                        <textarea name="staff_note" id="submit_note">{{ old('staff_note', $assignment->staff_note) }}</textarea>
                    </div>
                    <button type="submit" class="sst-btn sst-btn--green" onclick="return confirm('Submit this task for admin review?');">Submit for review</button>
                </form>
            </div>
        @elseif ($assignment->status === 'submitted')
            <div class="sst-card" style="font-size:0.88rem;color:#334155;">Submitted on {{ optional($assignment->submitted_at)->format('d M Y H:i') ?? '—' }}. Waiting for admin review.</div>
        @elseif ($assignment->status === 'completed')
            <div class="sst-card" style="font-size:0.88rem;color:#047857;">Marked complete on {{ optional($assignment->completed_at)->format('d M Y H:i') ?? '—' }}.</div>
        @elseif ($task->status !== 'published')
            <div class="sst-card" style="font-size:0.88rem;color:#64748b;">This task is no longer open for updates.</div>
        @endif

        @if ($assignment->progressLogs->isNotEmpty())
            <div class="sst-card">
                <h3 style="margin:0 0 0.5rem;font-size:0.88rem;">Your update history</h3>
                <ul class="sst-history">
                    @foreach ($assignment->progressLogs as $log)
                        <li style="margin-bottom:0.35rem;">
                            {{ $log->created_at?->format('d M Y H:i') }} · {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                            @if ($log->progress_value !== null) · {{ number_format((int) $log->progress_value) }} @endif
                            @if ($log->note)<div style="color:#64748b;white-space:pre-wrap;">{{ $log->note }}</div>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection

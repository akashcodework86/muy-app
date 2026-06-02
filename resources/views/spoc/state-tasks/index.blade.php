@extends('layouts.admin')

@section('title', 'My tasks')
@section('heading', 'My tasks')

@section('content')
    <style>
        .sst-grid { display:grid; gap:0.85rem; }
        .sst-cards { display:grid; gap:0.65rem; grid-template-columns:repeat(auto-fit,minmax(10rem,1fr)); }
        .sst-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:0.75rem; }
        .sst-k { font-size:0.7rem; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:700; }
        .sst-v { margin-top:0.15rem; font-size:1.25rem; font-weight:800; color:#0f172a; }
        .sst-toolbar { display:flex; flex-wrap:wrap; gap:0.5rem; align-items:flex-end; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:0.65rem 0.75rem; }
        .sst-table-wrap { overflow:auto; background:#fff; border:1px solid #e5e7eb; border-radius:12px; }
        .sst-table { width:100%; min-width:820px; border-collapse:collapse; font-size:0.84rem; }
        .sst-table th { text-align:left; padding:0.55rem 0.65rem; border-bottom:1px solid #e5e7eb; background:#f8fafc; color:#64748b; font-size:0.72rem; text-transform:uppercase; }
        .sst-table td { padding:0.55rem 0.65rem; border-bottom:1px solid #f8fafc; vertical-align:top; }
        .sst-link { color:#3730a3; font-weight:700; text-decoration:none; }
        .sst-pill { display:inline-flex; border-radius:999px; padding:0.12rem 0.48rem; font-size:0.72rem; font-weight:800; border:1px solid #e5e7eb; }
        .sst-btn { border-radius:8px; padding:0.4rem 0.7rem; font-size:0.82rem; font-weight:700; border:1px solid #d1d5db; background:#fff; text-decoration:none; color:#111827; }
    </style>

    <div class="sst-grid">
        <p style="margin:0;font-size:0.88rem;color:#52525b;">Tasks assigned to you by state admin. Update your progress and submit when ready for review.</p>

        <div class="sst-cards">
            <div class="sst-card"><div class="sst-k">Pending</div><div class="sst-v">{{ number_format((int) $stats['pending']) }}</div></div>
            <div class="sst-card"><div class="sst-k">In progress</div><div class="sst-v">{{ number_format((int) $stats['in_progress']) }}</div></div>
            <div class="sst-card"><div class="sst-k">Submitted</div><div class="sst-v">{{ number_format((int) $stats['submitted']) }}</div></div>
            <div class="sst-card"><div class="sst-k">Overdue</div><div class="sst-v">{{ number_format((int) $stats['overdue']) }}</div></div>
        </div>

        <form method="get" class="sst-toolbar">
            <div>
                <label for="status" style="display:block;font-size:0.72rem;font-weight:700;color:#64748b;margin-bottom:0.15rem;">Status</label>
                <select name="status" id="status" style="border:1px solid #d1d5db;border-radius:8px;padding:0.42rem 0.55rem;font-size:0.84rem;min-width:12rem;">
                    <option value="">All</option>
                    @foreach (\App\Models\StateTaskAssignment::STATUSES as $s)
                        <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="sst-btn">Apply</button>
            <a href="{{ route('spoc.state-tasks.index') }}" class="sst-btn">Reset</a>
        </form>

        <div class="sst-table-wrap">
            <table class="sst-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Due</th>
                        <th>Target</th>
                        <th>My progress</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        @php $task = $assignment->task; @endphp
                        <tr>
                            <td>
                                <a href="{{ route('spoc.state-tasks.show', $task) }}" class="sst-link">{{ $task->title }}</a>
                                <div style="font-size:0.74rem;color:#64748b;margin-top:0.1rem;">From {{ $task->creator?->name ?? 'State admin' }}</div>
                            </td>
                            <td>
                                {{ optional($task->due_date)->format('d M Y') ?? '—' }}
                                @if ($assignment->isOverdue())
                                    <div><span class="sst-pill" style="background:#fff7ed;color:#c2410c;">Overdue</span></div>
                                @endif
                            </td>
                            <td>{{ $task->target_value ? number_format((int) $task->target_value) : '—' }}</td>
                            <td>
                                @if ($task->target_value)
                                    {{ number_format((int) $assignment->progress_value) }} / {{ number_format((int) $task->target_value) }}
                                @else
                                    {{ $assignment->statusLabel() }}
                                @endif
                            </td>
                            <td><span class="sst-pill">{{ $assignment->statusLabel() }}</span></td>
                            <td><a href="{{ route('spoc.state-tasks.show', $task) }}" class="sst-btn">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="padding:1rem;color:#64748b;">No tasks assigned yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $assignments->links() }}
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'State tasks')
@section('heading', 'State tasks')

@section('content')
    <style>
        .st-grid { display:grid; gap:0.85rem; }
        .st-toolbar { display:flex; flex-wrap:wrap; gap:0.55rem; align-items:flex-end; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:0.7rem 0.8rem; }
        .st-field label { display:block; font-size:0.72rem; font-weight:700; color:#64748b; margin-bottom:0.18rem; text-transform:uppercase; letter-spacing:.04em; }
        .st-field select, .st-field input[type=checkbox] { font-size:0.84rem; border:1px solid #d1d5db; border-radius:8px; padding:0.42rem 0.55rem; min-width:11rem; }
        .st-btn { display:inline-flex; align-items:center; border-radius:8px; padding:0.42rem 0.75rem; font-size:0.82rem; font-weight:700; text-decoration:none; border:1px solid #d1d5db; background:#fff; color:#111827; cursor:pointer; }
        .st-btn--primary { background:#18181b; color:#fff; border-color:#18181b; }
        .st-table-wrap { overflow:auto; background:#fff; border:1px solid #e5e7eb; border-radius:12px; }
        .st-table { width:100%; min-width:920px; border-collapse:collapse; font-size:0.84rem; }
        .st-table th { text-align:left; padding:0.55rem 0.65rem; border-bottom:1px solid #e5e7eb; background:#f8fafc; color:#64748b; font-size:0.72rem; text-transform:uppercase; letter-spacing:.05em; }
        .st-table td { padding:0.55rem 0.65rem; border-bottom:1px solid #f8fafc; vertical-align:top; }
        .st-pill { display:inline-flex; align-items:center; border-radius:999px; padding:0.12rem 0.5rem; font-size:0.72rem; font-weight:800; border:1px solid transparent; }
        .st-pill--draft { background:#f4f4f5; color:#52525b; border-color:#e4e4e7; }
        .st-pill--published { background:#ecfdf5; color:#047857; border-color:#a7f3d0; }
        .st-pill--closed { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
        .st-pill--cancelled { background:#fef2f2; color:#b91c1c; border-color:#fecaca; }
        .st-pill--overdue { background:#fff7ed; color:#c2410c; border-color:#fed7aa; }
        .st-link { color:#3730a3; font-weight:700; text-decoration:none; }
        .st-meta { font-size:0.74rem; color:#64748b; margin-top:0.12rem; }
    </style>

    <div class="st-grid">
        <p style="margin:0;font-size:0.88rem;color:#52525b;">
            Assign operational tasks to <strong>state staff (SPOCs)</strong> with timelines. Each assignee reports progress individually.
        </p>

        <div class="st-toolbar">
            <form method="get" action="{{ route('admin.state-tasks.index') }}" style="display:flex; flex-wrap:wrap; gap:0.55rem; align-items:flex-end; flex:1;">
                <div class="st-field">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All statuses</option>
                        @foreach (\App\Models\StateTask::STATUSES as $s)
                            <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="st-field">
                    <label for="assignee_id">Assignee</label>
                    <select name="assignee_id" id="assignee_id">
                        <option value="">All state staff</option>
                        @foreach ($stateStaff as $staff)
                            <option value="{{ $staff->id }}" @selected((int) $filters['assignee_id'] === (int) $staff->id)>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="st-field">
                    <label><input type="checkbox" name="overdue" value="1" @checked($filters['overdue'])> Overdue only</label>
                </div>
                <button type="submit" class="st-btn">Apply</button>
                <a href="{{ route('admin.state-tasks.index') }}" class="st-btn">Reset</a>
            </form>
            <a href="{{ route('admin.state-tasks.create') }}" class="st-btn st-btn--primary">Create task</a>
        </div>

        <div class="st-table-wrap">
            <table class="st-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Timeline</th>
                        <th>Target</th>
                        <th>Assignees</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tasks as $task)
                        @php
                            $pillClass = match ($task->status) {
                                'draft' => 'st-pill--draft',
                                'published' => 'st-pill--published',
                                'closed' => 'st-pill--closed',
                                default => 'st-pill--cancelled',
                            };
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.state-tasks.show', $task) }}" class="st-link">{{ $task->title }}</a>
                                <div class="st-meta">By {{ $task->creator?->name ?? '—' }}</div>
                            </td>
                            <td>
                                @if ($task->start_date)
                                    {{ $task->start_date->format('d M Y') }}
                                @else
                                    —
                                @endif
                                →
                                @if ($task->due_date)
                                    {{ $task->due_date->format('d M Y') }}
                                @else
                                    —
                                @endif
                                @if ($task->isOverdue())
                                    <div><span class="st-pill st-pill--overdue">Overdue</span></div>
                                @endif
                            </td>
                            <td>{{ $task->target_value ? number_format((int) $task->target_value) : '—' }}</td>
                            <td>{{ number_format((int) $task->assignments_count) }}</td>
                            <td>
                                {{ number_format((int) $task->completed_count) }} / {{ number_format((int) $task->assignments_count) }} done
                                @if ((int) $task->submitted_count > 0)
                                    <div class="st-meta">{{ number_format((int) $task->submitted_count) }} awaiting review</div>
                                @endif
                            </td>
                            <td><span class="st-pill {{ $pillClass }}">{{ ucfirst($task->status) }}</span></td>
                            <td><a href="{{ route('admin.state-tasks.show', $task) }}" class="st-btn">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="padding:1rem;color:#64748b;">No tasks yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $tasks->links() }}
    </div>
@endsection

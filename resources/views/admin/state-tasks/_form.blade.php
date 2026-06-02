@php
    $isEdit = $task !== null;
@endphp

<style>
    .stf-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:1rem; display:grid; gap:0.85rem; max-width:920px; }
    .stf-row { display:grid; gap:0.35rem; }
    .stf-row label { font-size:0.78rem; font-weight:700; color:#475569; }
    .stf-row input[type=text], .stf-row input[type=date], .stf-row input[type=number], .stf-row textarea, .stf-row select {
        width:100%; border:1px solid #d1d5db; border-radius:8px; padding:0.48rem 0.6rem; font-size:0.88rem;
    }
    .stf-row textarea { min-height:7rem; resize:vertical; }
    .stf-grid-2 { display:grid; gap:0.75rem; grid-template-columns:repeat(auto-fit,minmax(12rem,1fr)); }
    .stf-assignees { display:grid; gap:0.35rem; grid-template-columns:repeat(auto-fit,minmax(14rem,1fr)); max-height:16rem; overflow:auto; border:1px solid #e5e7eb; border-radius:8px; padding:0.65rem; }
    .stf-assignees label { display:flex; gap:0.45rem; align-items:flex-start; font-size:0.84rem; font-weight:500; color:#111827; }
    .stf-actions { display:flex; flex-wrap:wrap; gap:0.55rem; }
    .stf-btn { border-radius:8px; padding:0.45rem 0.8rem; font-size:0.84rem; font-weight:700; border:1px solid #d1d5db; background:#fff; cursor:pointer; text-decoration:none; color:#111827; }
    .stf-btn--primary { background:#18181b; color:#fff; border-color:#18181b; }
    .stf-btn--green { background:#047857; color:#fff; border-color:#047857; }
    .stf-help { font-size:0.76rem; color:#64748b; }
    .stf-existing { font-size:0.82rem; color:#334155; }
</style>

<form method="post" action="{{ $action }}" enctype="multipart/form-data" class="stf-card">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="stf-row">
        <label for="title">Title *</label>
        <input type="text" name="title" id="title" value="{{ old('title', $task?->title) }}" required maxlength="255">
    </div>

    <div class="stf-row">
        <label for="description">Description</label>
        <textarea name="description" id="description">{{ old('description', $task?->description) }}</textarea>
    </div>

    <div class="stf-grid-2">
        <div class="stf-row">
            <label for="start_date">Start date</label>
            <input type="date" name="start_date" id="start_date" value="{{ old('start_date', optional($task?->start_date)->format('Y-m-d')) }}">
        </div>
        <div class="stf-row">
            <label for="due_date">Due date</label>
            <input type="date" name="due_date" id="due_date" value="{{ old('due_date', optional($task?->due_date)->format('Y-m-d')) }}">
        </div>
        <div class="stf-row">
            <label for="target_value">Target (optional number per assignee)</label>
            <input type="number" name="target_value" id="target_value" min="1" value="{{ old('target_value', $task?->target_value) }}" placeholder="e.g. 50">
            <span class="stf-help">Leave blank if completion is simply done / not done.</span>
        </div>
    </div>

    <div class="stf-row">
        <label>Assign to state staff *</label>
        <div class="stf-assignees">
            @foreach ($stateStaff as $staff)
                <label>
                    <input type="checkbox" name="assignee_ids[]" value="{{ $staff->id }}"
                        @checked(in_array((string) $staff->id, array_map('strval', $selectedAssignees), true))>
                    <span>{{ $staff->name }}<br><span class="stf-help">{{ $staff->email }}</span></span>
                </label>
            @endforeach
        </div>
        @error('assignee_ids')<div style="color:#b91c1c;font-size:0.82rem;">{{ $message }}</div>@enderror
    </div>

    <div class="stf-row">
        <label for="attachments">Attach documents (optional)</label>
        <input type="file" name="attachments[]" id="attachments" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
        <span class="stf-help">Up to 5 files, 10 MB each.</span>
    </div>

    @if ($isEdit && $task->attachments->isNotEmpty())
        <div class="stf-row">
            <label>Existing attachments</label>
            @foreach ($task->attachments as $attachment)
                <div class="stf-existing" style="display:flex;gap:0.5rem;align-items:center;">
                    <a href="{{ route('admin.state-tasks.attachments.download', [$task, $attachment]) }}">{{ $attachment->original_name }}</a>
                    <form method="post" action="{{ route('admin.state-tasks.attachments.destroy', [$task, $attachment]) }}" onsubmit="return confirm('Remove this attachment?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="stf-btn" style="padding:0.2rem 0.45rem;font-size:0.75rem;">Remove</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    <div class="stf-actions">
        @if (! $isEdit)
            <button type="submit" class="stf-btn">Save draft</button>
            <button type="submit" name="publish" value="1" class="stf-btn stf-btn--green">Publish now</button>
        @else
            <button type="submit" class="stf-btn stf-btn--primary">Save changes</button>
        @endif
        <a href="{{ $isEdit ? route('admin.state-tasks.show', $task) : route('admin.state-tasks.index') }}" class="stf-btn">Cancel</a>
    </div>
</form>

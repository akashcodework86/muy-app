@extends('layouts.admin')

@section('title', 'Edit state task')
@section('heading', 'Edit state task')

@section('content')
    @include('admin.state-tasks._form', [
        'action' => route('admin.state-tasks.update', $task),
        'method' => 'PUT',
        'task' => $task,
        'stateStaff' => $stateStaff,
        'selectedAssignees' => old('assignee_ids', $task->assignments->pluck('assignee_user_id')->all()),
    ])
@endsection

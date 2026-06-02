@extends('layouts.admin')

@section('title', 'Create state task')
@section('heading', 'Create state task')

@section('content')
    @include('admin.state-tasks._form', [
        'action' => route('admin.state-tasks.store'),
        'method' => 'POST',
        'task' => null,
        'stateStaff' => $stateStaff,
        'selectedAssignees' => old('assignee_ids', []),
    ])
@endsection

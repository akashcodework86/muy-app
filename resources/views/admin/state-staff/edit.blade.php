@extends('layouts.admin')

@section('title', 'Edit state staff')
@section('heading', 'Edit state staff (SPOC)')

@section('content')
    @if ($errors->any())
        <ul style="background:#fee2e2; color:#991b1b; padding:0.5rem 0.75rem; border-radius:6px; font-size:0.85rem; max-width:28rem; margin:0.5rem 0 1rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    <form method="post" action="{{ route('admin.state-staff.update', $user) }}" style="max-width:28rem;">
        @csrf
        @method('PUT')
        <div style="margin-bottom:0.85rem;">
            <label for="name" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required style="width:100%; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>
        <div style="margin-bottom:0.85rem;">
            <label for="email" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">Email (login)</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="off" style="width:100%; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>
        <div style="margin-bottom:0.85rem;">
            <label for="password" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">New password (leave blank to keep current)</label>
            <input id="password" name="password" type="password" autocomplete="new-password" style="width:100%; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>
        <div style="margin-bottom:1rem;">
            <label for="designation_id" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">Designation</label>
            <select id="designation_id" name="designation_id" required style="width:100%; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
                <option value="">Select</option>
                @foreach ($designations as $d)
                    <option value="{{ $d->id }}" @selected((string) old('designation_id', (string) $user->designation_id) === (string) $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.55rem 1rem; border-radius:6px; font-weight:500;">Save</button>
        <a href="{{ route('admin.state-staff.index') }}" style="margin-left:0.75rem; font-size:0.9rem;">Cancel</a>
    </form>
@endsection

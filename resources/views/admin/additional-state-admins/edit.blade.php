@extends('layouts.admin')

@section('title', 'Edit Executive Director')
@section('heading', 'Edit Executive Director')

@section('content')
    <form method="post" action="{{ route('admin.additional-state-admins.update', $user) }}" style="max-width:30rem;">
        @csrf
        @method('PUT')
        <div style="margin-bottom:0.85rem;"><label for="name" style="display:block;font-weight:600;margin-bottom:0.25rem;">Name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required style="width:100%;padding:0.55rem;border:1px solid #d4d4d8;border-radius:7px;"></div>
        <div style="margin-bottom:0.85rem;"><label for="email" style="display:block;font-weight:600;margin-bottom:0.25rem;">Email (login)</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="off" style="width:100%;padding:0.55rem;border:1px solid #d4d4d8;border-radius:7px;"></div>
        <div style="margin-bottom:0.85rem;"><label for="password" style="display:block;font-weight:600;margin-bottom:0.25rem;">New password</label><input id="password" name="password" type="password" autocomplete="new-password" style="width:100%;padding:0.55rem;border:1px solid #d4d4d8;border-radius:7px;"><small style="color:#71717a;">Leave blank to keep the current password.</small></div>
        <div style="margin-bottom:1rem;"><label style="display:block;font-weight:600;margin-bottom:0.25rem;">Designation</label><input value="Executive Director" readonly style="width:100%;padding:0.55rem;border:1px solid #d4d4d8;border-radius:7px;background:#f4f4f5;"></div>
        <button type="submit" style="background:#18181b;color:#fff;border:0;padding:0.6rem 1rem;border-radius:7px;font-weight:700;">Save changes</button>
        <a href="{{ route('admin.additional-state-admins.index') }}" style="margin-left:0.75rem;">Cancel</a>
    </form>
@endsection

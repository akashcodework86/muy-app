@extends('layouts.admin')

@section('title', 'Add state staff')
@section('heading', 'Add state staff (SPOC)')

@section('content')
    <p style="font-size:0.88rem; color:#52525b; margin-top:0; max-width:48rem;">
        SPOCs approve, send back, or reject service cases raised by district staff.
        After creating the user, assign them districts on the
        <a href="{{ url('/admin/service-spocs') }}">Service SPOCs</a> page (coming soon).
    </p>

    @if ($errors->any())
        <ul style="background:#fee2e2; color:#991b1b; padding:0.5rem 0.75rem; border-radius:6px; font-size:0.85rem; max-width:28rem; margin:0.5rem 0 1rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    <form method="post" action="{{ route('admin.state-staff.store') }}" style="max-width:28rem;">
        @csrf
        <div style="margin-bottom:0.85rem;">
            <label for="name" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required style="width:100%; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>
        <div style="margin-bottom:0.85rem;">
            <label for="email" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">Email (login)</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="off" style="width:100%; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>
        <div style="margin-bottom:0.85rem;">
            <label for="password" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">Password (min 8)</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" style="width:100%; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
        </div>
        <div style="margin-bottom:1rem;">
            <label for="designation_id" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">Designation</label>
            <select id="designation_id" name="designation_id" required style="width:100%; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
                <option value="">Select</option>
                @foreach ($designations as $d)
                    <option value="{{ $d->id }}" @selected((string) old('designation_id') === (string) $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
            <p style="font-size:0.75rem; color:#71717a; margin:0.35rem 0 0;">Typically <em>State Staff (SPOC)</em>. Manage titles under <a href="{{ route('admin.designations.index') }}">Designations</a>.</p>
        </div>
        <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.55rem 1rem; border-radius:6px; font-weight:500;">Create state staff</button>
        <a href="{{ route('admin.state-staff.index') }}" style="margin-left:0.75rem; font-size:0.9rem;">Cancel</a>
    </form>
@endsection

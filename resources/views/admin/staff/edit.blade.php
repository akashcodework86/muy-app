@extends('layouts.admin')

@section('title', 'Edit staff')
@section('heading', 'Edit district staff')

@section('content')
    <form method="post" action="{{ route('admin.staff.update', $user) }}" style="max-width:28rem;">
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
            <label for="password" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">New password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" style="width:100%; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
            <span style="font-size:0.75rem; color:#71717a;">Leave blank to keep current password.</span>
        </div>
        <div style="margin-bottom:0.85rem;">
            <label for="designation_id" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">Role (designation)</label>
            <select id="designation_id" name="designation_id" required style="width:100%; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
                <option value="">Select</option>
                @foreach ($designations as $d)
                    <option value="{{ $d->id }}" @selected((string) old('designation_id', $user->designation_id) === (string) $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="margin-bottom:0.85rem;">
            <label for="hub_id" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">Hub</label>
            <select id="hub_id" name="hub_id" required style="width:100%; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
                <option value="">Select</option>
                @foreach ($hubs as $h)
                    <option value="{{ $h->id }}" @selected((string) old('hub_id', $user->hub_id) === (string) $h->id)>{{ $h->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="margin-bottom:1rem;">
            <label for="district_id" style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.25rem;">District (must match hub)</label>
            <select id="district_id" name="district_id" required style="width:100%; padding:0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
                <option value="">Select</option>
                @foreach ($districts as $d)
                    <option value="{{ $d->id }}" data-hub="{{ $d->hub_id }}" @selected((string) old('district_id', $user->district_id) === (string) $d->id)>{{ $d->hub?->name ?? '' }} — {{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.55rem 1rem; border-radius:6px; font-weight:500;">Save changes</button>
        <a href="{{ route('admin.staff.index') }}" style="margin-left:0.75rem; font-size:0.9rem;">Cancel</a>
    </form>

    @push('scripts')
        <script>
            (function () {
                const hub = document.getElementById('hub_id');
                const dist = document.getElementById('district_id');
                if (!hub || !dist) return;
                function filterDistricts() {
                    const hid = hub.value;
                    Array.from(dist.options).forEach(function (opt) {
                        if (!opt.value) return;
                        const dh = opt.getAttribute('data-hub');
                        opt.hidden = hid && dh !== hid;
                    });
                }
                hub.addEventListener('change', filterDistricts);
                filterDistricts();
            })();
        </script>
    @endpush
@endsection

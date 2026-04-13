@extends('layouts.admin')

@section('title', 'Edit holiday')

@section('heading', 'Edit public holiday')

@section('content')
    <form method="post" action="{{ route('admin.holidays.update', $holiday) }}" style="max-width:24rem;">
        @csrf
        @method('PUT')
        <div style="margin-bottom:1rem;">
            <label for="holiday_date" style="display:block;font-weight:600;margin-bottom:0.35rem;font-size:0.9rem;">Date</label>
            <input id="holiday_date" name="holiday_date" type="date" required
                value="{{ old('holiday_date', $holiday->holiday_date->format('Y-m-d')) }}"
                style="width:100%;padding:0.55rem 0.75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:1rem;">
            @error('holiday_date')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>
        <div style="margin-bottom:1rem;">
            <label for="name" style="display:block;font-weight:600;margin-bottom:0.35rem;font-size:0.9rem;">Name</label>
            <input id="name" name="name" type="text" required maxlength="160" value="{{ old('name', $holiday->name) }}"
                style="width:100%;padding:0.55rem 0.75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:1rem;">
            @error('name')<div style="color:#b91c1c;font-size:0.85rem;margin-top:0.25rem;">{{ $message }}</div>@enderror
        </div>
        <button type="submit" style="padding:0.5rem 1.1rem;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Update</button>
        <a href="{{ route('admin.holidays.index') }}" style="margin-left:0.75rem;color:#64748b;">Back</a>
    </form>
@endsection

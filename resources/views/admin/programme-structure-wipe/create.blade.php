@extends('layouts.admin')

@section('title', 'Reset programme structure')
@section('heading', 'Reset programme structure')

@section('content')
    <div style="max-width:55rem;">
        <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:0.85rem 1rem; border-radius:10px; font-size:0.88rem; line-height:1.5; margin-bottom:1rem;">
            <strong>Destructive action.</strong> This runs <code style="background:#fee2e2;padding:0.1rem 0.35rem;border-radius:4px;">programme:wipe-structure</code>:
            all service cases (and attachment files), catalog services &amp; categories, MIS deliverables, and all linked state/district/staff targets.
            It does <strong>not</strong> delete CFA submissions, batches, users, or staff accounts.
        </div>

        @if (! $wipeConfigured)
            <p style="background:#fef9c3; border:1px solid #fde047; color:#854d0e; padding:0.75rem 1rem; border-radius:8px; font-size:0.88rem; margin-bottom:1rem;">
                <strong>Submit abhi band hai.</strong> Pehle server ki <code>.env</code> me <strong>PROGRAMME_WIPE_SECRET</strong> (kam se kam 8 characters) set karo, phir
                <code>php artisan config:clear</code> (ya deploy ke baad <code>config:cache</code>). Uske baad neeche wala button kaam karega.
            </p>
        @endif

        @if (session('status'))
            <p style="background:#dcfce7; color:#166534; padding:0.5rem 0.75rem; border-radius:6px; font-size:0.88rem; margin:0 0 1rem;">{{ session('status') }}</p>
        @endif
        @if (session('command_output'))
            <pre style="background:#18181b; color:#e4e4e7; padding:0.75rem 1rem; border-radius:8px; font-size:0.78rem; overflow:auto; max-height:20rem; margin:0 0 1rem;">{{ session('command_output') }}</pre>
        @endif
        @if ($errors->any())
            <ul style="color:#b91c1c; margin:0 0 1rem; padding-left:1.2rem; font-size:0.88rem;">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.programme-structure-wipe.store') }}" style="background:#fff; border:1px solid #e4e4e7; border-radius:12px; padding:1rem 1.1rem;">
            @csrf
            <fieldset style="border:none; margin:0; padding:0; min-width:0;" @if (! $wipeConfigured) disabled @endif>
                <label style="display:block; font-size:0.88rem; font-weight:600; color:#18181b; margin-bottom:0.35rem;">Wipe secret</label>
                <input type="password" name="wipe_secret" value="{{ old('wipe_secret') }}" autocomplete="off"
                    @if ($wipeConfigured) required @endif
                    style="width:100%; max-width:28rem; padding:0.45rem 0.6rem; border:1px solid #d4d4d8; border-radius:6px; font-size:0.9rem; margin-bottom:0.85rem;">

                <label style="display:block; font-size:0.88rem; font-weight:600; color:#18181b; margin-bottom:0.35rem;">Type exactly: <code>RESET-PROGRAMME</code></label>
                <input type="text" name="confirm_phrase" value="{{ old('confirm_phrase') }}"
                    @if ($wipeConfigured) required @endif
                    autocomplete="off"
                    style="width:100%; max-width:28rem; padding:0.45rem 0.6rem; border:1px solid #d4d4d8; border-radius:6px; font-size:0.9rem; margin-bottom:0.85rem;">

                <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.88rem; cursor:pointer; margin-bottom:1rem;">
                    <input type="hidden" name="wipe_app_settings" value="0">
                    <input type="checkbox" name="wipe_app_settings" value="1" @checked(old('wipe_app_settings')) style="width:1.1rem; height:1.1rem; accent-color:#b91c1c;">
                    Also delete <strong>app_settings</strong> rows (service module JSON / toggles)
                </label>

                <button type="submit" style="background:#b91c1c; color:#fff; border:none; padding:0.5rem 1rem; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer;">
                    Run wipe now
                </button>
            </fieldset>
        </form>
    </div>
@endsection

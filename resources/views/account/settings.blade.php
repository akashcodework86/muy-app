@extends('layouts.admin')

@section('body_class', 'admin-app-body--dashboard')

@section('title', 'Account settings')

@section('heading', 'Account settings')

@section('content')
    <style>
        .account-settings { max-width: 40rem; }
        .account-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.35rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);
        }
        .account-card h2 {
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0 0 1rem;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .account-field { margin-bottom: 1rem; }
        .account-field:last-of-type { margin-bottom: 0; }
        .account-field label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.35rem;
        }
        .account-field input[type="text"],
        .account-field input[type="email"],
        .account-field input[type="tel"],
        .account-field input[type="password"],
        .account-field input[type="file"] {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 0.55rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
        }
        .account-field .field-error { font-size: 0.75rem; color: #b91c1c; margin-top: 0.25rem; }
        .account-actions { margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .account-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }
        .account-btn--primary { background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff; }
        .account-btn--primary:hover { filter: brightness(1.05); }
        .account-btn--danger {
            background: #fff;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .account-btn--danger:hover { background: #fef2f2; }
        .account-avatar-preview {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 0.75rem;
        }
        .account-avatar-preview img,
        .account-avatar-preview .account-avatar-fallback {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }
        .account-avatar-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.25rem;
            color: #fff;
            background: linear-gradient(135deg, #f97316, #db2777, #6366f1);
        }
        .account-hint { font-size: 0.75rem; color: #64748b; margin-top: 0.35rem; }
    </style>

    @php
        $initials = collect(preg_split('/\s+/', trim((string) ($user->name ?? 'U'))) ?: [])
            ->filter()
            ->map(fn ($part) => mb_substr($part, 0, 1))
            ->take(2)
            ->implode('');
        if ($initials === '') {
            $initials = 'U';
        }
    @endphp

    <div class="account-settings">
        <p class="admin-page-meta" style="margin-top:-0.5rem;margin-bottom:1.25rem;">Update your name, contact details, photo, and password.</p>

        <div class="account-card">
            <h2>Profile photo</h2>
            <div class="account-avatar-preview">
                @if ($user->avatar_path)
                    <img src="{{ $user->avatarUrl() }}" alt="Profile photo">
                @else
                    <span class="account-avatar-fallback">{{ strtoupper($initials) }}</span>
                @endif
            </div>
            <form method="post" action="{{ route('account.settings.avatar.update') }}" enctype="multipart/form-data">
                @csrf
                <div class="account-field">
                    <label for="avatar">Upload image</label>
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp">
                    @error('avatar')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                    <p class="account-hint">JPEG, PNG, or WebP · max 2 MB</p>
                </div>
                <div class="account-actions">
                    <button type="submit" class="account-btn account-btn--primary">Save photo</button>
                </div>
            </form>
            @if ($user->avatar_path)
                <form method="post" action="{{ route('account.settings.avatar.destroy') }}" class="account-actions" style="margin-top:0.75rem;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="account-btn account-btn--danger">Remove photo</button>
                </form>
            @endif
        </div>

        @if ($user->role === 'incubatee')
        <div class="account-card">
            <h2>Login</h2>
            <p class="account-hint" style="margin-top:0">Your login ID is this 10-digit mobile number. Only the password can be changed.</p>
            <div class="account-field">
                <label>Mobile (login ID)</label>
                <input type="text" value="{{ $user->phone }}" disabled>
            </div>
            <div class="account-field">
                <label>Name</label>
                <input type="text" value="{{ $user->name }}" disabled>
            </div>
        </div>
        @else
        <div class="account-card">
            <h2>Profile &amp; contact</h2>
            <form method="post" action="{{ route('account.settings.profile.update') }}">
                @csrf
                @method('PUT')
                <div class="account-field">
                    <label for="name">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name">
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="account-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email">
                    @error('email')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="account-field">
                    <label for="phone">Mobile</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" autocomplete="tel" placeholder="+91 …">
                    @error('phone')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="account-actions">
                    <button type="submit" class="account-btn account-btn--primary">Save profile</button>
                </div>
            </form>
        </div>
        @endif

        <div class="account-card">
            <h2>Password</h2>
            <form method="post" action="{{ route('account.settings.password.update') }}">
                @csrf
                @method('PUT')
                <div class="account-field">
                    <label for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
                    @error('current_password')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="account-field">
                    <label for="password">New password</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password">
                    @error('password')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="account-field">
                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                </div>
                <div class="account-actions">
                    <button type="submit" class="account-btn account-btn--primary">Update password</button>
                </div>
            </form>
        </div>
    </div>
@endsection

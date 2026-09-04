@extends('auth.layouts.muy-login', ['hideGuestLogin' => true])

@section('title', 'Incubatee login')
@section('eyebrow', 'Incubatee portal')

@section('form')
            <h1>Incubatee login</h1>
            <p class="muy-card__lead">Sign in with your mobile number.</p>

            @if ($errors->any())
                <div class="muy-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ route('incubatee.login.store') }}">
                @csrf
                <label for="phone">Mobile number</label>
                <input id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="username" value="{{ old('phone') }}" required autofocus maxlength="13">

                <label for="password">Password</label>
                <div class="app-auth-password-wrap">
                    <input id="password" name="password" type="password" required autocomplete="current-password">
                    <button class="app-auth-password-toggle" type="button" data-toggle-password aria-label="Show password" title="Show password" aria-pressed="false">
                        <span class="app-auth-sr-only">Show password</span>
                        <svg data-icon-eye viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2.2 12.1c2.3-4.8 6-7.1 9.8-7.1s7.5 2.3 9.8 7.1c.1.3.1.6 0 .8-2.3 4.8-6 7.1-9.8 7.1s-7.5-2.3-9.8-7.1a1 1 0 0 1 0-.8Z" stroke="currentColor" stroke-width="1.8" />
                            <path d="M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="1.8" />
                        </svg>
                        <svg data-icon-eye-off viewBox="0 0 24 24" fill="none" aria-hidden="true" style="display:none">
                            <path d="M9.9 4.6A10 10 0 0 1 12 4c3.8 0 7.5 2.3 9.8 7.1.1.3.1.6 0 .8a15.8 15.8 0 0 1-3.1 4.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M6.1 6.1A15.8 15.8 0 0 0 2.2 12c-.1.3-.1.6 0 .8C4.5 17.6 8.2 20 12 20c1.2 0 2.4-.2 3.5-.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M10.3 10.3a2.5 2.5 0 0 0 3.4 3.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="muy-remember">
                    <input id="remember" name="remember" type="checkbox" value="1">
                    <label for="remember">Remember me</label>
                </div>

                <button type="submit" class="muy-btn">Log in</button>
            </form>
@endsection

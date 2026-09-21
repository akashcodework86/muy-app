@php
    $currentLocale = $incubateeLocale ?? \App\Support\IncubateeLocale::fromRequest();
@endphp
<form method="post" action="{{ route('incubatee.language') }}" class="inc-lang" aria-label="Language">
    @csrf
    <input type="hidden" name="locale" value="{{ $currentLocale === 'hi' ? 'en' : 'hi' }}">
    <button type="submit" class="inc-lang__btn" title="{{ $currentLocale === 'hi' ? 'Switch to English' : 'हिन्दी में देखें' }}">
        <span class="inc-lang__opt @if ($currentLocale === 'hi') is-on @endif">{{ __('incubatee.nav.lang_hi') }}</span>
        <span class="inc-lang__sep" aria-hidden="true">|</span>
        <span class="inc-lang__opt @if ($currentLocale === 'en') is-on @endif">{{ __('incubatee.nav.lang_en') }}</span>
    </button>
</form>

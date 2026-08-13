@extends('public.homestay-survey.layout')

@section('title', 'Already submitted')

@section('content')
@php $muyLogo = 'https://ukrbi.in/new/admin/muy.png'; @endphp
<header class="hs-header">
    <div class="hs-header__inner">
        <img class="hs-header__logo" src="{{ $muyLogo }}" alt="MUY logo" width="48" height="48">
        <div class="hs-header__text">
            <h1>Homestay Progress Survey</h1>
            <p>Mukhyamantri Udyamshala Yojana</p>
        </div>
    </div>
</header>
<div class="hs-wrap">
    <div class="hs-card hs-thanks">
        <div class="hs-thanks__icon hs-thanks__icon--warn" aria-hidden="true">!</div>
        <h1>Already submitted</h1>
        <p>Response already recorded for <strong>{{ $phone }}</strong>.</p>
        <div class="hs-thanks__actions">
            <a class="hs-btn hs-btn--lg" href="{{ route('homestay-survey.show') }}">Fill new entry</a>
        </div>
    </div>
</div>
@endsection

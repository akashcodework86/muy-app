@extends('layouts.admin')

@section('title', __('incubatee.docs_heading'))
@section('heading', __('incubatee.docs_heading'))

@section('content')
    <p style="font-size:0.9rem;color:#52525b;margin:0 0 1rem;">{{ $titleText }}</p>
    @include('documents.partials.list')
@endsection

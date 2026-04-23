@extends('layouts.admin')

@section('title', 'Documents')
@section('heading', 'Documents')

@section('content')
    <p style="font-size:0.9rem;color:#52525b;margin:0 0 1rem;">{{ $titleText }}</p>
    @include('documents.partials.list')
@endsection

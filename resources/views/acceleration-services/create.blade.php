@extends('layouts.admin')

@section('title', 'New acceleration entry')
@section('heading', 'New acceleration entry')

@include('acceleration-services.partials.styles')

@section('content')
<div class="accel-shell">
    @if (!empty($migrationMissing))
        <div class="accel-alert accel-alert--warning"><strong>Database update required.</strong> Run <code>php artisan migrate</code>.</div>
    @endif

    @if (session('status'))
        <div class="accel-alert accel-alert--success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="accel-alert accel-alert--error">
            <strong>Please fix:</strong>
            <ul style="margin:0.35rem 0 0 1rem;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="accel-alert accel-alert--info">
        MIS <strong>7.2</strong> — Initiation of acceleration &amp; co-incubation services.
        Counts <strong>unique Phase 1 incubatees per FY</strong> on first initiation; follow-up visits add services without re-counting 7.2.
        <span style="display:block;margin-top:0.35rem;">
            <a class="accel-link" href="{{ route($dashboardRoute) }}">View dashboard →</a>
        </span>
    </div>

    @if (empty($migrationMissing))
        @include('acceleration-services.partials.form')
    @endif
</div>
@endsection

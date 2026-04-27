@extends('layouts.admin')

@section('title', 'Cache Clear')
@section('heading', 'Cache clear executed')

@section('content')
    <p style="margin:0 0 0.75rem;">
        Ran at: <strong>{{ $ranAt->format('d M Y H:i:s') }}</strong>
    </p>

    <p style="margin:0 0 1rem; color:#374151;">
        This page ran <code>optimize:clear</code> and <code>view:clear</code> automatically.
    </p>

    @foreach ($results as $row)
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:0.75rem 0.9rem; margin-bottom:0.75rem;">
            <div style="display:flex; justify-content:space-between; gap:0.5rem; align-items:center;">
                <strong>{{ $row['command'] }}</strong>
                <span style="font-size:0.78rem; padding:0.15rem 0.45rem; border-radius:999px; background:{{ $row['exit_code'] === 0 ? '#dcfce7' : '#fee2e2' }}; color:{{ $row['exit_code'] === 0 ? '#166534' : '#991b1b' }};">
                    exit {{ $row['exit_code'] }}
                </span>
            </div>
            @if (!empty($row['output']))
                <pre style="white-space:pre-wrap; margin:0.55rem 0 0; font-size:0.8rem; color:#334155;">{{ $row['output'] }}</pre>
            @endif
        </div>
    @endforeach

    <p style="margin-top:1rem;">
        <a href="{{ route('admin.service-catalog.index') }}">Back to service catalog</a>
    </p>
@endsection


@extends('layouts.admin')

@section('title', 'Import gram panchayats')
@section('heading', 'Import gram panchayats')

@section('content')
<div style="max-width:720px;font-family:'DM Sans',sans-serif;display:flex;flex-direction:column;gap:1.25rem;">

    @if (! $tableReady)
        <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:12px;padding:1rem;">
            <strong>Migrations pending.</strong> Deploy latest code and run migrations before importing.
        </div>
    @endif

    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.1rem 1.25rem;">
        <p style="margin:0 0 0.5rem;font-size:0.88rem;color:#64748b;">
            Upload your master CSV (columns: <strong>District</strong>, <strong>Block</strong>, <strong>Gram Panchayat</strong>).
            Existing districts and blocks are <strong>not changed</strong> — only gram panchayat rows are added or updated.
        </p>
        <p style="margin:0;font-size:0.82rem;color:#64748b;">
            Currently in database: <strong>{{ number_format($totalGramPanchayats) }}</strong> gram panchayat(s).
        </p>
    </div>

    @if ($errors->any())
        <div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;">
            @foreach ($errors->all() as $e)
                <div>{{ $e }}</div>
            @endforeach
        </div>
    @endif

    @if (is_array($report) && ($report['success'] ?? false))
        <div style="background:#f0fdf4;border:1px solid #86efac;color:#166534;border-radius:12px;padding:1rem;font-size:0.88rem;">
            <strong>Import complete</strong>
            <ul style="margin:0.5rem 0 0 1.1rem;">
                <li>Inserted: {{ number_format((int) ($report['inserted'] ?? 0)) }}</li>
                <li>Updated: {{ number_format((int) ($report['updated'] ?? 0)) }}</li>
                <li>Skipped: {{ number_format((int) ($report['skipped'] ?? 0)) }}</li>
            </ul>
            @if (! empty($report['unmatched_districts']))
                <p style="margin:0.75rem 0 0.25rem;font-weight:700;">Unmatched districts:</p>
                <ul style="margin:0 0 0 1.1rem;max-height:120px;overflow:auto;">
                    @foreach ($report['unmatched_districts'] as $name)
                        <li>{{ $name }}</li>
                    @endforeach
                </ul>
            @endif
            @if (! empty($report['unmatched_blocks']))
                <p style="margin:0.75rem 0 0.25rem;font-weight:700;">Unmatched blocks (fix CSV spelling or add block in admin):</p>
                <ul style="margin:0 0 0 1.1rem;max-height:160px;overflow:auto;">
                    @foreach ($report['unmatched_blocks'] as $name)
                        <li>{{ $name }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.25rem;">
        <form method="post" action="{{ route('admin.gram-panchayats.import.store') }}" enctype="multipart/form-data">
            @csrf
            <label style="display:block;font-size:0.78rem;font-weight:600;margin-bottom:0.4rem;">CSV file</label>
            <input type="file" name="csv" accept=".csv,text/csv" required style="margin-bottom:1rem;">
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <button type="submit" class="adatt-btn" style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.55rem 1rem;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;" @if(! $tableReady) disabled @endif>
                    <i class="fa-solid fa-file-import"></i> Import now
                </button>
                <a href="{{ route('admin.attendance.index') }}" style="display:inline-flex;align-items:center;padding:0.55rem 1rem;border:1px solid #4f46e5;color:#4f46e5;border-radius:8px;font-weight:600;text-decoration:none;">
                    Back to field visits
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

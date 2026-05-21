@extends('layouts.admin')

@section('title', 'Database migrations')
@section('heading', 'Database migrations')

@section('content')
    @php
        $pendingCount = count($pending);
        $runOk = isset($runResult) && ($runResult['exit_code'] ?? 1) === 0;
    @endphp

    <style>
        .mig-shell { display:flex; flex-direction:column; gap:1rem; max-width:56rem; }
        .mig-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.1rem 1.25rem; }
        .mig-card h3 { margin:0 0 0.65rem; font-size:0.98rem; color:#0f172a; }
        .mig-card p, .mig-card li { font-size:0.88rem; color:#475569; line-height:1.55; }
        .mig-card ol { margin:0.35rem 0 0; padding-left:1.2rem; }
        .mig-alert { border-radius:10px; padding:0.75rem 0.9rem; font-size:0.86rem; margin-bottom:0.75rem; }
        .mig-alert--ok { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
        .mig-alert--warn { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
        .mig-alert--err { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
        .mig-stat { display:inline-flex; align-items:center; gap:0.35rem; padding:0.2rem 0.55rem; border-radius:999px; font-size:0.78rem; font-weight:800; }
        .mig-stat--ok { background:#dcfce7; color:#166534; }
        .mig-stat--bad { background:#fee2e2; color:#991b1b; }
        .mig-btn { border:none; border-radius:8px; background:#4f46e5; color:#fff; padding:0.58rem 0.95rem; font-weight:700; cursor:pointer; font-size:0.88rem; text-decoration:none; display:inline-flex; align-items:center; }
        .mig-btn--green { background:#065f46; }
        .mig-btn--ghost { background:#fff; color:#334155; border:1px solid #cbd5e1; }
        .mig-list { margin:0; padding:0; list-style:none; }
        .mig-list li { padding:0.45rem 0; border-bottom:1px solid #f1f5f9; font-size:0.84rem; font-family:ui-monospace, monospace; color:#334155; }
        .mig-list li:last-child { border-bottom:none; }
        .mig-checks { display:grid; gap:0.55rem; }
        .mig-check { display:flex; justify-content:space-between; gap:0.75rem; align-items:flex-start; padding:0.55rem 0.65rem; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; font-size:0.84rem; }
        .mig-pre { white-space:pre-wrap; margin:0.55rem 0 0; font-size:0.78rem; color:#334155; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:0.65rem 0.75rem; }
        .mig-actions { display:flex; flex-wrap:wrap; gap:0.55rem; align-items:center; margin-top:0.75rem; }
        .mig-confirm { display:flex; align-items:center; gap:0.45rem; font-size:0.84rem; color:#334155; margin-top:0.55rem; }
    </style>

    <div class="mig-shell">
        @if (!empty($runResult))
            <div class="mig-alert {{ $runOk ? 'mig-alert--ok' : 'mig-alert--err' }}">
                <strong>{{ $runOk ? 'Migrations completed.' : 'Migration run failed.' }}</strong>
                @if (!empty($ranAt))
                    Ran at {{ $ranAt->format('d M Y H:i:s') }} · exit code {{ $runResult['exit_code'] }}
                @endif
            </div>
            @if (!empty($runResult['output']))
                <div class="mig-card">
                    <h3>Output</h3>
                    <pre class="mig-pre">{{ $runResult['output'] }}</pre>
                </div>
            @endif
        @endif

        <div class="mig-card">
            <h3>Step 1 — cPanel se files upload karein</h3>
            <p>Pehle naye PHP files upload kar dein (File Manager ya FTP se), phir neeche <strong>Run pending migrations</strong> dabayein.</p>
            <ol>
                <li><code>database/migrations/</code> — nayi migration files</li>
                <li><code>app/</code>, <code>routes/</code>, <code>resources/views/</code> — jo feature ke files hain</li>
                <li>Upload ke baad is page par wapas aayein</li>
            </ol>
        </div>

        <div class="mig-card">
            <h3>Step 2 — Run migrations (recommended)</h3>
            <p>
                Pending migrations: <strong>{{ number_format($pendingCount) }}</strong>
                · Already ran: <strong>{{ number_format($ranCount) }}</strong>
            </p>

            @if (! $hasMigrationsTable)
                <div class="mig-alert mig-alert--warn">
                    <code>migrations</code> table abhi nahi hai — pehli baar run karne par Laravel ise khud bana lega.
                </div>
            @endif

            @if ($pendingCount === 0)
                <div class="mig-alert mig-alert--ok">Sab migrations already run ho chuki hain. Kuch pending nahi.</div>
            @else
                <ul class="mig-list">
                    @foreach ($pending as $name)
                        <li>{{ $name }}</li>
                    @endforeach
                </ul>

                <form method="post" action="{{ route('admin.ops.migrations.run') }}" class="mig-actions">
                    @csrf
                    <label class="mig-confirm">
                        <input type="checkbox" name="confirm" value="1" required>
                        Main samajh gaya — database update hoga
                    </label>
                    <button type="submit" class="mig-btn">Run pending migrations</button>
                </form>
            @endif
        </div>

        <div class="mig-card">
            <h3>Step 3 — phpMyAdmin option (SQL download)</h3>
            <p>Agar upar wala button kaam na kare, SQL file download karke phpMyAdmin → <strong>Import</strong> se chala sakte hain.</p>
            <div class="mig-actions">
                @forelse ($sqlBundles as $bundle)
                    <a href="{{ route('admin.ops.migrations.sql', $bundle['slug']) }}" class="mig-btn mig-btn--green">
                        ⬇ Download {{ ucwords($bundle['title']) }} SQL
                    </a>
                @empty
                    <span style="font-size:0.84rem;color:#64748b;">No SQL bundles found in <code>database/sql/</code>.</span>
                @endforelse
            </div>
            <ol style="margin-top:0.75rem;">
                <li>phpMyAdmin kholein → apna database select karein</li>
                <li><strong>Import</strong> tab → downloaded <code>.sql</code> file choose karein → Go</li>
                <li>Success ke baad site refresh karein</li>
            </ol>
        </div>

        <div class="mig-card">
            <h3>Module health check</h3>
            <div class="mig-checks">
                @foreach ($moduleChecks as $check)
                    <div class="mig-check">
                        <div>
                            <strong>{{ $check['label'] }}</strong>
                            <div style="color:#64748b;margin-top:0.15rem;">{{ $check['detail'] }}</div>
                        </div>
                        <span class="mig-stat {{ $check['ok'] ? 'mig-stat--ok' : 'mig-stat--bad' }}">
                            {{ $check['ok'] ? 'OK' : 'Fix needed' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <p style="font-size:0.82rem;color:#64748b;">
            Admin only · Same as <code>php artisan migrate --force</code>
            · <a href="{{ route('admin.ops.cache-clear') }}">Cache clear page</a>
        </p>
    </div>
@endsection

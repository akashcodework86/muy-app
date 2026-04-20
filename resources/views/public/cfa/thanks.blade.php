@php
    $logoUrl = asset('https://ukrbi.in/new/admin/muy.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thank you — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cfa-form.css') }}?v={{ filemtime(public_path('css/cfa-form.css')) }}">
    <style>
        .thanks-page {
            padding: 2rem 1.25rem 3rem;
            max-width: 480px;
            margin: 0 auto;
        }
        .thanks-card {
            background: var(--color-surface, #fff);
            border-radius: var(--radius-lg, 14px);
            box-shadow: var(--shadow-lg, 0 4px 24px rgba(15, 23, 42, 0.08));
            border: 1px solid var(--color-border, #cbd5e1);
            overflow: hidden;
        }
        .thanks-card__accent {
            height: 4px;
            background: linear-gradient(90deg, #4f46e5, #6366f1, #0d9488);
        }
        .thanks-card__body {
            padding: 1.75rem 1.5rem 1.5rem;
        }
        .thanks-hero {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .thanks-hero__mark {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            background: linear-gradient(145deg, #ecfdf5 0%, #d1fae5 100%);
            border: 2px solid #6ee7b7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.85rem;
            line-height: 1;
            box-shadow: 0 8px 24px rgba(13, 148, 136, 0.2);
        }
        .thanks-hero__title {
            font-family: var(--font-display, 'DM Sans', sans-serif);
            font-size: clamp(1.75rem, 5vw, 2.125rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            margin: 0 0 0.35rem;
            background: linear-gradient(115deg, #4f46e5 0%, #0d9488 55%, #047857 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .thanks-hero__sub {
            margin: 0;
            font-size: 0.95rem;
            color: var(--color-text-muted, #64748b);
            font-weight: 500;
        }
        .thanks-hero__hi {
            margin: 0.4rem 0 0;
            font-size: 0.88rem;
            color: #94a3b8;
        }
        dl.thanks-dl { margin: 0; }
        .thanks-dl .row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.6rem 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }
        .thanks-dl .row:last-of-type { border-bottom: none; }
        .thanks-dl dt {
            color: #64748b;
            font-weight: 500;
            flex-shrink: 0;
        }
        .thanks-dl dd {
            margin: 0;
            text-align: right;
            color: #0f172a;
            font-weight: 600;
            word-break: break-word;
        }
        .thanks-dl .row--app dd {
            font-size: 1.05rem;
            background: linear-gradient(90deg, #4f46e5, #0d9488);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .btn-new {
            display: block;
            margin-top: 1.25rem;
            padding: 0.7rem 1.25rem;
            background: linear-gradient(135deg, #4f46e5, #0d9488);
            color: #fff !important;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: var(--radius, 8px);
            text-decoration: none;
            text-align: center;
            transition: transform 0.12s ease, box-shadow 0.12s ease, opacity 0.15s;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
        }
        .btn-new:hover {
            opacity: 0.95;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(13, 148, 136, 0.35);
        }
        .powered {
            margin-top: 1rem;
            font-size: 0.75rem;
            color: #94a3b8;
            text-align: center;
        }
        .empty-note {
            font-size: 0.875rem;
            color: #92400e;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: var(--radius, 8px);
            padding: 0.85rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body class="cfa-body">
    <header class="form-header">
        <div class="header-inner">
            <img class="header-logo" src="{{ $logoUrl }}" width="140" height="56" alt="Mukhyamantri Udyamshala Yojana">
            <div class="header-text">
                <h1>मुख्यमंत्री उद्यमशाला योजना</h1>
                <p>Rural Business Incubator — Call For Application</p>
            </div>
        </div>
    </header>

    <div class="thanks-page">
        <div class="thanks-card">
            <div class="thanks-card__accent"></div>
            <div class="thanks-card__body">
                <div class="thanks-hero">
                    <div class="thanks-hero__mark" aria-hidden="true">✓</div>
                    <h2 class="thanks-hero__title">Thank you</h2>
                    <p class="thanks-hero__sub">Your application has been received.</p>
                    <p class="thanks-hero__hi">धन्यवाद · आपका आवेदन प्राप्त हो गया है।</p>
                </div>

                @if (empty($applicationNo) && empty($thanksName))
                    <p class="empty-note">Nothing to show here. Open the form from your link and submit again if needed.</p>
                @else
                    <dl class="thanks-dl">
                        <div class="row">
                            <dt>Name</dt>
                            <dd>{{ $thanksName ?: '—' }}</dd>
                        </div>
                        <div class="row">
                            <dt>District</dt>
                            <dd>{{ $thanksDistrict ?: '—' }}</dd>
                        </div>
                        <div class="row">
                            <dt>Block</dt>
                            <dd>{{ $thanksBlock ?: '—' }}</dd>
                        </div>
                        <div class="row row--app">
                            <dt>Application no.</dt>
                            <dd>{{ $applicationNo ?: '—' }}</dd>
                        </div>
                        <div class="row">
                            <dt>Sector</dt>
                            <dd>{{ $thanksSector ?: '—' }}</dd>
                        </div>
                        <div class="row">
                            <dt>Product</dt>
                            <dd>{{ $thanksProduct ?: '—' }}</dd>
                        </div>
                    </dl>
                @endif

                @if ($source === 'referral' && !empty($referralToken))
                    <a href="{{ route('cfa.apply', ['token' => $referralToken]) }}" class="btn-new">New registration</a>
                @else
                    <a href="{{ route('cfa.public.show') }}" class="btn-new">New registration</a>
                @endif

                <p class="powered">{{ config('app.name') }}</p>
            </div>
        </div>
    </div>
    @include('partials.app-footer')
</body>
</html>

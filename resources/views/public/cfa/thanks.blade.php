<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Application submitted — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    @include('partials.admin-shell-styles')
    <style>
        .thanks-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, #059669, #0d9488);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #fff;
        }
        .thanks-card h1 { color: #047857; text-align: center; }
        .thanks-card .app-no {
            font-size: 1.65rem;
            font-weight: 700;
            background: linear-gradient(90deg, #4f46e5, #0d9488);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin: 0.5rem 0 1.25rem;
            text-align: center;
            letter-spacing: 0.04em;
        }
        .thanks-card p { text-align: center; }
    </style>
</head>
<body class="app-auth-body">
    <div class="app-auth-wrap">
        <div class="app-auth-card thanks-card app-auth-card--wide">
            <div class="thanks-icon">✓</div>
            <h1>Application submitted successfully</h1>
            @if (!empty($applicationNo))
                <p class="app-auth-lead" style="margin-bottom:0.35rem;">Your application number is</p>
                <p class="app-no">{{ $applicationNo }}</p>
                <p class="app-auth-lead">Please save this number for future reference.</p>
            @else
                <p class="app-auth-lead">Thank you. Your application has been received.</p>
            @endif
        </div>
    </div>
</body>
</html>

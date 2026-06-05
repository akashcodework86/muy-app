@extends('layouts.admin')

@section('title', 'Legacy incubatee profile')
@section('heading', 'Phase 2 — Incubatee profile')

@php
    /** Same-origin for html2canvas; proxied in {@see LegacyPhase2IncubateeProfileController::headerLogo} */
    $headerLogoUrl = route('staff.phase2-profile.logo');
@endphp

@push('styles')
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" as="script" crossorigin="anonymous">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Compact sheet: target ~2 A4 pages when exported with html2pdf */
        #p2-profile {
            --p2-gap: 0.65rem;
            --p2-pad: 0.65rem;
            --p2-crest: 56px;
            font-size: 11px;
            line-height: 1.35;
            color: #1e293b;
        }
        @media print {
            .p2-no-print,
            .admin-topbar,
            .admin-page-head,
            .muy-footer {
                display: none !important;
            }
            body.admin-app-body .admin-main {
                max-width: none !important;
                padding: 0.35rem !important;
            }
            #p2-profile { font-size: 10.5px; }
            .p2-slider { flex-wrap: wrap !important; transform: none !important; }
            .p2-slide { flex: 0 0 33.33% !important; max-width: 33.33%; }
        }
        .p2-watermark::before {
            content: "Mukhya Mantri Udyamshala Yojana (MUY) - MIS SYSTEM";
            position: absolute;
            top: 38%;
            left: 4%;
            font-size: 1.65rem;
            color: #9ca3af;
            transform: rotate(-28deg);
            z-index: 0;
            opacity: 0.08;
            white-space: nowrap;
            pointer-events: none;
            font-weight: 600;
        }
        .p2-print-area {
            background-color: #fff;
            padding: 0.75rem 0.85rem 1rem;
            border-radius: 0.65rem;
            position: relative;
            overflow: visible;
            max-width: 52rem;
            margin-left: auto;
            margin-right: auto;
        }
        .p2-profile-header {
            display: grid;
            grid-template-columns: var(--p2-crest) minmax(0, 1fr) var(--p2-crest);
            align-items: center;
            column-gap: 0.85rem;
            row-gap: 0.35rem;
            padding: 0.65rem 0.85rem;
            margin-bottom: var(--p2-gap);
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            position: relative;
            z-index: 1;
        }
        @media (max-width: 720px) {
            .p2-profile-header {
                grid-template-columns: var(--p2-crest) var(--p2-crest);
                grid-template-rows: auto auto;
                justify-content: center;
                column-gap: 2.5rem;
                row-gap: 0.5rem;
            }
            .p2-profile-header__crest--left { grid-column: 1; grid-row: 1; justify-self: end; }
            .p2-profile-header__crest--right { grid-column: 2; grid-row: 1; justify-self: start; }
            .p2-profile-header__main {
                grid-column: 1 / -1;
                grid-row: 2;
                width: 100%;
            }
        }
        .p2-profile-header__crest {
            width: var(--p2-crest);
            height: var(--p2-crest);
            box-sizing: border-box;
            padding: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid #e2e8f0;
            border-radius: 0.4rem;
            background: #fafafa;
        }
        .p2-profile-header__crest img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
        }
        .p2-profile-header__main {
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 0.45rem;
        }
        .p2-profile-header__titles h1 {
            font-size: 1.1rem;
            font-weight: 800;
            margin: 0;
            line-height: 1.2;
            color: #0f172a;
        }
        .p2-profile-header__titles p {
            font-size: 0.76rem;
            margin: 0.15rem 0 0;
            color: #475569;
            font-weight: 600;
        }
        .p2-profile-header__meta {
            width: 100%;
            max-width: 22rem;
            text-align: center;
            font-size: 0.7rem;
            color: #334155;
        }
        .p2-profile-header__meta h2 {
            font-size: 0.78rem;
            font-weight: 800;
            margin: 0 0 0.15rem;
            color: #0f172a;
        }
        .p2-profile-header__meta p { margin: 0.06rem 0; line-height: 1.3; }
        .p2-profile-header__meta strong { color: #0f172a; }
        .p2-photo-block {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
        }
        .p2-profile-pic {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        }
        .p2-card-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--p2-gap);
            margin-bottom: var(--p2-gap);
            page-break-inside: avoid;
        }
        @media (max-width: 900px) {
            .p2-card-grid { grid-template-columns: 1fr; }
        }
        .p2-card {
            border-radius: 0.45rem;
            padding: var(--p2-pad);
            border: 1px solid #e2e8f0;
        }
        .p2-card h2 {
            font-size: 0.78rem;
            font-weight: 800;
            margin: 0 0 0.35rem;
            padding-bottom: 0.25rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.45);
        }
        .p2-card ul { margin: 0; padding: 0; list-style: none; }
        .p2-card li {
            display: flex;
            gap: 0.35rem;
            padding: 0.12rem 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 0.72rem;
        }
        .p2-card li:last-child { border-bottom: none; }
        .p2-card li strong {
            flex: 0 0 42%;
            max-width: 9.5rem;
            color: #475569;
            font-weight: 700;
        }
        .p2-card li span { flex: 1; min-width: 0; word-break: break-word; }
        .p2-card--personal { background: #eff6ff; }
        .p2-card--personal h2 { color: #1e3a8a; border-color: #bfdbfe; }
        .p2-card--address { background: #f8fafc; }
        .p2-card--address h2 { color: #1e293b; }
        .p2-card--enterprise { background: #ecfdf5; }
        .p2-card--enterprise h2 { color: #14532d; border-color: #a7f3d0; }
        .p2-section {
            margin-bottom: var(--p2-gap);
            padding: var(--p2-pad);
            border: 1px solid #e2e8f0;
            border-radius: 0.45rem;
            background: #fff;
            position: relative;
            z-index: 1;
        }
        .p2-section h2 {
            font-size: 0.78rem;
            font-weight: 800;
            margin: 0 0 0.35rem;
            color: #0f172a;
        }
        .p2-section p { margin: 0; font-size: 0.72rem; color: #334155; }
        .p2-services-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.68rem;
        }
        .p2-services-table th,
        .p2-services-table td {
            border: 1px solid #e2e8f0;
            padding: 0.22rem 0.35rem;
            text-align: left;
            vertical-align: top;
        }
        .p2-services-table th {
            background: #f1f5f9;
            font-weight: 700;
            color: #334155;
        }
        .p2-services-table tbody tr:nth-child(even) { background: #fafafa; }
        .p2-footer {
            margin-top: 0.65rem;
            padding-top: 0.5rem;
            border-top: 1px solid #cbd5e1;
            font-size: 0.65rem;
            color: #64748b;
            position: relative;
            z-index: 1;
        }
        .p2-footer-sign {
            display: flex;
            justify-content: flex-end;
            padding: 0.5rem 1.5rem 0.25rem 0;
        }
        .p2-footer-sign > div { text-align: center; }
        .p2-footer-sign .p2-sign-line {
            margin-top: 1.75rem;
            border-top: 1px solid #94a3b8;
            width: 11rem;
        }
        .p2-slider-wrap { position: relative; max-width: 100%; overflow: hidden; }
        .p2-slider { display: flex; transition: transform 0.5s ease-in-out; }
        .p2-slide { flex: 0 0 33.33%; padding: 6px; box-sizing: border-box; }
        .p2-slide img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .p2-slider-nav {
            position: absolute;
            top: 50%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            transform: translateY(-50%);
        }
        .p2-slider-nav button {
            background: rgba(0,0,0,0.45);
            color: white;
            border: none;
            padding: 8px;
            cursor: pointer;
            border-radius: 50%;
            font-size: 15px;
        }
        .p2-upload-note { font-size: 0.65rem; color: #64748b; max-width: 22rem; margin: 0.35rem auto 0; line-height: 1.35; }
        #p2-downloadBtn {
            padding: 0.45rem 0.95rem;
            border-radius: 0.5rem;
            border: none;
            background: #2563eb;
            color: #fff;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
        }
        #p2-downloadBtn:hover { background: #1d4ed8; }
        #p2-downloadBtn:disabled { opacity: 0.8; cursor: wait; }
    </style>
@endpush

@section('content')
    @php
        $a = $profile['application'] ?? [];
        $p = $profile['applicant'] ?? [];
        $e = $profile['enterprise'] ?? [];
        $svcRows = $profile['services'] ?? [];
        $imgs = $profile['product_image_urls'] ?? [];
        $regNorm = mb_strtolower(trim((string) ($e['is_registered'] ?? '')));
        $regYes = in_array($regNorm, ['yes', 'y', '1', 'true'], true);
        $pdfSlug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($a['application_no'] ?? 'app'));
    @endphp

    <div class="p2-no-print" style="display:flex;flex-wrap:wrap;gap:0.45rem;margin-bottom:0.65rem;align-items:center;">
        <a href="{{ url()->previous() }}" class="inline-flex items-center rounded-md bg-zinc-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-zinc-700">← Back</a>
        <button type="button" id="p2-downloadBtn" onclick="p2DownloadPdf()">Download PDF</button>
    </div>

    @if (session('status'))
        <p class="p2-no-print" style="margin:0 0 0.55rem;padding:0.45rem 0.65rem;border-radius:0.375rem;background:#ecfdf5;color:#065f46;font-size:0.75rem;font-weight:600;">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="p2-no-print" style="margin:0 0 0.55rem;padding:0.45rem 0.65rem;border-radius:0.375rem;background:#fef2f2;color:#991b1b;font-size:0.75rem;">
            @foreach ($errors->all() as $error)
                <p style="margin:0;">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div id="p2-profile" class="shadow-sm p2-print-area p2-watermark">
        <header class="p2-profile-header">
            <div class="p2-profile-header__crest p2-profile-header__crest--left" title="Government of Uttarakhand">
                <img src="{{ asset('images/Seal_of_Uttarakhand.svg') }}" alt="Uttarakhand government seal" width="56" height="56" loading="eager" decoding="async">
            </div>
            <div class="p2-profile-header__main">
                <div class="p2-profile-header__titles">
                    <h1>मुख्यमंत्री उद्यमशाला योजना</h1>
                    <p>Rural Business Incubator · Incubatee profile</p>
                </div>
                <div class="p2-photo-block">
                    <img src="{{ $profilePhotoUrl }}" alt="" class="p2-profile-pic" width="72" height="72" crossorigin="anonymous" referrerpolicy="no-referrer" loading="eager" decoding="async">
                </div>
                <div class="p2-profile-header__meta">
                    <h2>Application</h2>
                    <p><strong>No.</strong> {{ $a['application_no'] ?? '—' }}</p>
                    <p><strong>Submitted</strong> {{ $a['submission_date'] ?? '—' }}</p>
                    <p><strong>Stage</strong> {{ $a['form_stage'] ?? '—' }}</p>
                    <p><strong>Category</strong> {{ $a['category'] ?? '—' }}</p>
                    <p style="font-size:0.65rem;color:#64748b;margin-top:0.15rem;">Legacy id {{ (int) ($profile['legacy_application_id'] ?? 0) }}</p>
                </div>
            </div>
            <div class="p2-profile-header__crest p2-profile-header__crest--right" title="MUY">
                <img src="{{ $headerLogoUrl }}" alt="MUY logo" width="56" height="56" crossorigin="anonymous" loading="eager" decoding="async">
            </div>
        </header>

        <div class="p2-no-print" style="text-align:center;margin-bottom:0.55rem;">
            <form action="{{ route('staff.phase2-profile.photo.upload', ['legacy_application' => $profile['legacy_application_id']]) }}" method="post" enctype="multipart/form-data" style="display:inline-flex;flex-wrap:wrap;gap:0.4rem;align-items:center;justify-content:center;">
                @csrf
                <label class="text-xs text-gray-600">Profile photo</label>
                <input type="file" name="profile_pic" accept="image/*" class="text-xs max-w-[14rem]">
                <button type="submit" class="rounded bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-blue-700">Save</button>
            </form>
            <p class="p2-upload-note">Saved in this MIS when a legacy <code>rbi_onboarded_applicants</code> row exists for this application.</p>
        </div>

        <div class="p2-card-grid">
            <div class="p2-card p2-card--personal">
                <h2>Personal details</h2>
                <ul>
                    <li><strong>Name</strong><span>{{ $p['applicant_name'] ?? '—' }}</span></li>
                    <li><strong>Gender</strong><span>{{ $p['gender'] ?? '—' }}</span></li>
                    <li><strong>DOB</strong><span>{{ $p['dob'] ?? '—' }}</span></li>
                    <li><strong>Phone</strong><span>{{ $p['phone'] ?? '—' }}</span></li>
                    <li><strong>Education</strong><span>{{ $p['education'] ?? '—' }}</span></li>
                    <li><strong>Emp. women</strong><span>{{ $p['empwomen'] ?? '—' }}</span></li>
                    <li><strong>Tech use</strong><span>{{ $p['techuse'] ?? '—' }}</span></li>
                    <li><strong>Challenges</strong><span>{{ $p['challenges'] ?? '—' }}</span></li>
                    <li><strong>Email</strong><span>{{ $p['email'] ?? '—' }}</span></li>
                    <li><strong>Caste</strong><span>{{ $p['caste'] ?? '—' }}</span></li>
                    <li><strong>SHG member</strong><span>{{ $p['is_shg_member'] ?? '—' }}</span></li>
                    <li><strong>SHG name</strong><span>{{ $p['shg_name'] ?? '—' }}</span></li>
                    <li>
                        <strong>Lakhpati Didi</strong>
                        <span>
                            {{ $p['lakhpati'] ?? '—' }}
                            @if (! empty($profile['lakhpati_editable']))
                                <form action="{{ route('staff.phase2-profile.lakhpati.update', ['legacy_application' => $profile['legacy_application_id']]) }}" method="post" class="p2-no-print" style="display:inline-flex;flex-wrap:wrap;gap:0.25rem;margin-left:0.35rem;align-items:center;vertical-align:middle;">
                                    @csrf
                                    <select name="lakhpati" class="rounded border border-slate-300 px-1 py-0.5 text-xs" aria-label="Lakhpati Didi">
                                        <option value="Yes" @selected(($p['lakhpati'] ?? '') === 'Yes')>Yes</option>
                                        <option value="No" @selected(($p['lakhpati'] ?? '') !== 'Yes')>No</option>
                                    </select>
                                    <button type="submit" class="rounded bg-blue-600 px-2 py-0.5 text-xs font-semibold text-white hover:bg-blue-700">Save</button>
                                </form>
                            @endif
                        </span>
                    </li>
                </ul>
            </div>

            <div class="p2-card p2-card--address">
                <h2>Address &amp; ID</h2>
                <ul>
                    <li><strong>District</strong><span>{{ $p['district'] ?? '—' }}</span></li>
                    <li><strong>Block</strong><span>{{ $p['block'] ?? '—' }}</span></li>
                    <li><strong>Village</strong><span>{{ $p['village'] ?? '—' }}</span></li>
                    <li><strong>ID type</strong><span>{{ $p['id_proof_type'] ?? '—' }}</span></li>
                    <li><strong>ID number</strong><span>{{ $p['id_proof_number'] ?? '—' }}</span></li>
                    <li><strong>Migrated (emp.)</strong><span>{{ $p['migrated_for_employment'] ?? '—' }}</span></li>
                    <li><strong>Submitted by</strong><span>{{ $p['submitted_by_name'] ?? '—' }} · {{ $p['submitted_by_mobile'] ?? '—' }}</span></li>
                </ul>
            </div>

            <div class="p2-card p2-card--enterprise">
                <h2>Enterprise</h2>
                <ul>
                    <li><strong>Name</strong><span>{{ $e['enterprise_name'] ?? '—' }}</span></li>
                    <li><strong>Registered</strong><span>{{ $e['is_registered'] ?? '—' }}</span></li>
                    @if ($regYes)
                        <li><strong>Reg. type</strong><span>{{ $e['registration_type'] ?? '—' }}</span></li>
                        <li><strong>Reg. no.</strong><span>{{ $e['registration_number'] ?? '—' }}</span></li>
                        <li><strong>Reg. date</strong><span>{{ $e['registration_date'] ?? '—' }}</span></li>
                    @endif
                    <li><strong>Sector</strong><span>{{ $e['sector'] ?? '—' }}</span></li>
                    <li><strong>Product</strong><span>{{ $a['product'] ?? '—' }}</span></li>
                    @if (! empty($a['other_product']))
                        <li><strong>Other product</strong><span>{{ $a['other_product'] }}</span></li>
                    @endif
                    <li><strong>Turnover</strong><span>₹{{ $e['turnover_last_year'] ?? '—' }}</span></li>
                    <li><strong>Years in bus.</strong><span>{{ $e['years_in_business'] ?? '—' }}</span></li>
                    <li><strong>Team size</strong><span>{{ $e['team_size'] ?? '—' }}</span></li>
                    <li><strong>Location</strong><span>{{ $e['location_type'] ?? '—' }}</span></li>
                    <li><strong>Support needed</strong><span>{{ $e['support_needed'] ?? '—' }}</span></li>
                    <li><strong>Training</strong><span>{{ $e['training_received'] ?? '—' }}</span></li>
                    <li><strong>Institute</strong><span>{{ $e['training_institute'] ?? '—' }}</span></li>
                </ul>
            </div>
        </div>

        <div class="p2-section">
            <h2>Applicant expectations</h2>
            <p class="whitespace-pre-wrap" style="font-size:0.72rem;">{{ $p['expectations'] ?? '—' }}</p>
            @if (! empty($p['expectation_other']))
                <p class="mt-1" style="font-size:0.72rem;"><strong>Other:</strong> {{ $p['expectation_other'] }}</p>
            @endif
        </div>

        <div class="p2-section">
            <h2>Services provided</h2>
            @if ($svcRows === [])
                <p class="text-gray-500" style="font-size:0.72rem;">No services recorded.</p>
            @else
                <table class="p2-services-table">
                    <thead>
                        <tr>
                            <th style="width:2rem;">#</th>
                            <th>Service</th>
                            <th style="width:18%;">Category</th>
                            <th style="width:20%;">Served by</th>
                            <th style="width:12%;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($svcRows as $i => $svc)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $svc['service_name'] ?? '' }}</td>
                                <td>{{ $svc['category'] ?? '' }}</td>
                                <td>{{ $svc['served_by_name'] ?? '' }}</td>
                                <td>
                                    @if (! empty($svc['assigned_on']))
                                        {{ \Illuminate\Support\Carbon::parse($svc['assigned_on'])->format('d M y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="p2-section p2-slider-wrap">
            <h2>Product images</h2>
            <div class="p2-slider" id="p2-productSlider">
                @forelse ($imgs as $index => $url)
                    <div class="p2-slide">
                        <img src="{{ $url }}" alt="Product {{ $index + 1 }}" loading="eager" decoding="async" referrerpolicy="no-referrer">
                    </div>
                @empty
                    <div class="p2-slide">
                        <img src="https://placehold.co/300x140/e5e7eb/64748b?text=No+image" alt="No image">
                    </div>
                @endforelse
            </div>
            <div class="p2-slider-nav p2-no-print">
                <button type="button" onclick="p2PrevSlide()">❮</button>
                <button type="button" onclick="p2NextSlide()">❯</button>
            </div>
        </div>

        <footer class="p2-footer">
            <div class="p2-footer-sign">
                <div>
                    <p style="margin:0;font-size:0.7rem;color:#334155;">Authorized signatory</p>
                    <div class="p2-sign-line"></div>
                </div>
            </div>
            <p style="margin:0.35rem 0 0;text-align:center;">Mukhya Mantri Udyamshala Yojana (MUY) — legacy Phase 2 data.</p>
        </footer>
    </div>
@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        const p2PdfSlug = @json($pdfSlug);
        let p2PdfBusy = false;

        (function p2WarmImages() {
            const run = () => {
                const el = document.getElementById('p2-profile');
                if (!el) return;
                el.querySelectorAll('img').forEach((img) => {
                    if (img.decode) {
                        img.decode().catch(() => {});
                    }
                });
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', run, { once: true });
            } else {
                run();
            }
        }());

        function p2WaitProfileImages(root, maxMs) {
            const cap = maxMs === undefined ? 1800 : maxMs;
            const imgs = Array.from(root.querySelectorAll('img'));
            const waitOne = (img) => new Promise((resolve) => {
                if (img.complete && img.naturalWidth > 0) {
                    resolve();
                    return;
                }
                const done = () => resolve();
                img.addEventListener('load', done, { once: true });
                img.addEventListener('error', done, { once: true });
            });
            return Promise.race([
                Promise.all(imgs.map(waitOne)),
                new Promise((r) => setTimeout(r, cap)),
            ]);
        }

        function p2TryInlineExternalProfilePhotoFast(root, maxMs) {
            const cap = maxMs === undefined ? 900 : maxMs;
            const pic = root.querySelector('.p2-profile-pic');
            if (!pic || !pic.src) {
                return Promise.resolve();
            }
            return new Promise((resolve) => {
                const done = () => resolve();
                const t = setTimeout(done, cap);
                try {
                    const u = new URL(pic.src, location.href);
                    if (u.origin === location.origin) {
                        clearTimeout(t);
                        done();
                        return;
                    }
                    const im = new Image();
                    im.crossOrigin = 'anonymous';
                    im.onload = () => {
                        try {
                            const c = document.createElement('canvas');
                            const w = im.naturalWidth || 120;
                            const h = im.naturalHeight || 120;
                            c.width = w;
                            c.height = h;
                            c.getContext('2d').drawImage(im, 0, 0);
                            pic.src = c.toDataURL('image/jpeg', 0.82);
                        } catch (err) { /* keep original */ }
                        clearTimeout(t);
                        done();
                    };
                    im.onerror = () => {
                        clearTimeout(t);
                        done();
                    };
                    im.src = pic.src;
                } catch (e) {
                    clearTimeout(t);
                    done();
                }
            });
        }

        async function p2DownloadPdf() {
            if (p2PdfBusy) return;
            const button = document.getElementById('p2-downloadBtn');
            const element = document.getElementById('p2-profile');
            if (!element) {
                alert('Profile content not found.');
                return;
            }
            if (typeof html2pdf === 'undefined') {
                alert('PDF library failed to load. Refresh the page and try again.');
                return;
            }

            p2PdfBusy = true;
            if (button) {
                button.disabled = true;
            }

            window.scrollTo(0, element.offsetTop);

            try {
                await p2WaitProfileImages(element, 1600);
                await p2TryInlineExternalProfilePhotoFast(element, 800);

                const opt = {
                    margin: [0.32, 0.38, 0.32, 0.38],
                    filename: 'incubatee_profile_' + p2PdfSlug + '.pdf',
                    image: { type: 'jpeg', quality: 0.82 },
                    html2canvas: {
                        scale: 1.35,
                        useCORS: true,
                        allowTaint: false,
                        logging: false,
                        backgroundColor: '#ffffff',
                        scrollX: 0,
                        scrollY: 0,
                        imageTimeout: 5000,
                        onclone: function (clonedDoc) {
                            clonedDoc.querySelectorAll('.p2-no-print').forEach((n) => n.remove());
                            const root = clonedDoc.getElementById('p2-profile');
                            if (root) {
                                root.classList.remove('p2-watermark');
                                root.style.overflow = 'visible';
                                root.style.maxHeight = 'none';
                                root.style.boxShadow = 'none';
                            }
                            const slider = clonedDoc.getElementById('p2-productSlider');
                            if (slider) {
                                slider.style.transform = 'none';
                                slider.style.flexWrap = 'wrap';
                            }
                            const b = clonedDoc.body;
                            if (b) {
                                b.style.margin = '0';
                                b.style.padding = '0';
                                b.style.background = '#ffffff';
                            }
                        },
                    },
                    jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' },
                    pagebreak: { mode: ['legacy'] },
                };

                await html2pdf().set(opt).from(element).save();
            } catch (e) {
                console.error(e);
                alert('Could not generate PDF. Try again in a moment.');
            } finally {
                p2PdfBusy = false;
                if (button) {
                    button.disabled = false;
                }
            }
        }

        let p2CurrentSlide = 0;
        const p2Slides = () => document.querySelectorAll('.p2-slide');
        function p2ShowSlide(index) {
            const slider = document.getElementById('p2-productSlider');
            const slides = p2Slides();
            const total = slides.length;
            if (!slider || total === 0) return;
            if (index >= total) p2CurrentSlide = 0;
            else if (index < 0) p2CurrentSlide = total - 1;
            else p2CurrentSlide = index;
            slider.style.transform = 'translateX(-' + (p2CurrentSlide * 33.33) + '%)';
        }
        function p2NextSlide() { p2ShowSlide(p2CurrentSlide + 1); }
        function p2PrevSlide() { p2ShowSlide(p2CurrentSlide - 1); }
        if (p2Slides().length > 1) {
            setInterval(p2NextSlide, 5000);
        }
        @if (! empty($autopdf))
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(function () { p2DownloadPdf(); }, 200);
        });
        @endif
    </script>
@endpush

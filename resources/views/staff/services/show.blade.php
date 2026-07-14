@extends('layouts.admin')

@section('title', 'Service case')
@section('heading', 'Service case')

@section('content')
    <style>
        .svc-doc-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            border-radius: 8px;
            padding: 0.25rem 0.55rem;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
        }
        .svc-doc-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.55);
            z-index: 80;
            padding: 1rem;
        }
        .svc-doc-modal.is-open {
            display: flex;
        }
        .svc-doc-modal__card {
            width: min(980px, 96vw);
            max-height: 92vh;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.28);
            display: flex;
            flex-direction: column;
        }
        .svc-doc-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.8rem;
            padding: 0.7rem 0.9rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .svc-doc-modal__title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .svc-doc-modal__close {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            border-radius: 8px;
            padding: 0.25rem 0.55rem;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .svc-doc-modal__body {
            padding: 0.8rem;
            overflow: auto;
            background: #f8fafc;
            min-height: 320px;
        }
        .svc-doc-modal__frame {
            width: 100%;
            min-height: 72vh;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }
        .svc-doc-modal__img {
            max-width: 100%;
            max-height: 72vh;
            display: block;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }
        .svc-through-reap-badge {
            display: inline-flex;
            align-items: center;
            margin-left: 0.35rem;
            padding: 0.16rem 0.5rem;
            border-radius: 999px;
            border: 1px solid #fdba74;
            background: linear-gradient(180deg, #fff7ed 0%, #ffedd5 100%);
            color: #9a3412;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            vertical-align: middle;
        }
    </style>

    <p style="margin:0 0 1rem;">
        <a href="{{ route('staff.services.index') }}">← Service cases</a>
    </p>

    @php
        use App\Support\ConvergenceReapSupport;
        use App\Support\SchemaValueFormatter;
        use App\Support\ServiceFieldTypes;
        $schema = ServiceFieldTypes::normalizeSchema($case->service?->field_schema ?? []);
        $payload = is_array($case->payload) ? $case->payload : [];
    @endphp

    @php
        $status = (string) $case->status;
        $statusLabel = ucwords(str_replace('_', ' ', $status));
        $statusStyles = [
            'draft' => ['bg' => '#f1f5f9', 'fg' => '#334155', 'bd' => '#cbd5e1'],
            'pending_approval' => ['bg' => '#fef3c7', 'fg' => '#92400e', 'bd' => '#fcd34d'],
            'sent_back' => ['bg' => '#fee2e2', 'fg' => '#991b1b', 'bd' => '#fecaca'],
            'approved' => ['bg' => '#dcfce7', 'fg' => '#166534', 'bd' => '#86efac'],
            'rejected' => ['bg' => '#ffe4e6', 'fg' => '#9f1239', 'bd' => '#fda4af'],
        ][$status] ?? ['bg' => '#f4f4f5', 'fg' => '#3f3f46', 'bd' => '#e4e4e7'];
    @endphp

    <div style="background:linear-gradient(135deg,#ffffff 0%,#f8fafc 100%);border:1px solid #e4e4e7;border-radius:14px;padding:1rem 1.1rem;max-width:48rem;margin-bottom:1rem;box-shadow:0 8px 22px -18px rgba(15,23,42,0.3);">
        <h2 style="margin:0 0 0.4rem;font-size:1.15rem;">
            {{ $case->service?->name ?? 'Service' }}
            @include('staff.services.partials.through-reap-badge', ['case' => $case])
        </h2>
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.45rem;font-size:0.85rem;color:#52525b;">
            <span style="display:inline-flex;align-items:center;padding:0.15rem 0.55rem;border-radius:999px;border:1px solid {{ $statusStyles['bd'] }};background:{{ $statusStyles['bg'] }};color:{{ $statusStyles['fg'] }};font-weight:700;">
                {{ $statusLabel }}
            </span>
            @if ($case->reference_number)
                <span><strong>Ref:</strong> {{ $case->reference_number }}</span>
            @endif
        </div>
        @if ($case->delivered_on)
            <p style="margin:0.45rem 0 0;font-size:0.85rem;color:#52525b;"><strong>Delivered on:</strong> {{ $case->delivered_on->format('d M Y') }}</p>
        @endif
        @if ($case->sla_deadline_at)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#52525b;"><strong>SPOC SLA target:</strong> {{ $case->sla_deadline_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</p>
        @endif
    </div>

    @if ($case->sent_back_note)
        <div style="max-width:48rem;margin-bottom:1rem;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:12px;padding:0.8rem 0.95rem;">
            <p style="margin:0;font-size:0.76rem;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">SPOC send-back remark</p>
            <p style="margin:0.25rem 0 0;font-size:0.9rem;line-height:1.5;">{{ $case->sent_back_note }}</p>
        </div>
    @endif

    @if ($case->rejected_note)
        <div style="max-width:48rem;margin-bottom:1rem;background:#fff1f2;border:1px solid #fecdd3;color:#9f1239;border-radius:12px;padding:0.8rem 0.95rem;">
            <p style="margin:0;font-size:0.76rem;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;">SPOC rejection reason</p>
            <p style="margin:0.25rem 0 0;font-size:0.9rem;line-height:1.5;">{{ $case->rejected_note }}</p>
        </div>
    @endif

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:48rem;margin-bottom:1rem;box-shadow:0 8px 22px -18px rgba(15,23,42,0.3);">
        <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Incubatee</h3>
        <p style="margin:0;font-size:0.88rem;"><strong>
            @if ($case->cfaSubmission)
                {{ $case->cfaSubmission->applicant_name }}
            @elseif (! empty($legacyIncubateePreview))
                {{ $legacyIncubateePreview['applicant_name'] ?: '—' }}
            @else
                —
            @endif
        </strong></p>
        @if ($case->cfaSubmission?->application_no)
            <p style="margin:0.25rem 0 0;font-size:0.82rem;color:#52525b;">{{ $case->cfaSubmission->application_no }}</p>
        @elseif (! empty($legacyIncubateePreview['application_no'] ?? ''))
            <p style="margin:0.25rem 0 0;font-size:0.82rem;color:#52525b;">{{ $legacyIncubateePreview['application_no'] }}</p>
            <p style="margin:0.2rem 0 0;font-size:0.75rem;color:#64748b;">Phase 2 legacy application #{{ $case->legacy_application_id }}</p>
        @endif
    </div>

    @if ($schema !== [])
        <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:48rem;margin-bottom:1rem;box-shadow:0 8px 22px -18px rgba(15,23,42,0.3);">
            <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Submitted details</h3>
            <dl style="margin:0;display:grid;gap:0.5rem;">
                @foreach ($schema as $field)
                    @php $k = $field['key']; @endphp
                    <div>
                        <dt style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:#64748b;font-weight:700;">{{ $field['label'] }}</dt>
                        <dd style="margin:0.15rem 0 0;font-size:0.88rem;">{!! SchemaValueFormatter::renderHtml($field, $payload[$k] ?? null) !!}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    @if ($case->displaysReapSupportRoute())
        @include('staff.services.partials.through-reap-summary', ['case' => $case, 'payload' => $payload])
    @endif

    @include('partials.service-case-document-cards', [
        'case' => $case,
        'attachmentRoute' => 'staff.services.attachments.download',
        'docButtonClass' => 'svc-doc-btn js-doc-open',
    ])

    @if (($staffDeleteEnabled ?? true) && $case->canBeDeletedByStaff())
        <form method="post" action="{{ route('staff.services.destroy', $case) }}" onsubmit="return confirm('Delete this case?');" style="margin-top:0.5rem;">
            @csrf
            @method('DELETE')
            <button type="submit" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:0.45rem 0.85rem;border-radius:8px;font-weight:600;cursor:pointer;">Delete case</button>
        </form>
    @endif

    <div id="svcDocModal" class="svc-doc-modal" aria-hidden="true">
        <div class="svc-doc-modal__card" role="dialog" aria-modal="true" aria-label="Document preview">
            <div class="svc-doc-modal__head">
                <div id="svcDocTitle" class="svc-doc-modal__title">Document</div>
                <button type="button" id="svcDocClose" class="svc-doc-modal__close">Close</button>
            </div>
            <div id="svcDocBody" class="svc-doc-modal__body"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('svcDocModal');
            var modalBody = document.getElementById('svcDocBody');
            var modalTitle = document.getElementById('svcDocTitle');
            var closeBtn = document.getElementById('svcDocClose');
            var openButtons = Array.prototype.slice.call(document.querySelectorAll('.js-doc-open'));

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                modalBody.innerHTML = '';
            }

            function openModal(url, name) {
                modalTitle.textContent = name || 'Document';
                modalBody.innerHTML = '';

                var lower = (name || url || '').toLowerCase();
                if (lower.endsWith('.pdf')) {
                    var frame = document.createElement('iframe');
                    frame.className = 'svc-doc-modal__frame';
                    frame.src = url;
                    frame.title = name || 'Document';
                    modalBody.appendChild(frame);
                } else if (/\.(png|jpg|jpeg|webp|gif)$/i.test(lower)) {
                    var img = document.createElement('img');
                    img.className = 'svc-doc-modal__img';
                    img.alt = name || 'Document image';
                    img.src = url;
                    modalBody.appendChild(img);
                } else {
                    var fallback = document.createElement('div');
                    fallback.style.fontSize = '0.86rem';
                    fallback.style.color = '#334155';
                    fallback.innerHTML = 'Preview not supported for this file type. <a href="' + url + '" target="_blank" rel="noopener">Open document</a>.';
                    modalBody.appendChild(fallback);
                }

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            }

            openButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openModal(btn.getAttribute('data-doc-url') || '', btn.getAttribute('data-doc-name') || 'Document');
                });
            });

            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal);
            }
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeModal();
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });
        });
    </script>
@endsection

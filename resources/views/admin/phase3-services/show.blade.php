@extends('layouts.admin')

@section('title', 'Service case details')
@section('heading', 'Service case details')

@section('content')
    <style>
        .p3-doc-btn {
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
        .p3-doc-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.55);
            z-index: 80;
            padding: 1rem;
        }
        .p3-doc-modal.is-open {
            display: flex;
        }
        .p3-doc-modal__card {
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
        .p3-doc-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.8rem;
            padding: 0.7rem 0.9rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .p3-doc-modal__title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .p3-doc-modal__close {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            border-radius: 8px;
            padding: 0.25rem 0.55rem;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .p3-doc-modal__body {
            padding: 0.8rem;
            overflow: auto;
            background: #f8fafc;
            min-height: 320px;
        }
        .p3-doc-modal__frame {
            width: 100%;
            min-height: 72vh;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }
        .p3-doc-modal__img {
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
        <a href="{{ route('admin.phase3-services.index') }}">← Phase 3 service cases</a>
    </p>

    @php
        use App\Support\SchemaValueFormatter;
        use App\Support\ServiceFieldTypes;
        $schema = ServiceFieldTypes::normalizeSchema($case->service?->field_schema ?? []);
        $payload = is_array($case->payload) ? $case->payload : [];
        $status = (string) $case->status;
        $statusLabel = ucwords(str_replace('_', ' ', $status));
        $lip = $legacyIncubateePreview ?? null;
    @endphp

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
        <h2 style="margin:0 0 0.35rem;font-size:1.05rem;">
            {{ $case->service?->name ?? 'Service' }}
            @include('staff.services.partials.through-reap-badge', ['case' => $case])
        </h2>
        <p style="margin:0;font-size:0.85rem;color:#52525b;">
            <strong>Category:</strong> {{ $case->service?->category?->name ?? '—' }}
            · <strong>Status:</strong> {{ $statusLabel }}
            @if ($case->reference_number)
                · <strong>Ref:</strong> {{ $case->reference_number }}
            @endif
        </p>
        <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#52525b;">
            @if ($case->submitted_at)
                <strong>Submitted:</strong> {{ $case->submitted_at->timezone(config('app.timezone'))->format('d M Y, h:i A') }}
            @endif
            @if ($case->submitter?->name)
                · <strong>Assigned by:</strong> {{ $case->submitter->name }}
            @elseif ($case->creator?->name)
                · <strong>Created by:</strong> {{ $case->creator->name }}
            @endif
            @if ($case->spoc?->name)
                · <strong>SPOC:</strong> {{ $case->spoc->name }}
            @endif
        </p>
        @if ($case->delivered_on)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#52525b;"><strong>Delivered on:</strong> {{ $case->delivered_on->format('d M Y') }}</p>
        @endif
        @if ($case->sla_deadline_at)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#52525b;"><strong>SPOC SLA target:</strong> {{ $case->sla_deadline_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</p>
        @endif
        @if ($case->sent_back_note)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#9a3412;"><strong>Send-back note:</strong> {{ $case->sent_back_note }}</p>
        @endif
        @if ($case->rejected_note)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#991b1b;"><strong>Rejection note:</strong> {{ $case->rejected_note }}</p>
        @endif
    </div>

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
        <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Incubatee</h3>
        <p style="margin:0;font-size:0.88rem;"><strong>
            @if ($case->cfaSubmission)
                {{ $case->cfaSubmission->applicant_name }}
            @elseif (is_array($lip))
                {{ $lip['applicant_name'] ?? '—' }}
            @else
                —
            @endif
        </strong></p>
        <p style="margin:0.25rem 0 0;font-size:0.82rem;color:#52525b;">
            @if ($case->cfaSubmission)
                {{ $case->cfaSubmission->application_no ?? '—' }}
                @if ($case->cfaSubmission->district?->name)
                    · {{ $case->cfaSubmission->district->name }}
                @endif
            @elseif (is_array($lip))
                {{ $lip['application_no'] ?? '—' }}
                @if (($lip['district'] ?? '') !== '')
                    · {{ $lip['district'] }}
                @endif
                <span style="color:#64748b;"> · Legacy #{{ $case->legacy_application_id }}</span>
            @else
                —
            @endif
        </p>
        @if ($case->cfaSubmission)
            <p style="margin:0.45rem 0 0;font-size:0.82rem;">
                <a href="{{ route('admin.cfa.show', $case->cfaSubmission) }}">View CFA</a>
            </p>
        @endif
    </div>

    @if ($schema !== [])
        <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
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

    @if ($case->isConvergenceServiceCase())
        <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
            <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">REAP route</h3>
            @if ($case->isMarkedThroughReap())
                <p style="margin:0;font-size:0.88rem;">
                    <span class="svc-through-reap-badge">Through REAP</span>
                    <span style="display:block;margin-top:0.45rem;color:#52525b;font-size:0.82rem;">
                        Counts toward MIS <strong>8.2</strong> and <strong>8.3</strong> when approved.
                    </span>
                </p>
            @else
                <p style="margin:0;font-size:0.88rem;color:#71717a;">Not marked Through REAP.</p>
            @endif
        </div>
    @endif

    @if ($case->attachments->isNotEmpty())
        <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
            <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Attachments</h3>
            <ul style="margin:0;padding-left:1.1rem;font-size:0.85rem;">
                @foreach ($case->attachments as $att)
                    <li style="margin-bottom:0.35rem;">
                        <button
                            type="button"
                            class="p3-doc-btn js-doc-open"
                            data-doc-url="{{ route('admin.phase3-services.attachments.view', [$case, $att]) }}"
                            data-doc-name="{{ $att->original_name }}"
                        >
                            View document
                        </button>
                        <span style="margin-left:0.3rem;">{{ $att->original_name }}</span>
                        <span style="color:#71717a;">({{ number_format((int) ($att->size_bytes / 1024), 0) }} KB)</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($case->events->isNotEmpty())
        <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
            <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Activity</h3>
            <ul style="margin:0;padding-left:1.1rem;font-size:0.85rem;color:#334155;">
                @foreach ($case->events as $event)
                    <li style="margin-bottom:0.35rem;">
                        <strong>{{ str_replace('_', ' ', (string) $event->action) }}</strong>
                        @if ($event->user?->name)
                            · {{ $event->user->name }}
                        @endif
                        @if ($event->created_at)
                            · {{ $event->created_at->timezone(config('app.timezone'))->format('d M Y, h:i A') }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="p3DocModal" class="p3-doc-modal" aria-hidden="true">
        <div class="p3-doc-modal__card" role="dialog" aria-modal="true" aria-label="Document preview">
            <div class="p3-doc-modal__head">
                <div id="p3DocTitle" class="p3-doc-modal__title">Document</div>
                <button type="button" id="p3DocClose" class="p3-doc-modal__close">Close</button>
            </div>
            <div id="p3DocBody" class="p3-doc-modal__body"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('p3DocModal');
            var modalBody = document.getElementById('p3DocBody');
            var modalTitle = document.getElementById('p3DocTitle');
            var closeBtn = document.getElementById('p3DocClose');
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
                    frame.className = 'p3-doc-modal__frame';
                    frame.src = url;
                    frame.title = name || 'Document';
                    modalBody.appendChild(frame);
                } else if (/\.(png|jpg|jpeg|webp|gif)$/i.test(lower)) {
                    var img = document.createElement('img');
                    img.className = 'p3-doc-modal__img';
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

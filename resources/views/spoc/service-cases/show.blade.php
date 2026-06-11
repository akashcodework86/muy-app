@extends('layouts.admin')

@section('title', 'SPOC case review')
@section('heading', 'Case review')

@section('content')
    <style>
        .spoc-doc-btn {
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
        .spoc-doc-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.55);
            z-index: 80;
            padding: 1rem;
        }
        .spoc-doc-modal.is-open {
            display: flex;
        }
        .spoc-doc-modal__card {
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
        .spoc-doc-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.8rem;
            padding: 0.7rem 0.9rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .spoc-doc-modal__title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .spoc-doc-modal__close {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            border-radius: 8px;
            padding: 0.25rem 0.55rem;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .spoc-doc-modal__body {
            padding: 0.8rem;
            overflow: auto;
            background: #f8fafc;
            min-height: 320px;
        }
        .spoc-doc-modal__frame {
            width: 100%;
            min-height: 72vh;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }
        .spoc-doc-modal__img {
            max-width: 100%;
            max-height: 72vh;
            display: block;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }
        .spoc-doc-modal__card--doc-image {
            width: min(1120px, 98vw);
        }
    </style>

    <p style="margin:0 0 1rem;">
        <a href="{{ route('spoc.service-cases.index') }}">← Back to queue</a>
    </p>

    @if (session('status'))
        <p style="background:#dcfce7;color:#166534;padding:0.5rem 0.75rem;border-radius:6px;font-size:0.88rem;margin:0 0 0.75rem;">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul style="color:#b91c1c;margin:0 0 0.75rem;padding-left:1.2rem;font-size:0.88rem;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    @php
        use App\Models\ServiceCase;
        use App\Support\SchemaValueFormatter;
        use App\Support\ServiceFieldTypes;
        $schema = ServiceFieldTypes::normalizeSchema($case->service?->field_schema ?? []);
        $payload = is_array($case->payload) ? $case->payload : [];
        $isPending = $case->status === ServiceCase::STATUS_PENDING_APPROVAL;
    @endphp

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
        <h2 style="margin:0 0 0.35rem;font-size:1.05rem;">{{ $case->service?->name ?? 'Service' }}</h2>
        <p style="margin:0;font-size:0.85rem;color:#52525b;">
            <strong>Status:</strong> {{ str_replace('_', ' ', $case->status) }}
            @if ($case->reference_number)
                · <strong>Ref:</strong> {{ $case->reference_number }}
            @endif
            @if ($case->submitter?->name)
                · <strong>Submitted by:</strong> {{ $case->submitter->name }}
            @endif
        </p>
        @if ($case->delivered_on)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#52525b;"><strong>Delivered on:</strong> {{ $case->delivered_on->format('d M Y') }}</p>
        @endif
        @if ($case->sent_back_note)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#9a3412;"><strong>Last send-back note:</strong> {{ $case->sent_back_note }}</p>
        @endif
        @if ($case->rejected_note)
            <p style="margin:0.35rem 0 0;font-size:0.85rem;color:#991b1b;"><strong>Rejection note:</strong> {{ $case->rejected_note }}</p>
        @endif
    </div>

    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
        <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Incubatee</h3>
        @php $lip = $legacyIncubateePreview ?? null; @endphp
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

    @if ($case->attachments->isNotEmpty())
        <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
            <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Attachments</h3>
            <ul style="margin:0;padding-left:1.1rem;font-size:0.85rem;">
                @foreach ($case->attachments as $att)
                    <li style="margin-bottom:0.35rem;">
                        <button
                            type="button"
                            class="spoc-doc-btn js-doc-open"
                            data-doc-url="{{ route('spoc.service-cases.attachments.download', [$case, $att]) }}"
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

    @if ($isPending)
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:0.75rem;max-width:52rem;">
            <form method="post" action="{{ route('spoc.service-cases.approve', $case) }}" onsubmit="return confirm('Approve this case?');" style="background:#fff;border:1px solid #dcfce7;border-radius:10px;padding:0.8rem;">
                @csrf
                <button type="submit" style="background:#166534;color:#fff;border:none;padding:0.45rem 0.85rem;border-radius:8px;font-weight:600;cursor:pointer;">Approve</button>
                <p style="margin:0.5rem 0 0;font-size:0.78rem;color:#52525b;">Marks case as approved and completed.</p>
            </form>

            <form method="post" action="{{ route('spoc.service-cases.send-back', $case) }}" style="background:#fff;border:1px solid #fed7aa;border-radius:10px;padding:0.8rem;">
                @csrf
                <label for="send_back_note" style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:0.3rem;">Send back note</label>
                <textarea id="send_back_note" name="note" rows="3" required style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;font-size:0.82rem;"></textarea>
                <button type="submit" style="margin-top:0.45rem;background:#9a3412;color:#fff;border:none;padding:0.45rem 0.85rem;border-radius:8px;font-weight:600;cursor:pointer;">Send back</button>
            </form>

            <form method="post" action="{{ route('spoc.service-cases.reject', $case) }}" style="background:#fff;border:1px solid #fecaca;border-radius:10px;padding:0.8rem;">
                @csrf
                <label for="reject_note" style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:0.3rem;">Rejection reason</label>
                <textarea id="reject_note" name="note" rows="3" required style="width:100%;padding:0.4rem 0.5rem;border:1px solid #d4d4d8;border-radius:6px;font-size:0.82rem;"></textarea>
                <button type="submit" style="margin-top:0.45rem;background:#991b1b;color:#fff;border:none;padding:0.45rem 0.85rem;border-radius:8px;font-weight:600;cursor:pointer;">Reject</button>
            </form>
        </div>
    @endif

    @include('partials.muy-doc-image-zoom')

    <div id="spocDocModal" class="spoc-doc-modal" aria-hidden="true">
        <div id="spocDocModalCard" class="spoc-doc-modal__card" role="dialog" aria-modal="true" aria-label="Document preview">
            <div class="spoc-doc-modal__head">
                <div id="spocDocTitle" class="spoc-doc-modal__title">Document</div>
                <button type="button" id="spocDocClose" class="spoc-doc-modal__close">Close</button>
            </div>
            <div id="spocDocBody" class="spoc-doc-modal__body"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('spocDocModal');
            var modalCard = document.getElementById('spocDocModalCard');
            var modalBody = document.getElementById('spocDocBody');
            var modalTitle = document.getElementById('spocDocTitle');
            var closeBtn = document.getElementById('spocDocClose');
            var openButtons = Array.prototype.slice.call(document.querySelectorAll('.js-doc-open'));

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                modalBody.innerHTML = '';
            }

            function openModal(url, name) {
                modalTitle.textContent = name || 'Document';
                modalBody.innerHTML = '';
                if (modalCard) {
                    modalCard.classList.remove('spoc-doc-modal__card--doc-image');
                }

                var lower = (name || url || '').toLowerCase();
                if (lower.endsWith('.pdf')) {
                    var frame = document.createElement('iframe');
                    frame.className = 'spoc-doc-modal__frame';
                    frame.src = url;
                    frame.title = name || 'Document';
                    modalBody.appendChild(frame);
                } else if (/\.(png|jpg|jpeg|webp|gif)$/i.test(lower)) {
                    if (modalCard) {
                        modalCard.classList.add('spoc-doc-modal__card--doc-image');
                    }
                    if (typeof window.muyMountDocImageZoom === 'function') {
                        window.muyMountDocImageZoom(modalBody, url, name);
                    } else {
                        var img = document.createElement('img');
                        img.className = 'spoc-doc-modal__img';
                        img.alt = name || 'Document image';
                        img.src = url;
                        modalBody.appendChild(img);
                    }
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


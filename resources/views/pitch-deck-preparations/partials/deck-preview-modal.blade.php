@once
    @push('styles')
    <style>
        .pdp-deck-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.55);
            z-index: 90;
            padding: 1rem;
        }
        .pdp-deck-modal.is-open { display: flex; }
        .pdp-deck-modal__card {
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
        .pdp-deck-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.8rem;
            padding: 0.7rem 0.9rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .pdp-deck-modal__title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }
        .pdp-deck-modal__actions { display: flex; gap: 0.45rem; flex-shrink: 0; }
        .pdp-deck-modal__btn {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            border-radius: 8px;
            padding: 0.28rem 0.6rem;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
        }
        .pdp-deck-modal__btn--primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }
        .pdp-deck-modal__body {
            padding: 0.8rem;
            overflow: auto;
            background: #f8fafc;
            min-height: 320px;
        }
        .pdp-deck-modal__frame {
            width: 100%;
            min-height: 72vh;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
        }
        .pdp-deck-modal__fallback {
            padding: 1.5rem 1rem;
            text-align: center;
            font-size: 0.9rem;
            color: #334155;
            line-height: 1.5;
        }
        .pdp-deck-modal__fallback p { margin: 0 0 0.75rem; }
    </style>
    @endpush

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('pdpDeckModal');
        if (!modal) {
            return;
        }

        var modalBody = document.getElementById('pdpDeckBody');
        var modalTitle = document.getElementById('pdpDeckTitle');
        var closeBtn = document.getElementById('pdpDeckClose');
        var downloadBtn = document.getElementById('pdpDeckDownload');
        var openButtons = Array.prototype.slice.call(document.querySelectorAll('.js-pdp-deck-preview'));

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            if (modalBody) {
                modalBody.innerHTML = '';
            }
        }

        function openModal(previewUrl, downloadUrl, name) {
            if (!modalBody || !modalTitle) {
                return;
            }

            modalTitle.textContent = name || 'Pitch deck';
            modalBody.innerHTML = '';

            if (downloadBtn) {
                downloadBtn.href = downloadUrl || previewUrl || '#';
            }

            var lower = (name || '').toLowerCase();
            if (lower.endsWith('.pdf')) {
                var frame = document.createElement('iframe');
                frame.className = 'pdp-deck-modal__frame';
                frame.src = previewUrl;
                frame.title = name || 'Pitch deck preview';
                modalBody.appendChild(frame);
            } else if (/\.(ppt|pptx)$/i.test(lower)) {
                var pptFallback = document.createElement('div');
                pptFallback.className = 'pdp-deck-modal__fallback';
                pptFallback.innerHTML =
                    '<p><strong>PowerPoint preview</strong></p>' +
                    '<p>Live slide preview is not supported in the browser. Download the file or open it in PowerPoint.</p>' +
                    '<p><a class="pdp-deck-modal__btn pdp-deck-modal__btn--primary" href="' + (downloadUrl || previewUrl) + '">Download deck</a></p>';
                modalBody.appendChild(pptFallback);
            } else {
                var fallback = document.createElement('div');
                fallback.className = 'pdp-deck-modal__fallback';
                fallback.innerHTML =
                    '<p>Preview not available for this file type.</p>' +
                    '<p><a class="pdp-deck-modal__btn pdp-deck-modal__btn--primary" href="' + (downloadUrl || previewUrl) + '">Download deck</a></p>';
                modalBody.appendChild(fallback);
            }

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        }

        openButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal(
                    btn.getAttribute('data-deck-preview-url') || '',
                    btn.getAttribute('data-deck-download-url') || '',
                    btn.getAttribute('data-deck-name') || 'Pitch deck'
                );
            });
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    });
    </script>
    @endpush
@endonce

<div id="pdpDeckModal" class="pdp-deck-modal" aria-hidden="true">
    <div class="pdp-deck-modal__card" role="dialog" aria-modal="true" aria-label="Pitch deck preview">
        <div class="pdp-deck-modal__head">
            <div id="pdpDeckTitle" class="pdp-deck-modal__title">Pitch deck</div>
            <div class="pdp-deck-modal__actions">
                <a id="pdpDeckDownload" href="#" class="pdp-deck-modal__btn" download>Download</a>
                <button type="button" id="pdpDeckClose" class="pdp-deck-modal__btn">Close</button>
            </div>
        </div>
        <div id="pdpDeckBody" class="pdp-deck-modal__body"></div>
    </div>
</div>

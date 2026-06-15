<div class="doc-upload-progress" data-upload-progress-panel hidden>
    <div class="doc-upload-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <div class="doc-upload-progress__fill" data-upload-progress-fill style="width:0%;"></div>
    </div>
    <p class="doc-upload-progress__label" data-upload-progress-label>Preparing upload…</p>
</div>

@once
    @push('styles')
    <style>
        .doc-upload-progress { margin-top: 0.65rem; }
        .doc-upload-progress__track {
            height: 0.55rem;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }
        .doc-upload-progress__fill {
            height: 100%;
            width: 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #4f46e5, #6366f1);
            transition: width 0.15s ease;
        }
        .doc-upload-progress__label {
            margin: 0.35rem 0 0;
            font-size: 0.8rem;
            color: #475569;
            font-weight: 600;
        }
        .doc-upload-progress.is-indeterminate .doc-upload-progress__fill {
            width: 35% !important;
            animation: doc-upload-indeterminate 1.1s ease-in-out infinite;
        }
        @keyframes doc-upload-indeterminate {
            0% { transform: translateX(-120%); }
            100% { transform: translateX(320%); }
        }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        function csrfToken(form) {
            var input = form.querySelector('input[name="_token"]');
            return input ? input.value : '';
        }

        function formatSize(bytes) {
            if (!bytes || bytes <= 0) return '';
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
            return Math.max(1, Math.round(bytes / 1024)) + ' KB';
        }

        function bindUploadProgress(form) {
            if (!form || form.dataset.uploadProgressBound === '1') return;
            form.dataset.uploadProgressBound = '1';

            form.addEventListener('submit', function (event) {
                var fileInput = form.querySelector('input[type="file"]');
                if (!fileInput || !fileInput.files || !fileInput.files.length) {
                    return;
                }

                event.preventDefault();

                var panel = form.querySelector('[data-upload-progress-panel]');
                var fill = form.querySelector('[data-upload-progress-fill]');
                var label = form.querySelector('[data-upload-progress-label]');
                var track = form.querySelector('.doc-upload-progress__track');
                var submitBtn = form.querySelector('[type="submit"]');
                var file = fileInput.files[0];

                if (panel) {
                    panel.hidden = false;
                    panel.classList.remove('is-indeterminate');
                }
                if (fill) fill.style.width = '0%';
                if (track) track.setAttribute('aria-valuenow', '0');
                if (label) {
                    label.textContent = 'Uploading ' + (file.name || 'file') + ' (' + formatSize(file.size) + ')… 0%';
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.dataset.originalText = submitBtn.textContent;
                    submitBtn.textContent = 'Uploading…';
                }

                var xhr = new XMLHttpRequest();
                xhr.open((form.method || 'POST').toUpperCase(), form.action, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('Accept', 'text/html, application/json');
                var token = csrfToken(form);
                if (token) xhr.setRequestHeader('X-CSRF-TOKEN', token);

                xhr.upload.addEventListener('progress', function (ev) {
                    if (!ev.lengthComputable) {
                        if (panel) panel.classList.add('is-indeterminate');
                        return;
                    }
                    if (panel) panel.classList.remove('is-indeterminate');
                    var pct = Math.min(100, Math.round((ev.loaded / ev.total) * 100));
                    if (fill) fill.style.width = pct + '%';
                    if (track) track.setAttribute('aria-valuenow', String(pct));
                    if (label) {
                        label.textContent = 'Uploading ' + (file.name || 'file') + '… ' + pct + '%';
                    }
                });

                function resetSubmit() {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = submitBtn.dataset.originalText || submitBtn.textContent;
                    }
                }

                xhr.addEventListener('load', function () {
                    if (xhr.status >= 200 && xhr.status < 400) {
                        if (label) label.textContent = 'Upload complete. Redirecting…';
                        if (fill) fill.style.width = '100%';
                        window.location.href = xhr.responseURL || form.action;
                        return;
                    }

                    if (xhr.status === 422) {
                        try {
                            var payload = JSON.parse(xhr.responseText);
                            var messages = payload.errors ? Object.values(payload.errors).flat().join(' ') : (payload.message || 'Validation failed.');
                            if (label) label.textContent = messages;
                        } catch (e) {
                            if (label) label.textContent = 'Upload rejected. Please check the file and try again.';
                        }
                    } else {
                        if (label) label.textContent = 'Upload failed (HTTP ' + xhr.status + '). Please try again.';
                    }
                    resetSubmit();
                });

                xhr.addEventListener('error', function () {
                    if (label) label.textContent = 'Upload failed. Check your connection and try again.';
                    resetSubmit();
                });

                xhr.send(new FormData(form));
            });
        }

        document.querySelectorAll('form[data-doc-upload-progress]').forEach(bindUploadProgress);
    })();
    </script>
    @endpush
@endonce

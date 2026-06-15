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
        .doc-upload-progress__fill.is-done {
            background: linear-gradient(90deg, #16a34a, #22c55e);
        }
        .doc-upload-progress__label {
            margin: 0.35rem 0 0;
            font-size: 0.8rem;
            color: #475569;
            font-weight: 600;
        }
        .doc-upload-progress__label.is-error { color: #b91c1c; }
        .doc-upload-progress__label.is-success { color: #166534; }
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

        function setLabel(label, text, kind) {
            if (!label) return;
            label.textContent = text;
            label.classList.remove('is-error', 'is-success');
            if (kind) label.classList.add(kind);
        }

        function parseJsonResponse(xhr) {
            try {
                return JSON.parse(xhr.responseText || '{}');
            } catch (e) {
                return null;
            }
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
                if (fill) {
                    fill.style.width = '0%';
                    fill.classList.remove('is-done');
                }
                if (track) track.setAttribute('aria-valuenow', '0');
                setLabel(label, 'Uploading ' + (file.name || 'file') + ' (' + formatSize(file.size) + ')… 0%', null);
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.dataset.originalText = submitBtn.textContent;
                    submitBtn.textContent = 'Uploading…';
                }

                var xhr = new XMLHttpRequest();
                xhr.open((form.method || 'POST').toUpperCase(), form.action, true);
                xhr.withCredentials = true;
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('Accept', 'application/json');
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
                    setLabel(label, 'Uploading ' + (file.name || 'file') + '… ' + pct + '%', null);
                });

                xhr.upload.addEventListener('loadend', function () {
                    if (panel) panel.classList.remove('is-indeterminate');
                    if (fill) {
                        fill.style.width = '100%';
                        fill.classList.add('is-done');
                    }
                    if (track) track.setAttribute('aria-valuenow', '100');
                    setLabel(label, 'File sent. Saving on server…', null);
                });

                function resetSubmit() {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = submitBtn.dataset.originalText || submitBtn.textContent;
                    }
                }

                xhr.addEventListener('load', function () {
                    var payload = parseJsonResponse(xhr);

                    if (xhr.status >= 200 && xhr.status < 300 && payload && payload.ok && payload.redirect) {
                        setLabel(label, payload.message || 'Upload complete. Opening documents…', 'is-success');
                        window.setTimeout(function () {
                            window.location.assign(payload.redirect);
                        }, 350);
                        return;
                    }

                    if (xhr.status === 422 && payload) {
                        var messages = payload.errors
                            ? Object.values(payload.errors).flat().join(' ')
                            : (payload.message || 'Validation failed.');
                        setLabel(label, messages, 'is-error');
                        resetSubmit();
                        return;
                    }

                    if (xhr.status === 419) {
                        setLabel(label, 'Session expired. Refresh the page and try again.', 'is-error');
                        resetSubmit();
                        return;
                    }

                    if (xhr.status >= 200 && xhr.status < 400 && xhr.responseURL && xhr.responseURL !== form.action) {
                        setLabel(label, 'Upload complete. Redirecting…', 'is-success');
                        window.location.assign(xhr.responseURL);
                        return;
                    }

                    setLabel(label, 'Upload failed (HTTP ' + xhr.status + '). Please try again.', 'is-error');
                    resetSubmit();
                });

                xhr.addEventListener('error', function () {
                    setLabel(label, 'Upload failed. Check your connection and try again.', 'is-error');
                    resetSubmit();
                });

                xhr.addEventListener('timeout', function () {
                    setLabel(label, 'Upload timed out. Try a smaller file or check server limits.', 'is-error');
                    resetSubmit();
                });

                xhr.timeout = 600000;
                xhr.send(new FormData(form));
            });
        }

        document.querySelectorAll('form[data-doc-upload-progress]').forEach(bindUploadProgress);
    })();
    </script>
    @endpush
@endonce

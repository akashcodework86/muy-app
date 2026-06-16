<div class="doc-upload-progress" data-upload-progress-panel hidden>
    <div class="doc-upload-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <div class="doc-upload-progress__fill" data-upload-progress-fill style="width:0%;"></div>
    </div>
    <div class="doc-upload-progress__meta" data-upload-progress-meta></div>
    <p class="doc-upload-progress__label" data-upload-progress-label>Preparing upload…</p>
</div>

@once
    @push('styles')
    <style>
        .doc-upload-progress { margin-top: 0.65rem; }
        .doc-upload-progress__track {
            height: 0.6rem;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }
        .doc-upload-progress__fill {
            height: 100%;
            width: 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #4f46e5, #6366f1);
            transition: width 0.12s linear;
        }
        .doc-upload-progress__fill.is-done {
            background: linear-gradient(90deg, #16a34a, #22c55e);
            transition: none;
        }
        .doc-upload-progress__meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.74rem;
            color: #64748b;
            margin-top: 0.3rem;
            font-variant-numeric: tabular-nums;
            min-height: 1.1em;
        }
        .doc-upload-progress__label {
            margin: 0.2rem 0 0;
            font-size: 0.8rem;
            color: #475569;
            font-weight: 600;
        }
        .doc-upload-progress__label.is-error   { color: #b91c1c; }
        .doc-upload-progress__label.is-success  { color: #166534; }
        .doc-upload-progress.is-indeterminate .doc-upload-progress__fill {
            width: 35% !important;
            animation: doc-upload-indeterminate 1.1s ease-in-out infinite;
        }
        @keyframes doc-upload-indeterminate {
            0%   { transform: translateX(-120%); }
            100% { transform: translateX(320%); }
        }

        /* ── Drag-drop zone ── */
        .doc-drop-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 1.4rem 1rem;
            text-align: center;
            cursor: pointer;
            color: #64748b;
            font-size: 0.88rem;
            background: #f8fafc;
            transition: background 0.15s, border-color 0.15s;
            margin-bottom: 0.6rem;
        }
        .doc-drop-zone.is-drag-over { background: #eff6ff; border-color: #4f46e5; }
        .doc-drop-zone__icon { font-size: 2rem; display: block; margin-bottom: 0.4rem; }
        .doc-drop-zone__chip {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 0.45rem 0.65rem;
            font-size: 0.82rem;
            margin-top: 0.5rem;
        }
        .doc-drop-zone__chip-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }
        .doc-drop-zone__chip-size { color: #64748b; white-space: nowrap; }
        .doc-drop-zone__clear {
            background: none; border: none; cursor: pointer;
            color: #94a3b8; font-size: 1rem; padding: 0 2px; line-height: 1;
        }
        .doc-drop-zone__clear:hover { color: #b91c1c; }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        function csrfToken(form) {
            var input = form.querySelector('input[name="_token"]');
            return input ? input.value : '';
        }

        function fmtBytes(b) {
            if (!b || b <= 0) return '';
            if (b >= 1073741824) return (b / 1073741824).toFixed(2) + ' GB';
            if (b >= 1048576)   return (b / 1048576).toFixed(1) + ' MB';
            return Math.max(1, Math.round(b / 1024)) + ' KB';
        }

        function fmtSpeed(bps) {
            if (bps >= 1048576) return (bps / 1048576).toFixed(1) + ' MB/s';
            if (bps >= 1024)    return Math.round(bps / 1024) + ' KB/s';
            return Math.round(bps) + ' B/s';
        }

        function fmtEta(sec) {
            if (sec <= 0 || !isFinite(sec)) return '';
            if (sec >= 3600) return Math.ceil(sec / 3600) + 'h left';
            if (sec >= 60)   return Math.ceil(sec / 60) + 'm left';
            return Math.ceil(sec) + 's left';
        }

        function setLabel(label, text, kind) {
            if (!label) return;
            label.textContent = text;
            label.classList.remove('is-error', 'is-success');
            if (kind) label.classList.add(kind);
        }

        function parseJsonResponse(xhr) {
            try { return JSON.parse(xhr.responseText || '{}'); } catch (e) { return null; }
        }

        /* ── Drag-drop enhancement ── */
        function enhanceWithDragDrop(form) {
            var fileInput = form.querySelector('input[type="file"]');
            if (!fileInput || form.dataset.dropEnhanced === '1') return;
            form.dataset.dropEnhanced = '1';

            var container = fileInput.parentNode;
            var zone = document.createElement('div');
            zone.className = 'doc-drop-zone';
            zone.innerHTML =
                '<span class="doc-drop-zone__icon">☁️</span>' +
                'Drag &amp; drop any file here, or <label style="color:#4f46e5;font-weight:600;cursor:pointer;text-decoration:underline;" for="' + fileInput.id + '">browse</label>' +
                '<div class="doc-drop-zone__chip" style="display:none;" id="ddchip_' + fileInput.id + '">' +
                '  <span id="ddchip_icon_' + fileInput.id + '">📄</span>' +
                '  <span class="doc-drop-zone__chip-name" id="ddchip_name_' + fileInput.id + '"></span>' +
                '  <span class="doc-drop-zone__chip-size" id="ddchip_size_' + fileInput.id + '"></span>' +
                '  <button type="button" class="doc-drop-zone__clear" id="ddchip_clear_' + fileInput.id + '" title="Clear">✕</button>' +
                '</div>';

            container.insertBefore(zone, fileInput);
            fileInput.style.display = 'none';

            var chip      = document.getElementById('ddchip_' + fileInput.id);
            var chipName  = document.getElementById('ddchip_name_' + fileInput.id);
            var chipSize  = document.getElementById('ddchip_size_' + fileInput.id);
            var chipClear = document.getElementById('ddchip_clear_' + fileInput.id);

            var extIcons = {
                pdf:'📕',doc:'📘',docx:'📘',xls:'📗',xlsx:'📗',csv:'📊',
                ppt:'📙',pptx:'📙',txt:'📄',md:'📝',zip:'🗜',rar:'🗜',
                '7z':'🗜',jpg:'🖼',jpeg:'🖼',png:'🖼',gif:'🖼',webp:'🖼',
                mp4:'🎬',mov:'🎬',avi:'🎬',mkv:'🎬',mp3:'🎵',wav:'🎵',
                sql:'🗄',db:'🗄',json:'🔧',xml:'🔧',php:'🐘',py:'🐍',
            };
            function iconFor(name) {
                var ext = (name.split('.').pop() || '').toLowerCase();
                return extIcons[ext] || '📄';
            }

            function showChip(file) {
                if (!chip) return;
                chip.style.display = 'flex';
                if (chipName) chipName.textContent = file.name;
                if (chipSize) chipSize.textContent = fmtBytes(file.size);
                var iconEl = document.getElementById('ddchip_icon_' + fileInput.id);
                if (iconEl) iconEl.textContent = iconFor(file.name);
            }
            function clearChip() {
                if (chip) chip.style.display = 'none';
                fileInput.value = '';
            }

            zone.addEventListener('dragover',  function (e) { e.preventDefault(); zone.classList.add('is-drag-over'); });
            zone.addEventListener('dragleave', function ()  { zone.classList.remove('is-drag-over'); });
            zone.addEventListener('drop', function (e) {
                e.preventDefault();
                zone.classList.remove('is-drag-over');
                var f = e.dataTransfer && e.dataTransfer.files[0];
                if (!f) return;
                var dt = new DataTransfer();
                dt.items.add(f);
                fileInput.files = dt.files;
                showChip(f);
            });
            fileInput.addEventListener('change', function () {
                if (fileInput.files && fileInput.files[0]) showChip(fileInput.files[0]);
            });
            if (chipClear) chipClear.addEventListener('click', clearChip);
        }

        /* ── Upload progress ── */
        function bindUploadProgress(form) {
            if (!form || form.dataset.uploadProgressBound === '1') return;
            form.dataset.uploadProgressBound = '1';

            enhanceWithDragDrop(form);

            form.addEventListener('submit', function (event) {
                var fileInput = form.querySelector('input[type="file"]');
                if (!fileInput || !fileInput.files || !fileInput.files.length) return;

                event.preventDefault();

                var panel     = form.querySelector('[data-upload-progress-panel]');
                var fill      = form.querySelector('[data-upload-progress-fill]');
                var label     = form.querySelector('[data-upload-progress-label]');
                var meta      = form.querySelector('[data-upload-progress-meta]');
                var track     = form.querySelector('.doc-upload-progress__track');
                var submitBtn = form.querySelector('[type="submit"]');
                var file      = fileInput.files[0];
                var startTime = Date.now();

                if (panel) { panel.hidden = false; panel.classList.remove('is-indeterminate'); }
                if (fill)  { fill.style.width = '0%'; fill.classList.remove('is-done'); }
                if (track)  track.setAttribute('aria-valuenow', '0');
                if (meta)   meta.innerHTML = '';
                setLabel(label, 'Uploading ' + (file.name || 'file') + ' (' + fmtBytes(file.size) + ')…', null);
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

                    var pct     = Math.min(100, Math.round((ev.loaded / ev.total) * 100));
                    var elapsed = (Date.now() - startTime) / 1000;
                    var speed   = elapsed > 0.5 ? ev.loaded / elapsed : 0;
                    var eta     = speed > 0 ? (ev.total - ev.loaded) / speed : 0;

                    if (fill)  fill.style.width = pct + '%';
                    if (track) track.setAttribute('aria-valuenow', String(pct));
                    if (meta) {
                        meta.innerHTML =
                            '<span>' + fmtBytes(ev.loaded) + ' / ' + fmtBytes(ev.total) + ' &nbsp;(' + pct + '%)</span>' +
                            '<span>' + (speed > 0 ? fmtSpeed(speed) : '') + '</span>' +
                            '<span>' + (eta > 0 ? fmtEta(eta) : '') + '</span>';
                    }
                    setLabel(label, 'Uploading ' + (file.name || 'file') + '…', null);
                });

                xhr.upload.addEventListener('loadend', function () {
                    if (panel) panel.classList.remove('is-indeterminate');
                    if (fill)  { fill.style.width = '100%'; fill.classList.add('is-done'); }
                    if (track) track.setAttribute('aria-valuenow', '100');
                    if (meta)  meta.innerHTML = '<span>' + fmtBytes(file.size) + ' sent</span><span></span><span>Processing…</span>';
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
                        if (meta) meta.innerHTML = '';
                        setLabel(label, payload.message || 'Upload complete. Opening documents…', 'is-success');
                        window.setTimeout(function () { window.location.assign(payload.redirect); }, 350);
                        return;
                    }

                    if (meta) meta.innerHTML = '';

                    if (xhr.status === 422 && payload) {
                        var messages = payload.errors
                            ? Object.values(payload.errors).flat().join(' ')
                            : (payload.message || 'Validation failed.');
                        setLabel(label, messages, 'is-error');
                        resetSubmit();
                        return;
                    }

                    if (xhr.status === 413) {
                        setLabel(label,
                            (payload && payload.message)
                                ? payload.message
                                : 'Upload too large for this server. Restart with: composer serve (supports up to 50 MB).',
                            'is-error');
                        resetSubmit();
                        return;
                    }

                    if (xhr.status === 419) {
                        setLabel(label, 'Session expired. Refresh the page and try again.', 'is-error');
                        resetSubmit();
                        return;
                    }

                    var actionPath = '', responsePath = '';
                    try { actionPath   = new URL(form.action,                          window.location.href).pathname; } catch (e) {}
                    try { responsePath = new URL(xhr.responseURL || window.location.href, window.location.href).pathname; } catch (e) {}

                    if (xhr.status >= 200 && xhr.status < 400 && responsePath && actionPath
                        && responsePath === actionPath && actionPath.indexOf('/admin/documents') !== -1) {
                        setLabel(label, 'Upload complete. Redirecting…', 'is-success');
                        window.setTimeout(function () { window.location.assign(xhr.responseURL || form.action); }, 350);
                        return;
                    }

                    if (xhr.status >= 200 && xhr.status < 400 && xhr.responseURL && xhr.responseURL !== form.action) {
                        setLabel(label, 'Upload complete. Redirecting…', 'is-success');
                        window.location.assign(xhr.responseURL);
                        return;
                    }

                    if (payload && payload.message) {
                        setLabel(label, payload.message, 'is-error');
                        resetSubmit();
                        return;
                    }

                    setLabel(label, 'Upload failed (HTTP ' + xhr.status + '). Please try again.', 'is-error');
                    resetSubmit();
                });

                xhr.addEventListener('error', function () {
                    if (meta) meta.innerHTML = '';
                    setLabel(label, 'Upload failed. Check your connection and try again.', 'is-error');
                    resetSubmit();
                });

                xhr.addEventListener('timeout', function () {
                    if (meta) meta.innerHTML = '';
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

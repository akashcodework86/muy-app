<script>
    window.muySpocReview = (function () {
        var csrf = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrf ? csrf.getAttribute('content') : '';

        function storageKey(caseId) {
            return 'muy_spoc_review_' + caseId;
        }

        function read(caseId) {
            try {
                return JSON.parse(sessionStorage.getItem(storageKey(caseId)) || '{}') || {};
            } catch (e) {
                return {};
            }
        }

        function write(caseId, patch) {
            var next = Object.assign({
                started_at: null,
                document_viewed: false,
                accumulated_seconds: 0,
                last_tick_at: null,
            }, read(caseId), patch);
            sessionStorage.setItem(storageKey(caseId), JSON.stringify(next));
            return next;
        }

        function telemetryUrl(caseId) {
            if (typeof window.muySpocReviewTelemetryUrl === 'function') {
                return window.muySpocReviewTelemetryUrl(caseId);
            }
            return '/spoc/service-cases/' + caseId + '/review-telemetry';
        }

        function csrfValue() {
            if (csrfToken) return csrfToken;
            var input = document.querySelector('input[name="_token"]');
            return input ? input.value : '';
        }

        function post(caseId, payload) {
            if (!caseId) return;
            fetch(telemetryUrl(caseId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfValue(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            }).catch(function () {});
        }

        function ensureStarted(caseId) {
            var state = read(caseId);
            if (!state.started_at) {
                state = write(caseId, { started_at: Date.now(), last_tick_at: Date.now() });
            }
            return state;
        }

        function secondsSinceStart(caseId) {
            var state = ensureStarted(caseId);
            if (!state.started_at) return 0;
            return Math.max(0, Math.floor((Date.now() - state.started_at) / 1000));
        }

        function tick(caseId) {
            var state = ensureStarted(caseId);
            var now = Date.now();
            var last = state.last_tick_at || state.started_at || now;
            var delta = Math.max(0, Math.floor((now - last) / 1000));
            if (delta > 0) {
                write(caseId, {
                    accumulated_seconds: (state.accumulated_seconds || 0) + delta,
                    last_tick_at: now,
                });
                post(caseId, { event: 'heartbeat', seconds: delta });
            }
        }

        function markDocumentViewed(caseId, source) {
            if (!caseId) return;
            write(caseId, { document_viewed: true });
            post(caseId, { event: 'document_viewed', source: source || 'ui' });
        }

        function markQuickReviewOpened(caseId) {
            if (!caseId) return;
            ensureStarted(caseId);
            post(caseId, { event: 'quick_review_opened' });
        }

        function markFullPageVisited(caseId) {
            if (!caseId) return;
            ensureStarted(caseId);
            post(caseId, { event: 'full_page_visited' });
        }

        function attachApproveFields(form, caseId, channel) {
            if (!form || !caseId) return;
            form.addEventListener('submit', function () {
                tick(caseId);
                var seconds = secondsSinceStart(caseId);
                var state = read(caseId);
                var fields = [
                    ['client_review_seconds', String(seconds)],
                    ['client_document_viewed', state.document_viewed ? '1' : '0'],
                    ['approval_channel', channel || 'unknown'],
                ];
                fields.forEach(function (pair) {
                    var input = form.querySelector('input[name="' + pair[0] + '"]');
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = pair[0];
                        form.appendChild(input);
                    }
                    input.value = pair[1];
                });
            });
        }

        function bindDocButtons(selector, source) {
            Array.prototype.slice.call(document.querySelectorAll(selector)).forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var caseId = btn.getAttribute('data-case-id');
                    if (caseId) {
                        ensureStarted(caseId);
                        markDocumentViewed(caseId, source || 'queue_modal');
                    }
                });
            });
        }

        function startHeartbeat(caseId) {
            if (!caseId) return;
            ensureStarted(caseId);
            markFullPageVisited(caseId);
            tick(caseId);
            window.setInterval(function () {
                if (document.visibilityState === 'visible') {
                    tick(caseId);
                }
            }, 15000);
        }

        return {
            markDocumentViewed: markDocumentViewed,
            markQuickReviewOpened: markQuickReviewOpened,
            markFullPageVisited: markFullPageVisited,
            attachApproveFields: attachApproveFields,
            bindDocButtons: bindDocButtons,
            startHeartbeat: startHeartbeat,
            secondsSinceStart: secondsSinceStart,
            tick: tick,
        };
    })();
</script>

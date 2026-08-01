/**
 * Current-month-only activity dates with a custom calendar.
 * Month arrows show a reminder: backdate entry is not allowed.
 */
(function (window, document) {
    'use strict';

    if (window.MuyCurrentMonthDate) {
        return;
    }

    var STYLE_ID = 'muy-cmd-styles';
    var TOAST_ID = 'muy-cmd-toast';
    var POPOVER_ID = 'muy-cmd-popover';
    var REMINDER = 'Backdate entry is not allowed. Please select a date in the current month only.';

    function ensureStyles() {
        if (document.getElementById(STYLE_ID)) {
            return;
        }
        var style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = [
            '.muy-cmd-toast{position:fixed;z-index:10050;left:50%;bottom:1.5rem;transform:translateX(-50%) translateY(120%);',
            'background:#7f1d1d;color:#fff;padding:0.65rem 1rem;border-radius:10px;font-size:0.84rem;font-weight:600;',
            'box-shadow:0 10px 30px rgba(0,0,0,.22);max-width:min(92vw,26rem);text-align:center;opacity:0;',
            'transition:transform .22s ease,opacity .22s ease;pointer-events:none}',
            '.muy-cmd-toast.is-visible{transform:translateX(-50%) translateY(0);opacity:1}',
            '.muy-cmd-popover{position:absolute;z-index:10040;background:#fff;border:1px solid #e2e8f0;border-radius:12px;',
            'box-shadow:0 16px 40px rgba(15,23,42,.18);padding:0.75rem;width:17.5rem;display:none}',
            '.muy-cmd-popover.is-open{display:block}',
            '.muy-cmd-head{display:flex;align-items:center;justify-content:space-between;gap:0.35rem;margin-bottom:0.55rem}',
            '.muy-cmd-head strong{font-size:0.86rem;color:#0f172a}',
            '.muy-cmd-nav{position:relative;border:1px solid #fecaca;background:#fef2f2;border-radius:8px;width:2.15rem;height:2.15rem;',
            'cursor:not-allowed;font-size:1rem;color:#94a3b8;line-height:1;display:inline-flex;align-items:center;justify-content:center}',
            '.muy-cmd-nav:hover{background:#fee2e2;border-color:#f87171;color:#64748b}',
            '.muy-cmd-nav__arrow{opacity:.55}',
            '.muy-cmd-nav__cross{position:absolute;right:-0.15rem;top:-0.2rem;width:0.95rem;height:0.95rem;border-radius:999px;',
            'background:#dc2626;color:#fff;font-size:0.72rem;font-weight:800;line-height:0.95rem;text-align:center;',
            'box-shadow:0 1px 2px rgba(127,29,29,.35)}',
            '.muy-cmd-hint{font-size:0.72rem;color:#64748b;margin:0 0 0.5rem;line-height:1.35}',
            '.muy-cmd-dow,.muy-cmd-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:0.2rem}',
            '.muy-cmd-dow span{text-align:center;font-size:0.68rem;font-weight:700;color:#94a3b8;padding:0.2rem 0}',
            '.muy-cmd-day{border:none;background:transparent;border-radius:8px;height:2rem;font-size:0.8rem;',
            'cursor:pointer;color:#0f172a}',
            '.muy-cmd-day:hover{background:#eff6ff}',
            '.muy-cmd-day.is-selected{background:#7c3aed;color:#fff;font-weight:700}',
            '.muy-cmd-day.is-today:not(.is-selected){box-shadow:inset 0 0 0 1px #7c3aed}',
            '.muy-cmd-day.is-empty{visibility:hidden;pointer-events:none}',
            'input[data-muy-cmd="1"]{cursor:pointer}',
        ].join('');
        document.head.appendChild(style);
    }

    function toast(message) {
        ensureStyles();
        var el = document.getElementById(TOAST_ID);
        if (!el) {
            el = document.createElement('div');
            el.id = TOAST_ID;
            el.className = 'muy-cmd-toast';
            el.setAttribute('role', 'status');
            document.body.appendChild(el);
        }
        el.textContent = message || REMINDER;
        el.classList.add('is-visible');
        clearTimeout(toast._timer);
        toast._timer = setTimeout(function () {
            el.classList.remove('is-visible');
        }, 2800);
    }

    function parseYmd(value) {
        if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return null;
        }
        var parts = value.split('-').map(Number);
        return { y: parts[0], m: parts[1], d: parts[2] };
    }

    function ymd(y, m, d) {
        return y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    }

    function monthLabel(y, m) {
        return new Date(y, m - 1, 1).toLocaleString(undefined, { month: 'long', year: 'numeric' });
    }

    function getPopover() {
        var el = document.getElementById(POPOVER_ID);
        if (el) {
            return el;
        }
        el = document.createElement('div');
        el.id = POPOVER_ID;
        el.className = 'muy-cmd-popover';
        el.innerHTML = [
            '<div class="muy-cmd-head">',
            '  <button type="button" class="muy-cmd-nav" data-dir="-1" title="Backdate entry is not allowed" aria-label="Previous month not allowed">',
            '    <span class="muy-cmd-nav__arrow" aria-hidden="true">‹</span>',
            '    <span class="muy-cmd-nav__cross" aria-hidden="true">×</span>',
            '  </button>',
            '  <strong data-label></strong>',
            '  <button type="button" class="muy-cmd-nav" data-dir="1" title="Backdate entry is not allowed" aria-label="Next month not allowed">',
            '    <span class="muy-cmd-nav__arrow" aria-hidden="true">›</span>',
            '    <span class="muy-cmd-nav__cross" aria-hidden="true">×</span>',
            '  </button>',
            '</div>',
            '<p class="muy-cmd-hint">Current month only</p>',
            '<div class="muy-cmd-dow">',
            '  <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>',
            '</div>',
            '<div class="muy-cmd-grid" data-grid></div>',
        ].join('');
        document.body.appendChild(el);

        el.querySelectorAll('.muy-cmd-nav').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toast(REMINDER);
            });
        });

        return el;
    }

    var activeInput = null;

    function closePopover() {
        var el = document.getElementById(POPOVER_ID);
        if (el) {
            el.classList.remove('is-open');
        }
        activeInput = null;
    }

    function renderCalendar(input) {
        var start = parseYmd(input.getAttribute('data-month-start'));
        var end = parseYmd(input.getAttribute('data-month-end'));
        if (!start || !end) {
            return;
        }
        var selected = parseYmd(input.value) || start;
        var y = start.y;
        var m = start.m;
        var pop = getPopover();
        pop.querySelector('[data-label]').textContent = monthLabel(y, m);
        var grid = pop.querySelector('[data-grid]');
        grid.innerHTML = '';

        var firstDow = new Date(y, m - 1, 1).getDay();
        var daysInMonth = new Date(y, m, 0).getDate();
        var todayStr = input.getAttribute('data-today') || '';
        var i;
        for (i = 0; i < firstDow; i += 1) {
            var empty = document.createElement('button');
            empty.type = 'button';
            empty.className = 'muy-cmd-day is-empty';
            empty.tabIndex = -1;
            grid.appendChild(empty);
        }
        for (var day = 1; day <= daysInMonth; day += 1) {
            var value = ymd(y, m, day);
            if (value < ymd(start.y, start.m, start.d) || value > ymd(end.y, end.m, end.d)) {
                continue;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'muy-cmd-day';
            btn.textContent = String(day);
            btn.setAttribute('data-date', value);
            if (value === input.value) {
                btn.classList.add('is-selected');
            }
            if (value === todayStr) {
                btn.classList.add('is-today');
            }
            btn.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                var date = ev.currentTarget.getAttribute('data-date');
                if (!activeInput) {
                    return;
                }
                activeInput.value = date;
                activeInput.dispatchEvent(new Event('input', { bubbles: true }));
                activeInput.dispatchEvent(new Event('change', { bubbles: true }));
                closePopover();
            });
            grid.appendChild(btn);
        }
    }

    function positionPopover(input) {
        var pop = getPopover();
        var rect = input.getBoundingClientRect();
        var top = window.scrollY + rect.bottom + 6;
        var left = window.scrollX + rect.left;
        pop.style.top = top + 'px';
        pop.style.left = left + 'px';
        requestAnimationFrame(function () {
            var pr = pop.getBoundingClientRect();
            if (pr.right > window.innerWidth - 8) {
                pop.style.left = Math.max(8, window.scrollX + window.innerWidth - pr.width - 8) + 'px';
            }
            if (pr.bottom > window.innerHeight - 8) {
                pop.style.top = Math.max(8, window.scrollY + rect.top - pr.height - 6) + 'px';
            }
        });
    }

    function openPopover(input) {
        ensureStyles();
        activeInput = input;
        renderCalendar(input);
        positionPopover(input);
        getPopover().classList.add('is-open');
    }

    function enhance(input) {
        if (!input || input.dataset.muyCmdBound === '1') {
            return;
        }
        if (input.readOnly || input.disabled) {
            return;
        }
        var monthStart = input.getAttribute('data-month-start') || input.min;
        var monthEnd = input.getAttribute('data-month-end') || input.max;
        if (!monthStart || !monthEnd) {
            return;
        }
        input.dataset.muyCmdBound = '1';
        input.setAttribute('data-muy-cmd', '1');
        input.setAttribute('data-month-start', monthStart);
        input.setAttribute('data-month-end', monthEnd);
        if (!input.getAttribute('data-today')) {
            var now = new Date();
            input.setAttribute(
                'data-today',
                now.getFullYear()
                    + '-' + String(now.getMonth() + 1).padStart(2, '0')
                    + '-' + String(now.getDate()).padStart(2, '0')
            );
        }

        // Prefer custom calendar over native month navigation.
        if (input.type === 'date') {
            input.type = 'text';
            input.readOnly = true;
            input.autocomplete = 'off';
            input.inputMode = 'none';
        }

        input.addEventListener('click', function (e) {
            e.preventDefault();
            openPopover(input);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                e.preventDefault();
                openPopover(input);
            } else if (e.key === 'Escape') {
                closePopover();
            } else if (e.key === 'Tab') {
                closePopover();
            } else if (e.key.length === 1 || e.key === 'Backspace' || e.key === 'Delete') {
                e.preventDefault();
            }
        });
    }

    function enhanceAll(root) {
        ensureStyles();
        (root || document).querySelectorAll('input[data-month-start][data-month-end]').forEach(enhance);
    }

    document.addEventListener('click', function (e) {
        var pop = document.getElementById(POPOVER_ID);
        if (!pop || !pop.classList.contains('is-open')) {
            return;
        }
        if (pop.contains(e.target)) {
            return;
        }
        if (activeInput && (e.target === activeInput || activeInput.contains(e.target))) {
            return;
        }
        closePopover();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            enhanceAll(document);
        });
    } else {
        enhanceAll(document);
    }

    window.MuyCurrentMonthDate = {
        enhance: enhance,
        enhanceAll: enhanceAll,
        toast: toast,
        reminder: REMINDER,
    };
})(window, document);

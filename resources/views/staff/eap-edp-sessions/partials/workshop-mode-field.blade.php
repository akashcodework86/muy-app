@php
    $name = 'workshop_mode';
    $selected = old($name, $selected ?? null);
@endphp
@once
@push('styles')
<style>
    .ees-ws-field { margin-top: 0; }
    .ees-ws-control {
        position: relative;
        display: flex;
        align-items: stretch;
        gap: 0;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .ees-ws-control:focus-within {
        border-color: #a5b4fc;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.18);
    }
    .ees-ws-tick {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 2.35rem;
        padding: 0 0.35rem;
        font-size: 1rem;
        font-weight: 900;
        color: #4f46e5;
        background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 100%);
        border-right: 1px solid #e2e8f0;
        border-radius: 9px 0 0 9px;
        user-select: none;
    }
    .ees-ws-tick.is-empty { color: #cbd5e1; }
    .ees-ws-select {
        flex: 1 1 auto;
        min-width: 0;
        appearance: none;
        -webkit-appearance: none;
        border: none;
        border-radius: 0 9px 9px 0;
        padding: 0.62rem 2.35rem 0.62rem 0.75rem;
        font-size: 0.88rem;
        font-weight: 600;
        color: #0f172a;
        background-color: transparent;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.65rem center;
        background-size: 1rem;
        cursor: pointer;
    }
    .ees-ws-select:focus { outline: none; }
    .ees-ws-hint {
        margin: 0.35rem 0 0;
        color: #64748b;
        font-size: 0.78rem;
        line-height: 1.45;
    }
</style>
@endpush
@endonce

<div class="tp-field tp-field--full ees-ws-field">
    <label for="{{ $name }}">Virtual or physical workshop <span class="tp-req">*</span></label>
    <div class="ees-ws-control" data-ees-ws>
        <span class="ees-ws-tick {{ $selected === '' || $selected === null ? 'is-empty' : '' }}" data-ees-ws-tick aria-hidden="true">{{ $selected === 'virtual' || $selected === 'physical' ? '✓' : '' }}</span>
        <select
            id="{{ $name }}"
            name="{{ $name }}"
            class="ees-ws-select"
            required
            data-ees-ws-select
        >
            <option value="" disabled @selected($selected === '' || $selected === null)>Select format…</option>
            <option value="virtual" @selected($selected === 'virtual')>Virtual workshop (online)</option>
            <option value="physical" @selected($selected === 'physical')>Physical workshop (on-site)</option>
        </select>
    </div>
    <p class="ees-ws-hint">Mandatory: choose whether this session was held online or in person.</p>
</div>

@once
@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-ees-ws]').forEach(function (wrap) {
        var sel = wrap.querySelector('[data-ees-ws-select]');
        var tick = wrap.querySelector('[data-ees-ws-tick]');
        if (!sel || !tick) return;
        function sync() {
            var v = sel.value || '';
            tick.textContent = v ? '\u2713' : '';
            tick.classList.toggle('is-empty', !v);
        }
        sel.addEventListener('change', sync);
        sync();
    });
}());
</script>
@endpush
@endonce

@extends('layouts.admin')

@section('title', 'Add bill')
@section('heading', 'Add bill')

@push('styles')
<style>
    .imb-shell { display:flex; flex-direction:column; gap:1rem; max-width:56rem; }
    .imb-crumbs { font-size:0.82rem; color:#64748b; }
    .imb-crumbs a { color:#0d9488; text-decoration:none; font-weight:600; }
    .imb-crumbs a:hover { text-decoration:underline; }
    .imb-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.15rem 1.25rem; }
    .imb-card h3 { margin:0 0 0.85rem; font-size:0.95rem; font-weight:700; color:#0f172a; }
    .imb-meta { margin:0; font-size:0.88rem; color:#475569; line-height:1.5; }
    .imb-meta strong { color:#0f172a; }
    .imb-table { width:100%; border-collapse:collapse; font-size:0.86rem; }
    .imb-table th, .imb-table td { text-align:left; padding:0.55rem 0.45rem; border-bottom:1px solid #e2e8f0; vertical-align:top; }
    .imb-table th { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.04em; color:#64748b; }
    .imb-empty { margin:0; color:#64748b; font-size:0.86rem; }
    .imb-words { margin:0.2rem 0 0; font-size:0.78rem; font-weight:600; color:#0f766e; line-height:1.35; }
    .imb-words:empty, .imb-words[hidden] { display:none; }
    .imb-doc { color:#1d4ed8; font-weight:600; text-decoration:none; }
    .imb-doc:hover { text-decoration:underline; }
    .imb-rows { display:flex; flex-direction:column; gap:0.85rem; }
    .imb-row { border:1px solid #e2e8f0; border-radius:12px; padding:0.9rem 1rem; background:#f8fafc; }
    .imb-row__head { display:flex; align-items:center; justify-content:space-between; gap:0.5rem; margin-bottom:0.7rem; }
    .imb-row__title { font-size:0.82rem; font-weight:700; color:#334155; }
    .imb-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.75rem 1rem; }
    .imb-field { display:flex; flex-direction:column; gap:0.3rem; }
    .imb-field--full { grid-column:1 / -1; }
    .imb-field label { font-size:0.8rem; font-weight:700; color:#0f172a; }
    .imb-field input { width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:8px; padding:0.5rem 0.65rem; font-size:0.88rem; background:#fff; }
    .imb-req { color:#b91c1c; }
    .imb-hint { margin:0; font-size:0.74rem; color:#64748b; }
    .imb-remove { border:1px solid #fecaca; background:#fff; color:#b91c1c; border-radius:6px; padding:0.25rem 0.55rem; font-size:0.76rem; font-weight:600; cursor:pointer; }
    .imb-remove[hidden] { display:none; }
    .imb-actions { display:flex; flex-wrap:wrap; gap:0.65rem; align-items:center; margin-top:1rem; }
    .imb-add { border:1px dashed #94a3b8; background:#fff; color:#334155; border-radius:8px; padding:0.5rem 0.85rem; font-weight:700; cursor:pointer; }
    .imb-submit { border:none; border-radius:8px; background:#1d4ed8; color:#fff; padding:0.58rem 1rem; font-weight:700; cursor:pointer; }
    @media (max-width:720px) {
        .imb-grid { grid-template-columns:1fr; }
        .imb-table th { display:none; }
        .imb-table td { display:block; border-bottom:none; padding:0.2rem 0; }
        .imb-table tr { display:block; padding:0.7rem 0; border-bottom:1px solid #e2e8f0; }
        .imb-table td::before { content:attr(data-label); display:block; font-size:0.68rem; font-weight:700; text-transform:uppercase; color:#64748b; margin-bottom:0.1rem; }
    }
</style>
@endpush

@section('content')
@php
    $today = \App\Support\TodayOnlyDate::today();
    $monthStart = \App\Support\TodayOnlyDate::monthStart();
    $monthEnd = \App\Support\TodayOnlyDate::monthEnd();
    $oldBills = old('bills');
    if (! is_array($oldBills) || $oldBills === []) {
        $oldBills = [['bill_date' => $today, 'amount' => '', 'bill_number' => '']];
    }
@endphp
<div class="imb-shell">
    <p class="imb-crumbs">
        <a href="{{ route('staff.batches.index') }}">Batches</a>
        · <a href="{{ route('staff.batches.show', $batch) }}">{{ $batch->name }}</a>
        · Add bill
    </p>

    <div class="imb-card">
        <h3>Member</h3>
        <p class="imb-meta">
            <strong>{{ $cfa->applicant_name }}</strong>
            · Application no {{ $cfa->application_no ?: '—' }}
            · {{ $batch->name }}
            · {{ $batch->district?->name }}
        </p>
    </div>

    <div class="imb-card">
        <h3>Bills added</h3>
        @if ($bills->isEmpty())
            <p class="imb-empty">No bills added yet for this member.</p>
        @else
            <table class="imb-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Bill number</th>
                        <th>Amount</th>
                        <th>Document</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bills as $i => $bill)
                        <tr>
                            <td data-label="#">{{ $i + 1 }}</td>
                            <td data-label="Date">{{ $bill->bill_date?->format('d M Y') }}</td>
                            <td data-label="Bill number">{{ $bill->bill_number }}</td>
                            <td data-label="Amount">
                                {{ \App\Support\IndianRupees::format($bill->amount) }}
                                @php $words = \App\Support\IndianRupees::inWords($bill->amount); @endphp
                                @if ($words !== '')
                                    <p class="imb-words">{{ $words }}</p>
                                @endif
                            </td>
                            <td data-label="Document">
                                <a class="imb-doc" href="{{ route('staff.batches.members.bills.document', [$batch, $cfa, $bill]) }}">{{ $bill->document_original_name ?: 'Download' }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="imb-card">
        <h3>Add bills</h3>
        <p class="imb-hint" style="margin-bottom:0.85rem;">Date must be in the current month. Bill number must be unique. All fields are required.</p>

        <form method="post" action="{{ route('staff.batches.members.bills.store', [$batch, $cfa]) }}" enctype="multipart/form-data">
            @csrf
            <div class="imb-rows" id="imbRows"></div>
            <div class="imb-actions">
                <button type="button" class="imb-add" id="imbAdd">+ Add more bill</button>
                <button type="submit" class="imb-submit">Save bills</button>
            </div>
        </form>
    </div>
</div>

<template id="imbRowTemplate">
    <div class="imb-row" data-bill-row>
        <div class="imb-row__head">
            <span class="imb-row__title">Bill</span>
            <button type="button" class="imb-remove" data-remove hidden>Remove</button>
        </div>
        <div class="imb-grid">
            <div class="imb-field">
                <label>Date <span class="imb-req">*</span></label>
                <input type="date" data-field="bill_date" required
                    min="{{ $monthStart }}" max="{{ $monthEnd }}"
                    data-month-start="{{ $monthStart }}" data-month-end="{{ $monthEnd }}" data-today="{{ $today }}"
                    value="{{ $today }}">
            </div>
            <div class="imb-field">
                <label>Amount (INR) <span class="imb-req">*</span></label>
                <input type="number" data-field="amount" required min="0.01" step="0.01" placeholder="0.00" inputmode="decimal">
                <p class="imb-words" data-amount-words hidden></p>
            </div>
            <div class="imb-field">
                <label>Bill number <span class="imb-req">*</span></label>
                <input type="text" data-field="bill_number" required maxlength="100" autocomplete="off">
            </div>
            <div class="imb-field">
                <label>Document <span class="imb-req">*</span></label>
                <input type="file" data-field="document" required accept=".pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf">
                <p class="imb-hint">PDF or image, max 5 MB.</p>
            </div>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script src="{{ asset('js/muy-current-month-date.js') }}"></script>
<script>
(function () {
    const container = document.getElementById('imbRows');
    const template = document.getElementById('imbRowTemplate');
    const addBtn = document.getElementById('imbAdd');
    const oldBills = @json(array_values($oldBills));
    const TODAY = @json($today);
    let index = 0;

    function indianAmountInWords(raw) {
        const n = Math.floor(Math.abs(parseFloat(String(raw).replace(/,/g, '')) || 0));
        if (raw === '' || raw === null || raw === undefined) return '';
        if (!Number.isFinite(n) || parseFloat(String(raw).replace(/,/g, '')) <= 0) return '';
        const a = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
            'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        const b = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        function numToWords(x) {
            if (x < 20) return a[x];
            if (x < 100) return b[Math.floor(x / 10)] + (x % 10 ? '-' + a[x % 10] : '');
            if (x < 1000) return a[Math.floor(x / 100)] + ' hundred' + (x % 100 ? ' and ' + numToWords(x % 100) : '');
            if (x < 100000) return (numToWords(Math.floor(x / 1000)) + ' thousand ' + numToWords(x % 1000)).trim();
            if (x < 10000000) return (numToWords(Math.floor(x / 100000)) + ' lakh ' + numToWords(x % 100000)).trim();
            return (numToWords(Math.floor(x / 10000000)) + ' crore ' + numToWords(x % 10000000)).trim();
        }
        const paisePart = (() => {
            const m = String(raw).match(/\.(\d{1,2})/);
            if (!m) return '';
            const p = parseInt((m[1] + '0').slice(0, 2), 10);
            if (!p) return '';
            return ' and ' + numToWords(p) + ' paise';
        })();
        const words = (numToWords(n) + paisePart).replace(/\s+/g, ' ').trim();
        return words ? ('₹ ' + words.charAt(0).toUpperCase() + words.slice(1) + ' only') : '';
    }

    function syncAmountWords(row) {
        const input = row.querySelector('[data-field="amount"]');
        const out = row.querySelector('[data-amount-words]');
        if (!input || !out) return;
        const text = indianAmountInWords(input.value);
        out.textContent = text;
        out.hidden = !text;
    }

    function reindex() {
        const rows = container.querySelectorAll('[data-bill-row]');
        rows.forEach((row, i) => {
            row.querySelector('.imb-row__title').textContent = 'Bill ' + (i + 1);
            const remove = row.querySelector('[data-remove]');
            if (remove) remove.hidden = rows.length < 2;
        });
    }

    function addRow(preset) {
        const node = template.content.firstElementChild.cloneNode(true);
        const i = index++;
        node.querySelectorAll('[data-field]').forEach((el) => {
            const field = el.getAttribute('data-field');
            el.name = 'bills[' + i + '][' + field + ']';
            if (preset && Object.prototype.hasOwnProperty.call(preset, field) && field !== 'document') {
                el.value = preset[field] == null ? '' : String(preset[field]);
            }
        });
        const dateInput = node.querySelector('[data-field="bill_date"]');
        if (dateInput && !dateInput.value) dateInput.value = TODAY;
        node.querySelector('[data-field="amount"]').addEventListener('input', () => syncAmountWords(node));
        node.querySelector('[data-remove]').addEventListener('click', () => {
            if (container.querySelectorAll('[data-bill-row]').length < 2) return;
            node.remove();
            reindex();
        });
        container.appendChild(node);
        if (window.MuyCurrentMonthDate) {
            window.MuyCurrentMonthDate.enhanceAll(node);
        }
        syncAmountWords(node);
        reindex();
    }

    addBtn.addEventListener('click', () => addRow(null));
    (oldBills.length ? oldBills : [{}]).forEach((row) => addRow(row));
})();
</script>
@endpush

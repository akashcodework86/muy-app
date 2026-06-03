@php
    $districtLabel = (string) ($districtLabel ?? ($user->district?->name ?? '—'));
    $defaultBlockId = (int) old('district_block_id', $defaultBlockId ?? 0);
    $defaultGpId = (int) old('gram_panchayat_id', $defaultGpId ?? 0);
@endphp

@if (empty($gramPanchayatsEnabled))
    <div class="ws-part-alert" style="grid-column:1 / -1;background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:10px;padding:0.75rem 1rem;font-size:0.85rem;">
        Import gram panchayats: <code>php artisan gram-panchayats:import path/to/your.csv</code>
    </div>
@endif

<div class="tp-field">
    <label for="wsBlockSelect">Block <span class="tp-req">*</span></label>
    <select name="district_block_id" id="wsBlockSelect" class="ws-part-input" @if(($blockRows ?? collect())->isEmpty()) disabled @endif>
        <option value="">— Select block —</option>
        @foreach ($blockRows ?? [] as $block)
            <option value="{{ $block->id }}" @selected($defaultBlockId === (int) $block->id)>{{ $block->name }}</option>
        @endforeach
    </select>
</div>
<div class="tp-field">
    <label for="wsGpSelect">Default gram panchayat</label>
    <input type="search" id="wsGpSearch" class="ws-part-input" placeholder="Filter list…" disabled style="margin-bottom:0.35rem;">
    <select name="gram_panchayat_id" id="wsGpSelect" class="ws-part-input" disabled>
        <option value="">— Select block first —</option>
    </select>
    <span id="wsGpHint" style="font-size:0.72rem;color:#64748b;"></span>
</div>

@once
    @push('styles')
    <style>
        .ws-part-input { width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:0.58rem 0.7rem;font-size:0.88rem;background:#fff; }
        .tp-req { color:#e11d48; margin-left:1px; }
    </style>
    @endpush
@endonce

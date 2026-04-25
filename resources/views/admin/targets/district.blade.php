@extends('layouts.admin')

@section('title', 'District targets')
@section('heading', 'District targets (save partial progress; match state total when final)')

@section('content')
    @if ($fiscalYears->isEmpty() || $deliverables->isEmpty())
        <p>Missing fiscal years or deliverables.</p>
    @else
        <div style="margin-bottom:1rem;background:#f0fdf4;border:1px solid #bbf7d0;color:#14532d;padding:0.75rem 0.9rem;border-radius:8px;font-size:0.88rem;line-height:1.5;">
            <strong>Reminder:</strong> When <strong>Onboarding</strong> is selected, this allocation is for the hub batch onboarding target.
            Best practice is to keep the total district allocation equal to the state onboarding target.
        </div>
        <form method="get" action="{{ route('admin.targets.district') }}" style="margin-bottom:1rem; display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
            <div>
                <label for="fy" style="display:block; font-size:0.8rem; font-weight:500; margin-bottom:0.25rem;">Fiscal year</label>
                <select id="fy" name="fiscal_year_id" style="padding:0.4rem 0.5rem; border-radius:6px; border:1px solid #d4d4d8; min-width:12rem;">
                    @foreach ($fiscalYears as $fy)
                        <option value="{{ $fy->id }}" @selected((int) $fiscalYearId === (int) $fy->id)>{{ $fy->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="del" style="display:block; font-size:0.8rem; font-weight:500; margin-bottom:0.25rem;">Deliverable</label>
                <select id="del" name="deliverable_id" style="padding:0.4rem 0.5rem; border-radius:6px; border:1px solid #d4d4d8; min-width:16rem;">
                    @foreach ($deliverables as $d)
                        <option value="{{ $d->id }}" @selected((int) $deliverableId === (int) $d->id)>{{ $d->sort_order }}. {{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" style="background:#fff; border:1px solid #d4d4d8; padding:0.45rem 0.75rem; border-radius:6px;">Load</button>
        </form>

        @if ($stateTarget === null)
            <p style="background:#fffbeb; border:1px solid #fcd34d; color:#92400e; padding:0.75rem; border-radius:6px; font-size:0.9rem;">
                State target for this deliverable is not set. Set it on <a href="{{ route('admin.targets.state', ['fiscal_year_id' => $fiscalYearId]) }}">State targets</a> first.
            </p>
        @else
            <p style="font-size:0.9rem; margin-bottom:0.75rem;">
                <strong>State target for this deliverable:</strong> {{ number_format($stateTarget) }}
                <span style="color:#71717a;">— aim for district totals to add up to this number when allocation is final.</span>
            </p>

            <form id="district-target-form" method="post" action="{{ route('admin.targets.district.update') }}" data-state-target="{{ (int) $stateTarget }}">
                @csrf
                <input type="hidden" name="fiscal_year_id" value="{{ $fiscalYearId }}">
                <input type="hidden" name="deliverable_id" value="{{ $deliverableId }}">

                <div id="district-allocation-summary" class="target-allocation-summary" style="background:#fff; border:1px solid #e4e4e7; border-radius:8px; padding:0.85rem 1rem; margin-bottom:1rem; font-size:0.9rem; line-height:1.6;">
                    <div><strong>State target:</strong> <span id="summary-state">{{ number_format($stateTarget) }}</span></div>
                    <div><strong>District total (allocated):</strong> <span id="summary-allocated">0</span></div>
                    <div id="summary-remaining-wrap"><strong id="summary-remaining-label">Remaining to allocate:</strong> <span id="summary-remaining">—</span></div>
                </div>

                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #e4e4e7; border-radius:8px; font-size:0.875rem;">
                        <thead>
                            <tr style="background:#fafafa; text-align:left;">
                                <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">Hub</th>
                                <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7;">District</th>
                                <th style="padding:0.5rem 0.65rem; border-bottom:1px solid #e4e4e7; width:9rem;">Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($districts as $dist)
                                <tr>
                                    <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5; color:#52525b;">{{ $dist->hub?->name ?? '—' }}</td>
                                    <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">{{ $dist->name }}</td>
                                    <td style="padding:0.45rem 0.65rem; border-bottom:1px solid #f4f4f5;">
                                        <input type="number" class="js-district-target" name="districts[{{ $dist->id }}]" min="0" step="1"
                                            value="{{ old('districts.'.$dist->id, $existing[$dist->id] ?? 0) }}"
                                            style="width:100%; max-width:8rem; padding:0.35rem 0.45rem; border:1px solid #d4d4d8; border-radius:6px;">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p style="margin-top:1rem;">
                    <button type="submit" style="background:#18181b; color:#fff; border:none; padding:0.55rem 1rem; border-radius:6px; font-weight:500;">Save district targets</button>
                </p>
            </form>
        @endif
    @endif
@endsection

@if ($stateTarget !== null && $fiscalYears->isNotEmpty() && $deliverables->isNotEmpty())
    @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('district-target-form');
                if (!form) return;
                const state = parseInt(form.getAttribute('data-state-target'), 10) || 0;
                const allocatedEl = document.getElementById('summary-allocated');
                const remainingEl = document.getElementById('summary-remaining');
                const labelEl = document.getElementById('summary-remaining-label');
                const box = document.getElementById('district-allocation-summary');
                if (!allocatedEl || !remainingEl || !labelEl || !box) return;

                function fmt(n) {
                    return Number(n).toLocaleString('en-IN');
                }

                function update() {
                    const inputs = form.querySelectorAll('.js-district-target');
                    let sum = 0;
                    inputs.forEach(function (el) {
                        const raw = String(el.value).trim();
                        const v = raw === '' ? 0 : parseInt(raw, 10);
                        sum += Number.isFinite(v) && v >= 0 ? v : 0;
                    });
                    allocatedEl.textContent = fmt(sum);

                    const diff = state - sum;
                    box.style.borderColor = '#e4e4e7';
                    remainingEl.style.fontWeight = '600';

                    if (diff > 0) {
                        labelEl.textContent = 'Remaining to allocate:';
                        remainingEl.textContent = fmt(diff);
                        remainingEl.style.color = '#b45309';
                    } else if (diff < 0) {
                        labelEl.textContent = 'Over state target by:';
                        remainingEl.textContent = fmt(Math.abs(diff));
                        remainingEl.style.color = '#b91c1c';
                    } else {
                        labelEl.textContent = 'Remaining:';
                        remainingEl.textContent = '0 — matches state target';
                        remainingEl.style.color = '#047857';
                        box.style.borderColor = '#6ee7b7';
                    }
                }

                form.querySelectorAll('.js-district-target').forEach(function (el) {
                    el.addEventListener('input', update);
                    el.addEventListener('change', update);
                });
                update();
            })();
        </script>
    @endpush
@endif

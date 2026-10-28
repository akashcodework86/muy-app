@extends('layouts.admin')

@section('title', \App\Models\BuyerSellerMeetEntry::MODULE_LABEL)
@section('heading', \App\Models\BuyerSellerMeetEntry::MODULE_LABEL)

@push('styles')
@include('branding-communication.partials.form-styles')
<style>
.bsm-participants { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; }
.bsm-participants table { width:100%; border-collapse:collapse; font-size:0.84rem; }
.bsm-participants th, .bsm-participants td { padding:0.45rem 0.5rem; border-bottom:1px solid #f1f5f9; vertical-align:top; }
.bsm-participants th { background:#f8fafc; text-align:left; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.03em; color:#64748b; }
.bsm-search-results { max-height:140px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; background:#fff; }
.bsm-search-results button { display:block; width:100%; text-align:left; border:0; background:#fff; padding:0.4rem 0.55rem; cursor:pointer; font-size:0.8rem; }
.bsm-search-results button:hover { background:#f1f5f9; }
</style>
@endpush

@section('content')
<div class="bc-shell">
    @if (!empty($migrationMissing))<div class="bc-alert bc-alert--warning">Run <code>php artisan migrate</code>.</div>@endif
    @if (session('status'))<div class="bc-alert bc-alert--success">{{ session('status') }}</div>@endif
    @if (session('warning'))<div class="bc-alert bc-alert--warning">{{ session('warning') }}</div>@endif
    @if ($errors->any())<div class="bc-alert bc-alert--error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="bc-card">
        <h3 class="bc-card__title">{{ !empty($isAdminEdit) ? 'Edit Buyer-Seller Meet' : 'Log Buyer-Seller Meet (10.5)' }}</h3>
        <p class="bc-hint">Meet date must fall within <strong>{{ $activeFyLabel }}</strong>.</p>
        <form method="post" action="{{ $formAction }}" enctype="multipart/form-data" id="bsmForm">
            @csrf
            @if (!empty($isAdminEdit)) @method('PUT') @endif
            <div class="bc-grid">
                <div class="bc-field">
                    <label>Submitted by</label>
                    <input type="text" class="bc-readonly" value="{{ $entry->submitted_by_name ?? $user->name }}" readonly>
                </div>
                <div class="bc-field">
                    <label for="meet_date">Meet date <span class="bc-req">*</span></label>
                    <input type="date" id="meet_date" name="meet_date" required
                        min="{{ $fyMinDate }}" max="{{ $fyMaxDate }}"
                        value="{{ old('meet_date', $entry?->meet_date?->format('Y-m-d')) }}">
                </div>
                <div class="bc-field">
                    <label for="district_id">District <span class="bc-req">*</span></label>
                    <select id="district_id" name="district_id" required>
                        <option value="">— Select district —</option>
                        @foreach ($districts as $d)
                            <option value="{{ $d->id }}" @selected((string) old('district_id', $entry?->district_id) === (string) $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="bc-field bc-field--full">
                    <label for="title">Meet title / theme <span class="bc-req">*</span></label>
                    <input type="text" id="title" name="title" maxlength="255" required value="{{ old('title', $entry?->title) }}">
                </div>
                <div class="bc-field bc-field--full">
                    <label for="venue">Venue <span class="bc-req">*</span></label>
                    <input type="text" id="venue" name="venue" maxlength="255" required value="{{ old('venue', $entry?->venue) }}">
                </div>
                <div class="bc-field">
                    <label for="total_participants">Total participants <span class="bc-req">*</span></label>
                    <input type="number" id="total_participants" name="total_participants" min="1" max="100000" required value="{{ old('total_participants', $entry?->total_participants) }}">
                    <p class="bc-hint">Include guests not listed below.</p>
                </div>
                <div class="bc-field bc-field--full">
                    <label for="bsmPhotos">Photos <span class="bc-req">*</span></label>
                    <input id="bsmPhotos" type="file" name="photos[]" accept=".jpg,.jpeg,.png,.webp,image/*" multiple @if(empty($isAdminEdit)) required @endif>
                    <p class="bc-hint">Min 1, max {{ $maxPhotos }} images.@if(!empty($isAdminEdit)) Leave empty to keep existing photos.@endif</p>
                </div>
                <div class="bc-field bc-field--full">
                    <label for="bsmAttendance">Attendance sheets <span class="bc-req">*</span></label>
                    <input id="bsmAttendance" type="file" name="attendance_files[]" accept=".pdf,.xls,.xlsx,.jpg,.jpeg,.png" multiple @if(empty($isAdminEdit)) required @endif>
                    <p class="bc-hint">PDF/Excel/images. Min 1, max {{ $maxAttendanceFiles }}.@if(!empty($isAdminEdit)) Leave empty to keep existing files.@endif</p>
                </div>
                <div class="bc-field bc-field--full">
                    <label>Participants (buyer / seller) <span class="bc-req">*</span></label>
                    <p class="bc-hint">At least one <strong>Buyer</strong> and one <strong>Seller</strong>. Search onboarded incubatees or add manual names.</p>
                    <div id="bsmParticipantsRoot" class="bsm-participants" data-search-url="{{ route($searchRoute) }}"></div>
                    <button type="button" class="bc-btn bc-btn--secondary" id="bsmAddParticipant" style="margin-top:0.5rem;">+ Add participant</button>
                    @error('participants')<p class="bc-hint" style="color:#b91c1c;">{{ $message }}</p>@enderror
                </div>
                <div class="bc-field bc-field--full">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" maxlength="5000">{{ old('remarks', $entry?->remarks) }}</textarea>
                </div>
            </div>
            <div class="bc-actions">
                <button type="submit" class="bc-submit">{{ !empty($isAdminEdit) ? 'Update entry' : 'Save meet' }}</button>
                <a href="{{ route($dashboardRoute) }}" class="bc-link">View dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@include('buyer-seller-meets.partials.participants-script', [
    'initialParticipants' => old('participants', $entry ? $entry->participants->map(fn ($p) => [
        'kind' => $p->participant_kind,
        'role' => $p->role,
        'participant_name' => $p->participant_name,
        'phone' => $p->phone,
        'application_no' => $p->application_no,
        'incubatee_key' => $p->incubatee_key,
        'cfa_submission_id' => $p->cfa_submission_id,
        'legacy_application_id' => $p->legacy_application_id,
        'legacy_phase1_application_id' => $p->legacy_phase1_application_id,
    ])->values()->all() : []),
])
@endpush

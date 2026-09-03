@extends('staff.training-packages.form')

@section('title', 'Edit Training Package Attendance')
@section('heading', 'Edit Training Package Attendance')

@section('content')
<div class="tp-shell">
    @php
        $oldSelectedIds = collect((array) old('selected_incubatees', $selectedIds))
            ->map(fn ($id) => (int) $id)
            ->all();
        $oldModules = (array) old('training_packages', (array) ($row->training_packages ?? [$row->training_package]));
    @endphp

    @if (session('status'))
        <div class="tp-alert tp-alert--success">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="tp-alert tp-alert--error">
            <strong>Please fix:</strong>
            <ul>
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="tp-card">
        <h3 class="tp-card__title">Update Submission</h3>
        <form method="post" action="{{ route('staff.training-packages.update', $row) }}" enctype="multipart/form-data">
            @csrf
            @method('put')
            <div class="tp-grid">
                <div class="tp-field">
                    <label>Session Taken By</label>
                    <input type="text" class="tp-readonly" value="{{ $row->submitted_by_name }}" readonly>
                </div>
                <div class="tp-field">
                    <label>Date of Session *</label>
                    <x-activity-date-input name="session_date" :value="$row->event_date?->format('Y-m-d')" />
                </div>
                <div class="tp-field">
                    <label>District</label>
                    <input type="text" class="tp-readonly" value="{{ $row->district_name ?: ($row->district?->name ?? 'NA') }}" readonly>
                </div>
                @if ($row->monthSession)
                    <div class="tp-field tp-field--full">
                        <label>{{ $row->monthSession->is_extra ? 'Extra session' : 'Planned session' }}</label>
                        <input type="text" class="tp-readonly" value="{{ $row->monthSession->session_name }}{{ $row->monthSession->is_extra ? ' (Extra)' : '' }}" readonly>
                    </div>
                @endif
                <div class="tp-field">
                    <label>Training Batch (optional custom)</label>
                    <input type="text" name="training_batch_name" value="{{ old('training_batch_name', $row->training_batch_name) }}">
                </div>
                @if (\Illuminate\Support\Facades\Schema::hasColumn('training_packages', 'workshop_delivery'))
                    @php $oldWorkshop = old('workshop_delivery', $row->workshop_delivery ?: 'physical'); @endphp
                    <div class="tp-field tp-workshop-field">
                        <label for="workshop_delivery">Virtual or physical workshop *</label>
                        <select id="workshop_delivery" name="workshop_delivery" class="tp-workshop-select" required>
                            <option value="virtual" @selected($oldWorkshop === 'virtual')>Virtual workshop</option>
                            <option value="physical" @selected($oldWorkshop === 'physical')>Physical workshop</option>
                        </select>
                        <p class="tp-workshop-hint">Choose whether this session was held online or in person.</p>
                    </div>
                @endif
                <div class="tp-field tp-field--full">
                    <label>Training Packages (multi-select) *</label>
                    <div class="tp-checkgrid">
                        @foreach (['t1' => 'T1', 't2' => 'T2', 't3' => 'T3', 't4' => 'T4'] as $moduleValue => $moduleLabel)
                            <label>
                                <input type="checkbox" name="training_packages[]" value="{{ $moduleValue }}" @checked(in_array($moduleValue, $oldModules, true))>
                                <span>{{ $moduleLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="tp-section">
                <div class="tp-field">
                    <label>Uploaded attendance sheet (optional)</label>
                    <input id="tpMediaInput" type="file" name="attendance_media[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,.mov,.avi,.mkv,.doc,.docx,.xls,.xlsx" multiple>
                    @if (is_array($row->attendance_media_json) && count($row->attendance_media_json))
                        <p class="tp-field-hint">Current uploads:</p>
                        <div class="tp-media-preview">
                            @foreach ($row->attendance_media_json as $media)
                                @if (is_array($media))
                                    @if (str_starts_with((string) ($media['mime'] ?? ''), 'image/'))
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url((string) ($media['path'] ?? '')) }}" alt="{{ $media['original_name'] ?? 'Media' }}">
                                    @else
                                        <span class="tp-media-chip">{{ $media['original_name'] ?? 'File' }}</span>
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    @endif
                    <div id="tpMediaPreview" class="tp-media-preview"></div>
                </div>
            </div>

            <div class="tp-section" id="tpAttendanceSection" data-min-attendees="{{ \App\Models\TrainingPackage::MIN_ATTENDEES }}" data-enforce-min="{{ $row->isDraft() ? '1' : '0' }}">
                <h4 class="tp-section__title">Manual Attendance Selection *</h4>
                <p class="tp-min-banner">
                    Minimum <strong>{{ \App\Models\TrainingPackage::MIN_ATTENDEES }} incubatees</strong> must be selected to submit this session.
                    @if ($row->isDraft())
                        Use <strong>Save draft</strong> if you have fewer for now — you can add more later.
                    @endif
                </p>
                <div class="tp-two-col">
                    <div class="tp-col">
                        <p class="tp-note">
                            rbiphase3 onboarded applicants in district: <strong>{{ (int) ($totalOnboardedCount ?? $incubatees->count()) }}</strong>
                        </p>
                        <input id="tpSearch" class="tp-search" type="text" placeholder="Search rbiphase3 onboarded applicants by name/application/phone">
                        <div class="tp-list" id="tpSourceList">
                            @foreach ($incubatees as $item)
                                @php $checked = in_array((int) $item['incubatee_id'], $oldSelectedIds, true); @endphp
                                <label class="tp-item" data-search="{{ strtolower($item['name'].' '.$item['application_no'].' '.$item['phone']) }}">
                                    <input type="checkbox" class="tp-check" value="{{ $item['incubatee_id'] }}" @checked($checked)>
                                    <div>
                                        <h4>{{ $item['name'] ?: 'Unnamed' }}</h4>
                                        <div class="tp-meta">
                                            <span class="tp-pill">App: {{ $item['application_no'] ?: 'NA' }}</span>
                                            <span class="tp-pill">Batch: {{ $item['onboarding_batch_name'] ?: 'NA' }}</span>
                                        </div>
                                        <div class="tp-meta">Phone: {{ $item['phone'] ?: 'NA' }} | Block: {{ $item['block_name'] ?: 'NA' }} | Village: {{ $item['village'] ?: 'NA' }}</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="tp-col">
                        <p class="tp-right-title">Selected Incubatees <span id="tpSelectedCount" class="tp-selected-count is-short">0</span> / {{ \App\Models\TrainingPackage::MIN_ATTENDEES }}</p>
                        <p class="tp-note" id="tpMinHint">Minimum {{ \App\Models\TrainingPackage::MIN_ATTENDEES }} incubatees required to submit. Save draft to continue later.</p>
                        <div class="tp-list" id="tpSelectedPanel"></div>
                    </div>
                </div>
            </div>

            <div id="tpHiddenInputs"></div>
            <div class="tp-actions">
                @if ($row->isDraft())
                    <button class="tp-submit tp-submit--draft" type="submit" name="save_as_draft" value="1">Save draft</button>
                @endif
                <button class="tp-submit" id="tpSubmitBtn" type="submit">{{ $row->isDraft() ? 'Submit attendance' : 'Update attendance' }}</button>
                <a class="tp-link" href="{{ route('staff.training-packages.export-single', $row) }}">Excel Export</a>
                <a class="tp-link" href="{{ route('staff.training-packages.dashboard') }}">Back to dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection

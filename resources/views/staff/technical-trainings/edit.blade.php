@extends('staff.technical-trainings.form')

@section('title', 'Edit technical training to incubatees')
@section('heading', 'Edit technical training to incubatees')

@section('content')
<div class="tp-shell">
    @php
        $oldSelectedIds = collect((array) old('selected_incubatees', $selectedIds))
            ->map(fn ($id) => (int) $id)
            ->all();
    @endphp

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
        <form method="post" action="{{ $formAction ?? route('staff.technical-trainings.update', $row) }}" enctype="multipart/form-data">
            @csrf
            @method('put')
            <div class="tp-grid">
                <div class="tp-field">
                    <label>Session Taken By</label>
                    <input type="text" class="tp-readonly" value="{{ $submitterLabel ?? $row->submitted_by_name }}" readonly>
                </div>
                <div class="tp-field">
                    <label>Date of Session *</label>
                    <x-activity-date-input name="session_date" :value="$row->event_date?->format('Y-m-d')" />
                </div>
                <div class="tp-field">
                    <label>District{{ !empty($statewideEntry) ? ' *' : '' }}</label>
                    @if (!empty($statewideEntry))
                        <select name="district_id" id="tpSessionDistrict" required>
                            <option value="">Select district</option>
                            @foreach (($districts ?? []) as $district)
                                <option value="{{ $district->id }}" @selected((int) old('district_id', $row->district_id) === (int) $district->id)>{{ $district->name }}</option>
                            @endforeach
                        </select>
                        <p class="tp-field-hint">This is the district the session is counted under. You can still add incubatees from any district.</p>
                    @else
                        <input type="text" class="tp-readonly" value="{{ $row->district_name ?: ($row->district?->name ?? 'NA') }}" readonly>
                    @endif
                </div>
                <div class="tp-field">
                    <label>Training Batch (optional custom)</label>
                    <input type="text" name="training_batch_name" value="{{ old('training_batch_name', $row->training_batch_name) }}">
                </div>
                <div class="tp-field tp-field--full">
                    <label>Technical training session name *</label>
                    <input type="text" name="session_name" value="{{ old('session_name', $row->session_name) }}" maxlength="191" required>
                </div>
                <div class="tp-field tp-field--full">
                    <label>Session brief (optional)</label>
                    <textarea name="session_brief" maxlength="5000">{{ old('session_brief', $row->session_brief) }}</textarea>
                </div>
            </div>

            <div class="tp-section">
                <div class="tp-field">
                    <label>Upload photos, videos, or documents (optional)</label>
                    <input id="tpMediaInput" type="file" name="attendance_media[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,.mov,.avi,.mkv,.doc,.docx,.xls,.xlsx" multiple>
                    <p class="tp-field-hint">Choose files again to add more uploads. New files are saved with the current uploads, up to 25 files total.</p>
                    @if (is_array($row->attendance_media_json) && count($row->attendance_media_json))
                        <p class="tp-field-hint">Current uploads:</p>
                        @include('staff.technical-trainings.partials.attendance-media-preview', [
                            'mediaItems' => (array) $row->attendance_media_json,
                            'attachmentRoute' => $attachmentRoute ?? 'staff.technical-trainings.attachment',
                            'record' => $row,
                        ])
                    @endif
                    <div id="tpMediaPreview" class="tp-media-preview"></div>
                </div>
            </div>

            <div class="tp-section">
                <h4 class="tp-section__title">Manual Attendance Selection *</h4>
                <div class="tp-two-col">
                <div class="tp-col">
                    @if (!empty($statewideEntry))
                        <label for="tpBrowseDistrict" style="font-size:0.82rem;font-weight:700;color:#0f172a;">Show applicants from</label>
                        <select id="tpBrowseDistrict">
                            <option value="">Select a district</option>
                            @foreach (($districts ?? []) as $district)
                                <option value="{{ $district->id }}" @selected((int) old('district_id', $row->district_id) === (int) $district->id)>{{ $district->name }}</option>
                            @endforeach
                        </select>
                        <p class="tp-note">Switch districts to add people from anywhere. People you already selected stay selected.</p>
                    @endif
                    <p class="tp-note">
                        rbiphase3 applicants in district: <strong id="tpApplicantCount">{{ (int) ($totalApplicantCount ?? $incubatees->count()) }}</strong>
                    </p>
                        <input id="tpSearch" class="tp-search" type="text" placeholder="Search applicants by name, application no., or phone">
                        <div class="tp-list" id="tpSourceList">
                            @foreach ($incubatees as $item)
                                @php $checked = in_array((int) $item['incubatee_id'], $oldSelectedIds, true); @endphp
                                <label class="tp-item" data-search="{{ strtolower($item['name'].' '.$item['application_no'].' '.$item['phone'].' '.($item['onboard_label'] ?? '').' '.($item['district_name'] ?? '')) }}">
                                    <input type="checkbox" class="tp-check" value="{{ $item['incubatee_id'] }}" @checked($checked)>
                                    <div>
                                        <h4>{{ $item['name'] ?: 'Unnamed' }}</h4>
                                        <div class="tp-meta">
                                            <span class="tp-pill">App: {{ $item['application_no'] ?: 'NA' }}</span>
                                            @if (($item['onboard_status'] ?? '') === 'onboarded')
                                                <span class="tp-pill">{{ $item['onboard_label'] ?? 'Onboarded' }}</span>
                                                <span class="tp-pill">Batch: {{ $item['onboarding_batch_name'] ?: 'NA' }}</span>
                                            @else
                                                <span class="tp-pill tp-pill--muted">{{ $item['onboard_label'] ?? 'Not onboarded' }}</span>
                                            @endif
                                        </div>
                                        <div class="tp-meta">Phone: {{ $item['phone'] ?: 'NA' }} | Block: {{ $item['block_name'] ?: 'NA' }} | Village: {{ $item['village'] ?: 'NA' }}@if (!empty($item['district_name'])) | District: {{ $item['district_name'] }}@endif</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="tp-col">
                        <p class="tp-right-title">Selected Incubatees <span id="tpSelectedCount" class="tp-selected-count">0</span></p>
                        <div class="tp-list" id="tpSelectedPanel"></div>
                    </div>
                </div>
            </div>

            <div id="tpHiddenInputs"></div>
            <div class="tp-actions">
                <button class="tp-submit" type="submit">Update attendance</button>
                <a class="tp-link" href="{{ route(!empty($statewideEntry) ? 'spoc.technical-trainings.export-single' : 'staff.technical-trainings.export-single', $row) }}">Excel Export</a>
                <a class="tp-link" href="{{ $dashboardUrl ?? route('staff.technical-trainings.dashboard') }}">Back to dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection

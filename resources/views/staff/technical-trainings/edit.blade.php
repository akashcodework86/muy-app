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
        <form method="post" action="{{ route('staff.technical-trainings.update', $row) }}" enctype="multipart/form-data">
            @csrf
            @method('put')
            <div class="tp-grid">
                <div class="tp-field">
                    <label>Session Taken By</label>
                    <input type="text" class="tp-readonly" value="{{ $row->submitted_by_name }}" readonly>
                </div>
                <div class="tp-field">
                    <label>Date of Session *</label>
                    <input type="date" name="session_date" value="{{ old('session_date', $row->event_date?->format('Y-m-d')) }}" required>
                </div>
                <div class="tp-field">
                    <label>District</label>
                    <input type="text" class="tp-readonly" value="{{ $row->district_name ?: ($row->district?->name ?? 'NA') }}" readonly>
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
                            'attachmentRoute' => 'staff.technical-trainings.attachment',
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
                        <p class="tp-right-title">Selected Incubatees <span id="tpSelectedCount" class="tp-selected-count">0</span></p>
                        <div class="tp-list" id="tpSelectedPanel"></div>
                    </div>
                </div>
            </div>

            <div id="tpHiddenInputs"></div>
            <div class="tp-actions">
                <button class="tp-submit" type="submit">Update attendance</button>
                <a class="tp-link" href="{{ route('staff.technical-trainings.export-single', $row) }}">Excel Export</a>
                <a class="tp-link" href="{{ route('staff.technical-trainings.dashboard') }}">Back to dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection

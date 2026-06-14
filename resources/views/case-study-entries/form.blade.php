@extends('layouts.admin')

@section('title', \App\Models\CaseStudyEntry::MODULE_LABEL)
@section('heading', \App\Models\CaseStudyEntry::MODULE_LABEL)

@push('styles')
@include('branding-communication.partials.form-styles')
@endpush

@section('content')
<div class="bc-shell">
    @if (!empty($migrationMissing))
        <div class="bc-alert bc-alert--warning">Run <code>php artisan migrate</code> for <code>case_study_entries</code>.</div>
    @endif
    @if (session('status'))<div class="bc-alert bc-alert--success">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="bc-alert bc-alert--error"><strong>Please fix:</strong><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="bc-card">
        <h3 class="bc-card__title">New case study / testimonial</h3>
        <form method="post" action="{{ route($storeRoute) }}" enctype="multipart/form-data">
            @csrf
            <div class="bc-grid">
                <div class="bc-field">
                    <label>Submitted by</label>
                    <input type="text" class="bc-readonly" value="{{ $user->name }}" readonly>
                </div>
                <div class="bc-field">
                    <label for="story_date">Date <span class="bc-req">*</span></label>
                    <input type="date" id="story_date" name="story_date" value="{{ old('story_date', now()->toDateString()) }}" required>
                </div>
                <div class="bc-field bc-field--full">
                    <label for="story_title">Story title <span class="bc-req">*</span></label>
                    <input type="text" id="story_title" name="story_title" maxlength="255" value="{{ old('story_title') }}" required>
                </div>
                <div class="bc-field">
                    <label for="story_type">Story type <span class="bc-req">*</span></label>
                    <select id="story_type" name="story_type" required>
                        <option value="">— Select —</option>
                        @foreach ($storyTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('story_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="bc-field bc-field--full">
                    @include('branding-communication.partials.incubatee-single-picker')
                </div>
                <div class="bc-field bc-field--full">
                    <label for="document">Document <span class="bc-req">*</span></label>
                    <input type="file" id="document" name="document" accept=".pdf,.doc,.docx" required>
                    <p class="bc-hint">PDF or Word, max 20 MB.</p>
                </div>
                <div class="bc-field bc-field--full">
                    <label for="remarks">Short note</label>
                    <textarea id="remarks" name="remarks" maxlength="5000">{{ old('remarks') }}</textarea>
                </div>
            </div>
            <div class="bc-actions">
                <button type="submit" class="bc-submit">Save entry</button>
                <a href="{{ route($dashboardRoute) }}" class="bc-link">View dashboard</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@include('branding-communication.partials.incubatee-single-picker-script', ['searchRoute' => $searchRoute])
@endpush

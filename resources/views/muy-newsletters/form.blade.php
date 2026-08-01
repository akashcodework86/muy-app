@extends('layouts.admin')

@section('title', \App\Models\MuyNewsletterEntry::MODULE_LABEL)
@section('heading', \App\Models\MuyNewsletterEntry::MODULE_LABEL)

@push('styles')
@include('branding-communication.partials.form-styles')
@endpush

@section('content')
<div class="bc-shell">
    @if (!empty($migrationMissing))<div class="bc-alert bc-alert--warning">Run <code>php artisan migrate</code>.</div>@endif
    @if (session('status'))<div class="bc-alert bc-alert--success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="bc-alert bc-alert--error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="bc-card">
        <h3 class="bc-card__title">New newsletter entry</h3>
        <form method="post" action="{{ route($storeRoute) }}" enctype="multipart/form-data">
            @csrf
            <div class="bc-grid">
                <div class="bc-field">
                    <label>Submitted by</label>
                    <input type="text" class="bc-readonly" value="{{ $user->name }}" readonly>
                </div>
                <div class="bc-field">
                    <label for="issue_date">Issue date <span class="bc-req">*</span></label>
                    <x-activity-date-input name="issue_date" id="issue_date" />
                </div>
                <div class="bc-field">
                    <label for="issue_edition">Issue no. / edition <span class="bc-req">*</span></label>
                    <input type="text" id="issue_edition" name="issue_edition" maxlength="128" value="{{ old('issue_edition') }}" required placeholder="e.g. Issue 3 / Jan 2026">
                </div>
                <div class="bc-field">
                    <label for="distribution_mode">Distribution mode <span class="bc-req">*</span></label>
                    <select id="distribution_mode" name="distribution_mode" required>
                        <option value="">— Select —</option>
                        @foreach ($distributionModes as $value => $label)
                            <option value="{{ $value }}" @selected(old('distribution_mode') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="bc-field bc-field--full">
                    <label for="title">Newsletter title / theme <span class="bc-req">*</span></label>
                    <input type="text" id="title" name="title" maxlength="255" value="{{ old('title') }}" required>
                </div>
                <div class="bc-field bc-field--full">
                    <label for="newsletter_url">Newsletter link</label>
                    <input type="url" id="newsletter_url" name="newsletter_url" value="{{ old('newsletter_url') }}" placeholder="https://…">
                    <p class="bc-hint">Optional if you upload a PDF below. At least one of link or file is required.</p>
                </div>
                <div class="bc-field bc-field--full">
                    <label for="document">Document upload (PDF)</label>
                    <input type="file" id="document" name="document" accept=".pdf,application/pdf">
                    <p class="bc-hint">PDF, max 20 MB.</p>
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

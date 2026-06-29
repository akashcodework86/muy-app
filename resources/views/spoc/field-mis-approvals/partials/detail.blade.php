@php
    $row = $record ?? $row;
    $snapshots = (array) ($applicantSnapshots ?? $row->selected_incubatees_snapshot ?? []);
@endphp

@push('styles')
<style>
    .fma-detail-shell { display: flex; flex-direction: column; gap: 1rem; }
    .fma-detail-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
    .fma-detail-table thead tr { background: #f8fafc; }
    .fma-detail-table th, .fma-detail-table td { text-align: left; padding: 0.65rem 0.75rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .fma-detail-table tbody tr:last-child td { border-bottom: none; }
    .fma-detail-subtitle { margin: 1rem 0 0.5rem; font-size: 0.85rem; font-weight: 800; color: #0f172a; }
    .fma-detail-phone { display: inline-flex; padding: 0.15rem 0.45rem; border-radius: 999px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; font-weight: 700; text-decoration: none; font-size: 0.8rem; }
    .fma-detail-source { display: inline-flex; padding: 0.14rem 0.45rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800; }
    .fma-detail-source--phase3 { background: #eef2ff; color: #3730a3; }
    .fma-detail-source--legacy { background: #fff7ed; color: #9a3412; }
    .fma-onboard { display: inline-flex; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.72rem; font-weight: 800; white-space: nowrap; }
    .fma-onboard--yes { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .fma-onboard--no { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
    .coo-dl { display: grid; grid-template-columns: minmax(9rem, 34%) 1fr; gap: 0.55rem 1rem; font-size: 0.88rem; margin: 0; }
    .coo-dl dt { color: #64748b; font-weight: 600; margin: 0; }
    .coo-dl dd { margin: 0; color: #0f172a; }
    .coo-files { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 0; padding: 0; list-style: none; }
    .coo-files a { display: inline-flex; padding: 0.35rem 0.65rem; border-radius: 8px; background: #f0fdfa; border: 1px solid #99f6e4; color: #115e59; font-size: 0.8rem; font-weight: 700; text-decoration: none; }
    .coo-photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.65rem; margin-top: 0.35rem; }
    .coo-photo-grid a { display: block; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; }
    .coo-photo-grid img { display: block; width: 100%; height: 110px; object-fit: cover; }
</style>
@endpush

<div class="fma-detail-shell">
    @switch($moduleKey)
        @case('technical_training')
            @php $attachmentRoute = 'spoc.technical-trainings.attachment'; @endphp
            <div class="fma-detail-card">
                <h3 style="margin:0 0 0.85rem;font-size:0.98rem;font-weight:800;">Event details</h3>
                <div class="fma-detail-grid">
                    <div><span class="fma-detail-label">Date of session</span><span class="fma-detail-value">{{ $row->event_date?->format('d M Y') ?: '—' }}</span></div>
                    <div><span class="fma-detail-label">Session taken by</span><span class="fma-detail-value">{{ $row->submitted_by_name }}</span></div>
                    <div><span class="fma-detail-label">District</span><span class="fma-detail-value">{{ $row->district_name ?: ($row->district?->name ?? '—') }}</span></div>
                    <div><span class="fma-detail-label">Session name</span><span class="fma-detail-value">{{ $row->session_name }}</span></div>
                    <div class="fma-detail-full"><span class="fma-detail-label">Session brief</span><span class="fma-detail-value">{{ $row->session_brief ?: '—' }}</span></div>
                    <div><span class="fma-detail-label">Training batch</span><span class="fma-detail-value">{{ $row->training_batch_name ?: '—' }}</span></div>
                    <div><span class="fma-detail-label">Total selected</span><span class="fma-detail-value">{{ is_array($row->selected_incubatee_ids) ? count($row->selected_incubatee_ids) : 0 }}</span></div>
                    <div><span class="fma-detail-label">Submitted at</span><span class="fma-detail-value">{{ $row->submitted_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? $row->created_at?->format('d M Y H:i') ?? '—' }}</span></div>
                </div>
            </div>

            <div class="fma-detail-card">
                <h3 style="margin:0 0 0.85rem;font-size:0.98rem;font-weight:800;">Uploaded photos / video / docs</h3>
                @include('staff.technical-trainings.partials.attendance-media-preview', [
                    'mediaItems' => (array) $row->attendance_media_json,
                    'attachmentRoute' => $attachmentRoute,
                    'record' => $row,
                ])
            </div>

            <div class="fma-detail-card" style="padding:0;overflow:hidden;">
                <div style="padding:0.95rem 1.1rem;border-bottom:1px solid #e2e8f0;">
                    <h3 style="margin:0;font-size:0.98rem;font-weight:800;">Selected applicants</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="fma-detail-table">
                        <thead>
                            <tr>
                                <th>Sr.</th>
                                <th>Source</th>
                                <th>Name</th>
                                <th>Application no.</th>
                                <th>Phone</th>
                                <th>Gender</th>
                                <th>Village</th>
                                <th>Block</th>
                                <th>Onboarding status</th>
                                <th>Onboarding batch</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($snapshots as $snap)
                                @php
                                    $phone = trim((string) ($snap['phone'] ?? ''));
                                    $isLegacy = ($snap['source'] ?? '') === 'legacy_phase2' || (int) ($snap['incubatee_id'] ?? 0) < 0;
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <span class="fma-detail-source {{ $isLegacy ? 'fma-detail-source--legacy' : 'fma-detail-source--phase3' }}">
                                            {{ $isLegacy ? 'Phase 2 legacy' : 'Phase 3 CFA' }}
                                        </span>
                                    </td>
                                    <td>{{ $snap['name'] ?? 'Unnamed' }}</td>
                                    <td>{{ $snap['application_no'] ?? '—' }}</td>
                                    <td>
                                        @if ($phone !== '')
                                            <a class="fma-detail-phone" href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ trim((string) ($snap['gender'] ?? '')) !== '' ? $snap['gender'] : '—' }}</td>
                                    <td>{{ trim((string) ($snap['village'] ?? '')) !== '' ? $snap['village'] : '—' }}</td>
                                    <td>{{ trim((string) ($snap['block_name'] ?? '')) !== '' ? $snap['block_name'] : '—' }}</td>
                                    <td>@include('partials.onboarding-status-badge', ['row' => $snap])</td>
                                    <td>{{ $snap['onboarding_batch_name'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="10" style="color:#64748b;padding:1rem;">No selected applicants in snapshot.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @break

        @case('lakhpati_technical_training')
            @php $attachmentRoute = 'spoc.lakhpati-technical-trainings.attachment'; @endphp
            <div class="fma-detail-card">
                <h3 style="margin:0 0 0.85rem;font-size:0.98rem;font-weight:800;">{{ $row->session_title }}</h3>
                <div class="fma-detail-grid">
                    <div><span class="fma-detail-label">Date</span><span class="fma-detail-value">{{ $row->session_date?->format('d M Y') ?: '—' }}</span></div>
                    <div><span class="fma-detail-label">Entered by</span><span class="fma-detail-value">{{ $row->submitted_by_name }}</span></div>
                    <div><span class="fma-detail-label">District</span><span class="fma-detail-value">{{ $row->district_name }}</span></div>
                    <div><span class="fma-detail-label">Block</span><span class="fma-detail-value">{{ $row->block ?: '—' }}</span></div>
                    <div><span class="fma-detail-label">Gram panchayat</span><span class="fma-detail-value">{{ $row->gramPanchayat?->name ?? '—' }}</span></div>
                    <div><span class="fma-detail-label">Venue</span><span class="fma-detail-value">{{ $row->area ?: '—' }}</span></div>
                    <div><span class="fma-detail-label">Workshop mode</span><span class="fma-detail-value">{{ $row->formattedWorkshopMode() }}</span></div>
                    <div><span class="fma-detail-label">Requesting agency</span><span class="fma-detail-value">{{ $row->agencyTypeLabel() }}</span></div>
                    <div><span class="fma-detail-label">Participants (M / F / Total)</span><span class="fma-detail-value">{{ (int) $row->male_participants }} / {{ (int) $row->female_participants }} / {{ $row->totalParticipantCount() }}</span></div>
                    @if ($row->session_brief)
                        <div class="fma-detail-full"><span class="fma-detail-label">Brief</span><span class="fma-detail-value">{{ $row->session_brief }}</span></div>
                    @endif
                </div>

                @if (count($row->participantRows()) > 0)
                    <h4 class="fma-detail-subtitle">Participant rows</h4>
                    <div style="overflow-x:auto;">
                        <table class="fma-detail-table">
                            <thead><tr><th>#</th><th>Name</th><th>Mobile</th><th>Gender</th><th>Block</th><th>GP</th></tr></thead>
                            <tbody>
                                @foreach ($row->participantRows() as $p)
                                    <tr>
                                        <td>{{ $p['sr'] ?? '' }}</td>
                                        <td>{{ $p['name'] ?? '—' }}</td>
                                        <td>{{ $p['mobile'] ?? '—' }}</td>
                                        <td>{{ $p['gender'] ?? '—' }}</td>
                                        <td>{{ $p['block_name'] ?? '—' }}</td>
                                        <td>{{ $p['gram_panchayat_name'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($row->hasWorkshopPhotos())
                    <h4 class="fma-detail-subtitle">Workshop photos</h4>
                    @include('staff.technical-trainings.partials.attendance-media-preview', [
                        'mediaItems' => $row->workshopPhotoItems(),
                        'attachmentRoute' => $attachmentRoute,
                        'attachmentQuery' => ['collection' => 'photos'],
                        'record' => $row,
                        'showEmptyMessage' => false,
                    ])
                @endif

                @if ($row->hasAttendanceSheet())
                    <h4 class="fma-detail-subtitle">Attendance files</h4>
                    @include('staff.technical-trainings.partials.attendance-media-preview', [
                        'mediaItems' => (array) $row->attendance_media_json,
                        'attachmentRoute' => $attachmentRoute,
                        'record' => $row,
                        'showEmptyMessage' => false,
                    ])
                @endif
            </div>
            @break

        @case('line_department_meeting')
            @php $attachmentRoute = 'spoc.line-department-meetings.attachment'; @endphp
            <div class="fma-detail-card">
                <h3 style="margin:0 0 0.85rem;font-size:0.98rem;font-weight:800;">{{ $row->department_name }} — {{ $row->official_name }}</h3>
                <div class="fma-detail-grid">
                    <div><span class="fma-detail-label">Date</span><span class="fma-detail-value">{{ $row->meeting_date?->format('d M Y') }}</span></div>
                    <div><span class="fma-detail-label">Entered by</span><span class="fma-detail-value">{{ $row->submitted_by_name }}</span></div>
                    <div><span class="fma-detail-label">Level</span><span class="fma-detail-value">{{ $row->meetingLevelLabel() }}</span></div>
                    <div><span class="fma-detail-label">Mode</span><span class="fma-detail-value">{{ $row->meetingModeLabel() }}</span></div>
                    <div><span class="fma-detail-label">Purpose</span><span class="fma-detail-value">{{ $row->meetingPurposeLabel() }}</span></div>
                    @if ($row->venue)<div><span class="fma-detail-label">Venue</span><span class="fma-detail-value">{{ $row->venue }}</span></div>@endif
                    @if ($row->hub_name)<div><span class="fma-detail-label">Hub</span><span class="fma-detail-value">{{ $row->hub_name }}</span></div>@endif
                    @if ($row->district_name)<div><span class="fma-detail-label">District</span><span class="fma-detail-value">{{ $row->district_name }}</span></div>@endif
                    <div><span class="fma-detail-label">Official designation</span><span class="fma-detail-value">{{ $row->official_designation }}</span></div>
                    <div class="fma-detail-full"><span class="fma-detail-label">MUY staff present</span><span class="fma-detail-value">{{ $row->muy_staff_present }}</span></div>
                    <div class="fma-detail-full"><span class="fma-detail-label">Agenda</span><span class="fma-detail-value">{{ $row->agenda_summary }}</span></div>
                    <div class="fma-detail-full"><span class="fma-detail-label">Outcome</span><span class="fma-detail-value">{{ $row->outcome_decision }}</span></div>
                    @if (!empty($row->incubatees_discussed_json))
                        <div class="fma-detail-full"><span class="fma-detail-label">Incubatees discussed</span><span class="fma-detail-value">{{ implode(', ', (array) $row->incubatees_discussed_json) }}</span></div>
                    @endif
                </div>

                @if ($row->hasProofDocument())
                    <h4 class="fma-detail-subtitle">Proof documents</h4>
                    @include('staff.technical-trainings.partials.attendance-media-preview', [
                        'mediaItems' => (array) $row->proof_media_json,
                        'attachmentRoute' => $attachmentRoute,
                        'record' => $row,
                        'showEmptyMessage' => false,
                    ])
                @endif
            </div>
            @break

        @case('community_org_outreach')
            @php
                $documentRoute = 'spoc.community-org-outreach.document';
                $photoRoute = 'spoc.community-org-outreach.photo';
            @endphp
            <div class="fma-detail-card">
                <dl class="coo-dl">
                    <dt>Visit date</dt><dd>{{ $row->visit_date?->format('d M Y') }}</dd>
                    <dt>Hub</dt><dd>{{ $row->hub_name }}</dd>
                    <dt>District</dt><dd>{{ $row->district_name }}</dd>
                    <dt>Organisation</dt><dd>{{ $row->organization_name }}</dd>
                    <dt>Organisation type</dt><dd>{{ \App\Support\CommunityOrganizationOutreachOptions::organizationTypeDisplay((string) $row->organization_type, $row->organization_type_other) }}</dd>
                    <dt>Person met</dt><dd>{{ $row->person_met_name }}</dd>
                    <dt>Designation</dt><dd>{{ $row->person_met_designation ?: '—' }}</dd>
                    <dt>POC</dt><dd>{{ $row->poc_name }}</dd>
                    <dt>POC phone</dt><dd>{{ $row->poc_phone }}</dd>
                    <dt>POC email</dt><dd>{{ $row->poc_email ?: '—' }}</dd>
                    <dt>Purpose</dt><dd>{{ \App\Support\CommunityOrganizationOutreachOptions::labelFor('purpose', (string) $row->purpose) }}</dd>
                    <dt>Meeting mode</dt><dd>{{ \App\Support\CommunityOrganizationOutreachOptions::labelFor('meeting_mode', (string) $row->meeting_mode) }}</dd>
                    <dt>Remark</dt><dd>{{ $row->remarks ?: '—' }}</dd>
                    <dt>Documents</dt>
                    <dd>
                        @php $documents = array_values((array) $row->documents_json); @endphp
                        @if ($documents === [])
                            —
                        @else
                            <ul class="coo-files">
                                @foreach ($documents as $index => $doc)
                                    @if (is_array($doc))
                                        <li>
                                            <a href="{{ route($documentRoute, [$row, 'index' => $index]) }}">
                                                {{ $doc['original_name'] ?? ('Document '.($index + 1)) }}
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </dd>
                    <dt>Photos</dt>
                    <dd>
                        @php $photos = array_values((array) $row->photos_json); @endphp
                        @if ($photos === [])
                            —
                        @else
                            <div class="coo-photo-grid">
                                @foreach ($photos as $index => $photo)
                                    @if (is_array($photo))
                                        <a href="{{ route($photoRoute, [$row, 'index' => $index, 'inline' => 1]) }}" target="_blank" rel="noopener">
                                            <img src="{{ route($photoRoute, [$row, 'index' => $index, 'inline' => 1]) }}" alt="Visit photo {{ $index + 1 }}">
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </dd>
                    <dt>Submitted by</dt><dd>{{ $row->submitted_by_name }} · {{ $row->submitted_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? $row->created_at?->format('d M Y H:i') }}</dd>
                </dl>
            </div>
            @break

        @default
            <div class="fma-detail-card">
                <p style="margin:0;color:#64748b;">No detail view configured for this module.</p>
            </div>
    @endswitch
</div>

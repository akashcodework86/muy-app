@extends('layouts.admin')

@section('title', 'Training Package Attendance Details')
@section('heading', 'Training Package Attendance Details')

@section('content')
    <div style="display:flex;flex-direction:column;gap:0.9rem;">
        <a href="{{ route('admin.training-packages.index') }}" style="text-decoration:none;">← Back to dashboard</a>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:0.9rem;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.75rem;">
                <div><strong>Event taken by:</strong> {{ $entry->event_taken_by_name }}</div>
                <div><strong>Date of event:</strong> {{ $entry->event_date?->format('d M Y') }}</div>
                <div><strong>District:</strong> {{ $entry->district?->name ?? $entry->district_name ?? '—' }}</div>
                <div><strong>Block:</strong> {{ $entry->block }}</div>
                <div><strong>Training package:</strong> {{ strtoupper((string) $entry->training_package) }}</div>
                <div><strong>Participants selected:</strong> {{ number_format((int) $entry->selected_incubatees_count) }}</div>
            </div>

            <div style="margin-top:0.85rem;">
                <strong>Attendance document:</strong>
                <a href="{{ route('admin.training-packages.attachment', $entry) }}">Download file</a>
                <span style="color:#64748b;">({{ $entry->attendance_file_name ?: 'Attachment' }})</span>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:0.9rem;">
            <h3 style="margin:0 0 0.6rem;">Selected Incubatees</h3>
            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                @forelse ((array) ($entry->selected_incubatees_json ?? []) as $row)
                    <div style="border:1px solid #e2e8f0;border-radius:10px;padding:0.55rem;background:#f8fafc;">
                        <div style="font-weight:600;">{{ $row['name'] ?? '—' }}</div>
                        <div style="font-size:0.84rem;color:#64748b;">
                            App: {{ $row['application_no'] ?? '—' }}
                            | Phone: {{ $row['phone'] ?? '—' }}
                            | Batch: {{ $row['batch_name'] ?? '—' }}
                            | Block: {{ $row['block'] ?? '—' }}
                        </div>
                    </div>
                @empty
                    <div style="color:#64748b;">No participants found.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@php
    /** @var \App\Models\DistrictWorkshopSession $row */
    $routeName = (string) ($attachmentRouteName ?? 'staff.district-workshop-sessions.attachment');

    $mediaItems = is_array($row->attendance_media_json ?? null) ? $row->attendance_media_json : [];
    $firstIdx = null;
    $firstMedia = null;
    foreach ($mediaItems as $idx => $media) {
        if (! is_array($media)) {
            continue;
        }
        if ((string) ($media['path'] ?? '') === '') {
            continue;
        }
        $firstIdx = (int) $idx;
        $firstMedia = $media;
        break;
    }

    $mediaCount = 0;
    foreach ($mediaItems as $media) {
        if (is_array($media) && (string) ($media['path'] ?? '') !== '') {
            $mediaCount++;
        }
    }

    $mediaMime = '';
    $mediaName = 'Attendance sheet';
    $mediaKind = 'file';
    $viewUrl = '';
    $downloadUrl = '';

    if ($firstMedia !== null && $firstIdx !== null) {
        $mediaMime = (string) ($firstMedia['mime'] ?? '');
        $mediaName = (string) ($firstMedia['original_name'] ?? 'Attendance sheet');
        if (str_starts_with($mediaMime, 'image/')) {
            $mediaKind = 'image';
        } elseif (str_starts_with($mediaMime, 'video/')) {
            $mediaKind = 'video';
        } elseif ($mediaMime === 'application/pdf' || str_ends_with(strtolower($mediaName), '.pdf')) {
            $mediaKind = 'pdf';
        }

        $viewQuery = ['inline' => 1];
        if ($firstIdx > 0) {
            $viewQuery['index'] = $firstIdx;
        }
        $viewUrl = route($routeName, $row).'?'.http_build_query($viewQuery);

        $dlQuery = $firstIdx > 0 ? ['index' => $firstIdx] : [];
        $downloadUrl = route($routeName, $row).($dlQuery !== [] ? '?'.http_build_query($dlQuery) : '');
    }

    $sheetTitle = $mediaCount > 1
        ? 'View attendance sheet (preview first of '.$mediaCount.' files)'
        : 'View attendance sheet';
@endphp
<div class="ees-dash-att">
    @if ($firstMedia !== null && $viewUrl !== '')
        <button
            type="button"
            class="ees-dash-att__doc-btn js-tt-media-open"
            data-view-url="{{ $viewUrl }}"
            data-download-url="{{ $downloadUrl }}"
            data-media-kind="{{ $mediaKind }}"
            data-media-name="{{ $mediaName }}"
            aria-label="{{ $sheetTitle }}"
            title="{{ $sheetTitle }}"
        >
            <span class="ees-dash-att__doc-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15h6"/></svg>
            </span>
            View sheet
        </button>
    @else
        <span class="ees-dash-att__no-doc">Pending</span>
    @endif
</div>

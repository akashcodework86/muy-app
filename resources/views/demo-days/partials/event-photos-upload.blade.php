@php
    $requiresPhotos = ! ($isEdit ?? false) || empty($row) || ! $row->hasEventPhotos();
    $existingPhotoCount = ($isEdit ?? false) && ! empty($row) ? count($row->eventPhotoItems()) : 0;
    $maxPhotos = 5;
    $remainingSlots = max(0, $maxPhotos - $existingPhotoCount);
@endphp
<div class="ddy-field ddy-field--full">
    <label for="ddyEventPhotosInput">Event proof (photos) @if($requiresPhotos)<span class="ddy-req">*</span>@endif</label>
    <input id="ddyEventPhotosInput" type="file" name="event_photos[]"
        accept=".jpg,.jpeg,.png,.webp,image/*" capture="environment" multiple
        @if($requiresPhotos) required @endif
        @if($isEdit && $remainingSlots === 0) disabled @endif>
    <p class="ddy-hint">
        @if ($isEdit && $existingPhotoCount > 0)
            {{ $existingPhotoCount }} photo(s) saved. Upload up to {{ $remainingSlots }} more (max {{ $maxPhotos }} total, JPG/PNG, 20 MB each).
        @else
            Upload event photos (JPG/PNG). Minimum 1, maximum {{ $maxPhotos }}. Preview appears below — tap × to remove before submit.
        @endif
    </p>
    @if ($isEdit && ! empty($row) && $row->hasEventPhotos())
        @include('staff.technical-trainings.partials.attendance-media-preview', [
            'mediaItems' => $row->eventPhotoItems(),
            'attachmentRoute' => $attachmentRoute,
            'record' => $row,
            'showEmptyMessage' => false,
        ])
    @endif
    <div id="ddyEventPhotosPreview" class="ddy-media-preview"></div>
    @error('event_photos')<p class="ddy-hint" style="color:#b91c1c;">{{ $message }}</p>@enderror
    @error('event_photos.*')<p class="ddy-hint" style="color:#b91c1c;">{{ $message }}</p>@enderror
</div>

@include('demo-days.partials.event-photos-upload-script', ['maxPhotos' => $isEdit ? $remainingSlots : $maxPhotos])

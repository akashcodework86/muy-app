@if ($rows->isEmpty())
    <div class="smp-posts-empty">No entries yet.</div>
@else
    <div class="smp-posts-grid">
        @foreach ($rows as $row)
            @php
                $rowNumber = (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'firstItem') && $rows->firstItem() !== null)
                    ? (int) $rows->firstItem() + $loop->index
                    : $loop->iteration;
            @endphp
            @include('social-media-posts.partials.post-card', [
                'row' => $row,
                'rowNumber' => $rowNumber,
            ])
        @endforeach
    </div>
@endif

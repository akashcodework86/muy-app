<div class="smp-table-wrap">
    <table class="smp-table">
        <thead>
            <tr>
                <th class="smp-table__num-head">#</th>
                <th>Preview</th>
                <th>Date</th>
                <th>URL</th>
                <th>Platforms</th>
                <th>Description</th>
                @if (!empty($isAdminView))
                    <th>Submitted by</th>
                @endif
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $rowNumber = (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'firstItem') && $rows->firstItem() !== null)
                        ? (int) $rows->firstItem() + $loop->index
                        : $loop->iteration;
                @endphp
                <tr>
                    <td class="smp-table__num">{{ $rowNumber }}</td>
                    <td>
                        @include('social-media-posts.partials.list-thumbnail', ['row' => $row, 'size' => 'md'])
                    </td>
                    <td>{{ $row->posted_on?->format('d M Y') }}</td>
                    <td>
                        <a class="smp-url" href="{{ $row->post_url }}" target="_blank" rel="noopener noreferrer">{{ Str::limit($row->post_url, 48) }}</a>
                    </td>
                    <td>@include('social-media-posts.partials.platform-badges', ['row' => $row])</td>
                    <td><span class="smp-desc">{{ $row->description ?: '—' }}</span></td>
                    @if (!empty($isAdminView))
                        <td>{{ $row->submitted_by_name }}</td>
                    @endif
                    <td>
                        <div class="smp-row-actions">
                            <a href="{{ route($showRoute, $row) }}" class="smp-btn smp-btn--secondary" style="padding:0.4rem 0.7rem; font-size:0.8rem;">View</a>
                            @if (\App\Support\SocialMediaPostAccess::canDelete(auth()->user(), $row))
                                @php
                                    $destroyRoute = auth()->user()->role === 'state_admin'
                                        ? 'admin.social-media-posts.destroy'
                                        : 'spoc.social-media-posts.destroy';
                                @endphp
                                <form
                                    class="smp-delete-inline"
                                    method="post"
                                    action="{{ route($destroyRoute, $row) }}"
                                    onsubmit="return confirm('Delete this social media post permanently?');"
                                >
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="smp-btn--delete">Delete</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ !empty($isAdminView) ? 8 : 7 }}" class="smp-empty">No entries yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if ($totalCount > 0)
        <div class="smp-table-foot">
            Total entries: <strong>{{ number_format($totalCount) }}</strong>
            @if (!empty($isPaginated) && is_object($rows) && method_exists($rows, 'lastPage') && $rows->lastPage() > 1)
                · Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}
            @endif
        </div>
    @endif
</div>

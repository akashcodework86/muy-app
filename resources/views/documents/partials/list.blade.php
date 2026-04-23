<form method="get" action="{{ request()->url() }}" style="display:flex;flex-wrap:wrap;gap:0.55rem;align-items:center;margin:0 0 1rem;">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search documents or tags"
        style="min-width:16rem;max-width:24rem;flex:1;padding:0.45rem 0.6rem;border:1px solid #d4d4d8;border-radius:8px;">
    <select name="category" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
        <option value="0">All categories</option>
        @foreach ($categories as $cat)
            <option value="{{ $cat->id }}" @selected((int) ($filters['category'] ?? 0) === (int) $cat->id)>{{ $cat->name }}</option>
        @endforeach
    </select>
    <select name="tag" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
        <option value="">All tags</option>
        @foreach ($tags as $t)
            <option value="{{ $t }}" @selected(($filters['tag'] ?? '') === $t)>{{ $t }}</option>
        @endforeach
    </select>
    <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.45rem 0.8rem;border-radius:8px;font-weight:600;">Filter</button>
</form>

<div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:8px;font-size:0.875rem;">
        <thead>
            <tr style="text-align:left;background:#f8fafc;">
                <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Title</th>
                <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Category</th>
                <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Tags</th>
                <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">File</th>
                <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Updated</th>
                <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($docs as $d)
                @php $v = $d->latestVersion; @endphp
                <tr>
                    <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;font-weight:600;">{{ $d->title }}</td>
                    <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">{{ $d->category?->name ?? '—' }}</td>
                    <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ implode(', ', $d->normalizedTags()) ?: '—' }}</td>
                    <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">
                        @if ($v)
                            {{ $v->original_name }} · v{{ $v->version_no }}
                        @else
                            —
                        @endif
                    </td>
                    <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ $d->updated_at?->format('Y-m-d') }}</td>
                    <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">
                        @if ($v)
                            <a href="{{ route('documents.download', $d) }}">Download</a>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:1rem;color:#64748b;">No documents found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($docs->hasPages())
    <div style="margin-top:1rem;">{{ $docs->links() }}</div>
@endif

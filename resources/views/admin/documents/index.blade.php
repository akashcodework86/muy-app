@extends('layouts.admin')

@section('title', 'Document repository')
@section('heading', 'Document repository')

@section('content')
    <form method="get" action="{{ route('admin.documents.index') }}" style="display:flex;flex-wrap:wrap;gap:0.55rem;align-items:center;margin:0 0 1rem;">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search title or tags"
            style="min-width:16rem;max-width:24rem;flex:1;padding:0.45rem 0.6rem;border:1px solid #d4d4d8;border-radius:8px;">
        <select name="category" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
            <option value="0">All categories</option>
            @foreach ($categories as $root)
                <option value="{{ $root->id }}" @selected((int) ($filters['category'] ?? 0) === (int) $root->id)>{{ $root->name }}</option>
                @foreach ($root->children as $child)
                    <option value="{{ $child->id }}" @selected((int) ($filters['category'] ?? 0) === (int) $child->id)>&nbsp;&nbsp;↳ {{ $child->name }}</option>
                @endforeach
            @endforeach
        </select>
        <select name="role" style="padding:0.45rem 0.55rem;border:1px solid #d4d4d8;border-radius:8px;">
            <option value="">All visibilities</option>
            @foreach ($roles as $r)
                <option value="{{ $r }}" @selected(($filters['role'] ?? '') === $r)>{{ str_replace('_', ' ', ucfirst($r)) }}</option>
            @endforeach
        </select>
        <button type="submit" style="background:#18181b;color:#fff;border:none;padding:0.45rem 0.8rem;border-radius:8px;font-weight:600;">Filter</button>
        <a href="{{ route('admin.documents.create') }}" style="margin-left:auto;background:#4f46e5;color:#fff;text-decoration:none;padding:0.45rem 0.8rem;border-radius:8px;font-weight:600;">Upload document</a>
    </form>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e4e4e7;border-radius:8px;font-size:0.875rem;">
            <thead>
                <tr style="text-align:left;background:#f8fafc;">
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Title</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Category</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Tags</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Visible to</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Latest</th>
                    <th style="padding:0.55rem 0.65rem;border-bottom:1px solid #e4e4e7;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($docs as $d)
                    @php $v = $d->latestVersion; @endphp
                    <tr>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;font-weight:600;">{{ $d->title }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;">{{ $d->category?->displayPath() ?? '—' }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ implode(', ', $d->normalizedTags()) ?: '—' }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">{{ implode(', ', $d->allowed_roles ?? []) }}</td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;color:#52525b;">
                            @if ($v)
                                v{{ $v->version_no }} · {{ $v->original_name }}
                            @else
                                —
                            @endif
                        </td>
                        <td style="padding:0.45rem 0.65rem;border-bottom:1px solid #f4f4f5;white-space:nowrap;">
                            <a href="{{ route('admin.documents.edit', $d) }}">Edit</a>
                            @if ($v)
                                <span style="color:#d4d4d8;">|</span>
                                <a href="{{ route('documents.download', $d) }}">Download</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:1rem;">No documents yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($docs->hasPages())
        <div style="margin-top:1rem;">{{ $docs->links() }}</div>
    @endif
@endsection

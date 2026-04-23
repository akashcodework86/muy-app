@php
    $selectedDocCategory = $doc?->category;
    $selectedRoot = (int) old(
        'document_category_id',
        (int) (($selectedDocCategory && $selectedDocCategory->parent_id) ? $selectedDocCategory->parent_id : ($selectedDocCategory->id ?? 0))
    );
    $selectedSub = (int) old(
        'document_subcategory_id',
        (int) (($selectedDocCategory && $selectedDocCategory->parent_id) ? $selectedDocCategory->id : 0)
    );
    $selectedRoles = old('allowed_roles', $doc?->allowed_roles ?? []);
    if (! is_array($selectedRoles)) {
        $selectedRoles = [];
    }
    $tagsText = old('tags', implode(', ', $doc?->normalizedTags() ?? []));
    $subsJson = json_encode($subcategoriesByRoot ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
@endphp

<div style="margin-bottom:0.85rem;">
    <label for="title" style="display:block;font-weight:600;margin-bottom:0.25rem;">Title</label>
    <input id="title" name="title" required maxlength="191" value="{{ old('title', $doc?->title ?? '') }}"
        style="width:100%;padding:0.45rem 0.6rem;border:1px solid #d4d4d8;border-radius:8px;">
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;margin-bottom:0.85rem;">
    <div>
        <label for="document_category_id" style="display:block;font-weight:600;margin-bottom:0.25rem;">Category</label>
        <select id="document_category_id" name="document_category_id"
            style="width:100%;padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:8px;">
            <option value="">— None —</option>
            @foreach ($rootCategories as $cat)
                <option value="{{ $cat->id }}" @selected($selectedRoot === (int) $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="document_subcategory_id" style="display:block;font-weight:600;margin-bottom:0.25rem;">Subcategory (optional)</label>
        <select id="document_subcategory_id" name="document_subcategory_id"
            style="width:100%;padding:0.45rem 0.5rem;border:1px solid #d4d4d8;border-radius:8px;">
            <option value="">— None —</option>
        </select>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;margin-bottom:0.85rem;">
    <div>
        <label for="category_name" style="display:block;font-weight:600;margin-bottom:0.25rem;">Or create new category</label>
        <input id="category_name" name="category_name" value="{{ old('category_name') }}" maxlength="160"
            placeholder="e.g. SOP / Circular / Templates"
            style="width:100%;padding:0.45rem 0.6rem;border:1px solid #d4d4d8;border-radius:8px;">
    </div>
    <div>
        <label for="subcategory_name" style="display:block;font-weight:600;margin-bottom:0.25rem;">Or create new subcategory</label>
        <input id="subcategory_name" name="subcategory_name" value="{{ old('subcategory_name') }}" maxlength="160"
            placeholder="e.g. GST / Registration / Branding"
            style="width:100%;padding:0.45rem 0.6rem;border:1px solid #d4d4d8;border-radius:8px;">
    </div>
</div>

<div style="margin-bottom:0.85rem;">
    <label for="tags" style="display:block;font-weight:600;margin-bottom:0.25rem;">Tags (comma separated)</label>
    <input id="tags" name="tags" maxlength="1200" value="{{ $tagsText }}"
        placeholder="gst, artisan, template"
        style="width:100%;padding:0.45rem 0.6rem;border:1px solid #d4d4d8;border-radius:8px;">
</div>

<fieldset style="margin:0 0 0.85rem;padding:0.7rem 0.9rem;border:1px solid #e4e4e7;border-radius:8px;">
    <legend style="font-weight:600;font-size:0.88rem;">Visible to roles</legend>
    <div style="display:flex;flex-wrap:wrap;gap:0.55rem 1rem;">
        @foreach ($roles as $role)
            <label style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.88rem;">
                <input type="checkbox" name="allowed_roles[]" value="{{ $role }}" @checked(in_array($role, $selectedRoles, true))>
                <span>{{ str_replace('_', ' ', ucfirst($role)) }}</span>
            </label>
        @endforeach
    </div>
</fieldset>

@if (empty($fileOptional))
<div style="margin-bottom:0.9rem;">
    <label for="file" style="display:block;font-weight:600;margin-bottom:0.25rem;">File</label>
    <input id="file" type="file" name="file" required accept=".pdf,.docx,.xlsx,.pptx,.jpg,.jpeg,.png"
        style="padding:0.4rem 0.45rem;border:1px solid #d4d4d8;border-radius:8px;background:#fff;">
    <p style="margin:0.25rem 0 0;font-size:0.78rem;color:#71717a;">Allowed: PDF, DOCX, XLSX, PPTX, JPG, PNG · Max 25 MB.</p>
</div>
@endif

@push('scripts')
<script>
(function () {
    const rootSel = document.getElementById('document_category_id');
    const subSel = document.getElementById('document_subcategory_id');
    if (!rootSel || !subSel) return;
    const map = {!! $subsJson ?: '{}' !!};
    const selectedSub = {{ (int) $selectedSub }};

    function renderSubs() {
        const rootId = String(rootSel.value || '');
        const items = map[rootId] || [];
        subSel.innerHTML = '<option value="">— None —</option>';
        items.forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = String(item.id);
            opt.textContent = item.name;
            if (Number(item.id) === Number(selectedSub)) {
                opt.selected = true;
            }
            subSel.appendChild(opt);
        });
    }

    rootSel.addEventListener('change', function () {
        renderSubs();
        if (String(rootSel.value || '') === '') {
            subSel.value = '';
        }
    });
    renderSubs();
})();
</script>
@endpush

@php
    use App\Support\ServiceFieldTypes;
    use App\Support\ServiceSchemaTemplates;
    $fieldTypeMeta = ServiceFieldTypes::all();
    $schemaTemplates = ServiceSchemaTemplates::all();
    $fieldTypeSupportsOptions = [];
    foreach ($fieldTypeMeta as $tk => $tm) {
        $fieldTypeSupportsOptions[$tk] = (bool) ($tm['supports_options'] ?? false);
    }
    if (! isset($schemaInitial) || ! is_array($schemaInitial)) {
        $schemaInitial = [];
    }
@endphp

<fieldset style="margin:0 0 1rem; padding:0.75rem 0.9rem; border:1px solid #e4e4e7; border-radius:8px;">
    <legend style="padding:0 0.4rem; font-size:0.85rem; font-weight:600; color:#3730a3;">Submission form builder</legend>
    <p style="font-size:0.78rem; color:#71717a; margin:0 0 0.65rem;">Add the fields staff should fill when submitting this service. Leave it empty if no extra details are needed.</p>

    <div style="display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; margin-bottom:0.65rem;">
        <label style="font-size:0.82rem;">Use template</label>
        <select id="svc_schema_template" style="padding:0.35rem 0.5rem; border:1px solid #d4d4d8; border-radius:6px; font-size:0.82rem; min-width:14rem;">
            <option value="">— Choose —</option>
            @foreach ($schemaTemplates as $tplName => $_rows)
                <option value="{{ $tplName }}">{{ $tplName }}</option>
            @endforeach
        </select>
        <button type="button" id="svc_schema_add" style="background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe; padding:0.35rem 0.65rem; border-radius:6px; font-size:0.82rem; font-weight:600; cursor:pointer;">+ Add field</button>
    </div>
    <p style="font-size:0.74rem; color:#6b7280; margin:0 0 0.55rem;">Tip: fill both <strong>Field ID</strong> and <strong>Label</strong> to include a field in preview and save.</p>

    <div id="svc_schema_rows" style="display:flex; flex-direction:column; gap:0.5rem;"></div>

    <input type="hidden" name="field_schema" id="svc_schema_hidden" value="{{ e(old('field_schema', json_encode($schemaInitial, JSON_UNESCAPED_UNICODE))) }}">

    <details style="margin-top:0.75rem;">
        <summary style="cursor:pointer; font-size:0.82rem; font-weight:600; color:#52525b;">Advanced (JSON)</summary>
        <textarea id="svc_schema_raw" rows="8" style="width:100%; margin-top:0.35rem; font-family:ui-monospace,monospace; font-size:0.78rem; padding:0.5rem; border:1px solid #d4d4d8; border-radius:6px;" spellcheck="false"></textarea>
        <p style="font-size:0.72rem; color:#71717a; margin:0.25rem 0 0;">Paste a JSON array; it syncs into the builder when valid.</p>
    </details>
</fieldset>

<script>
(function () {
    const TYPES = @json(array_keys($fieldTypeMeta));
    const TYPE_LABELS = @json(collect($fieldTypeMeta)->map(fn ($m) => $m['label'])->all());
    const TEMPLATES = @json($schemaTemplates);
    const supportsOptions = @json($fieldTypeSupportsOptions);

    const rowsEl = document.getElementById('svc_schema_rows');
    const hiddenEl = document.getElementById('svc_schema_hidden');
    const rawEl = document.getElementById('svc_schema_raw');
    const tplEl = document.getElementById('svc_schema_template');
    const addBtn = document.getElementById('svc_schema_add');
    if (!rowsEl || !hiddenEl) return;

    let rows = [];

    function parseInitial() {
        try {
            const v = JSON.parse(hiddenEl.value || '[]');
            rows = Array.isArray(v) ? v : [];
        } catch (e) {
            rows = [];
        }
    }

    function normalizeRow(r) {
        const o = {
            key: String(r.key || '').trim(),
            label: String(r.label || '').trim(),
            type: String(r.type || 'text').trim(),
            required: !!r.required,
        };
        if (r.help) o.help = String(r.help);
        if (r.min !== undefined && r.min !== '') o.min = Number(r.min);
        if (r.max !== undefined && r.max !== '') o.max = Number(r.max);
        if (Array.isArray(r.options)) {
            o.options = r.options.map(function (x) {
                if (typeof x === 'string') return { value: x.trim(), label: x.trim() };
                if (x && x.value != null) return { value: String(x.value), label: String(x.label || x.value) };
                return null;
            }).filter(Boolean);
        }
        return o;
    }

    function syncHidden() {
        const clean = rows.map(normalizeRow).filter(function (r) {
            return r.key && r.label && TYPES.indexOf(r.type) >= 0;
        });
        hiddenEl.value = JSON.stringify(clean);
        if (rawEl) rawEl.value = JSON.stringify(clean, null, 2);
    }

    function optionLines(opts) {
        if (!Array.isArray(opts)) return '';
        return opts.map(function (o) {
            if (typeof o === 'string') return o;
            if (o.value === o.label) return o.value;
            return o.value + '|' + o.label;
        }).join('\n');
    }

    function parseOptionLines(text) {
        const out = [];
        String(text || '').split('\n').forEach(function (line) {
            line = line.trim();
            if (!line) return;
            const p = line.split('|');
            if (p.length >= 2) {
                out.push({ value: p[0].trim(), label: p.slice(1).join('|').trim() });
            } else {
                out.push({ value: line, label: line });
            }
        });
        return out;
    }

    function render() {
        rowsEl.innerHTML = '';
        rows.forEach(function (row, idx) {
            const wrap = document.createElement('div');
            wrap.style.cssText = 'border:1px solid #e4e4e7;border-radius:8px;padding:0.55rem 0.65rem;background:#fafafa;';
            const typeOpts = TYPES.map(function (t) {
                return '<option value="' + t + '"' + (row.type === t ? ' selected' : '') + '>' + (TYPE_LABELS[t] || t) + '</option>';
            }).join('');
            const optSupport = supportsOptions[row.type];
            wrap.innerHTML =
                '<div style="display:grid;grid-template-columns:1fr 1fr 8rem auto;gap:0.35rem;align-items:end;">' +
                '<div><label style="font-size:0.72rem;color:#52525b;">Field ID (snake_case)</label><input data-k="key" type="text" value="' + (row.key || '').replace(/"/g, '&quot;') + '" placeholder="e.g. certificate_no" style="width:100%;padding:0.3rem 0.4rem;border:1px solid #d4d4d8;border-radius:4px;font-size:0.82rem;"></div>' +
                '<div><label style="font-size:0.72rem;color:#52525b;">Label</label><input data-k="label" type="text" value="' + (row.label || '').replace(/"/g, '&quot;') + '" style="width:100%;padding:0.3rem 0.4rem;border:1px solid #d4d4d8;border-radius:4px;font-size:0.82rem;"></div>' +
                '<div><label style="font-size:0.72rem;color:#52525b;">Type</label><select data-k="type" style="width:100%;padding:0.3rem 0.4rem;border:1px solid #d4d4d8;border-radius:4px;font-size:0.82rem;">' + typeOpts + '</select></div>' +
                '<div style="display:flex;gap:0.35rem;align-items:center;"><label style="font-size:0.72rem;display:flex;align-items:center;gap:0.2rem;cursor:pointer;"><input data-k="required" type="checkbox"' + (row.required ? ' checked' : '') + '> Required</label><button type="button" data-del style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:0.25rem 0.45rem;border-radius:4px;font-size:0.75rem;cursor:pointer;">Remove</button></div>' +
                '</div>' +
                '<div style="margin-top:0.35rem;"><label style="font-size:0.72rem;color:#52525b;">Help text (optional)</label><input data-k="help" type="text" value="' + (row.help || '').replace(/"/g, '&quot;') + '" style="width:100%;padding:0.3rem 0.4rem;border:1px solid #d4d4d8;border-radius:4px;font-size:0.82rem;"></div>' +
                '<div data-optwrap style="margin-top:0.35rem;display:' + (optSupport ? 'block' : 'none') + ';"><label style="font-size:0.72rem;color:#52525b;">Options (one per line, optional <code>value|Label</code>)</label><textarea data-k="options" rows="3" style="width:100%;padding:0.35rem 0.45rem;border:1px solid #d4d4d8;border-radius:4px;font-size:0.78rem;font-family:ui-monospace,monospace;"></textarea></div>';

            const ota = wrap.querySelector('[data-k="options"]');
            if (ota) ota.value = optionLines(row.options);

            wrap.querySelectorAll('input,select,textarea').forEach(function (el) {
                el.addEventListener('input', function () { pullFromDom(); });
                el.addEventListener('change', function () { pullFromDom(); });
            });
            wrap.querySelector('[data-del]').addEventListener('click', function () {
                rows.splice(idx, 1);
                render();
                syncHidden();
            });
            rowsEl.appendChild(wrap);
        });
    }

    function pullFromDom() {
        const cards = rowsEl.children;
        rows = [];
        for (let i = 0; i < cards.length; i++) {
            const c = cards[i];
            const get = function (k) {
                const el = c.querySelector('[data-k="' + k + '"]');
                return el ? el.value : '';
            };
            const req = c.querySelector('[data-k="required"]');
            const type = get('type') || 'text';
            const r = {
                key: get('key'),
                label: get('label'),
                type: type,
                required: req && req.checked,
            };
            const h = get('help');
            if (h) r.help = h;
            if (supportsOptions[type]) {
                r.options = parseOptionLines(c.querySelector('[data-k="options"]').value);
            }
            rows.push(r);
            const ow = c.querySelector('[data-optwrap]');
            if (ow) ow.style.display = supportsOptions[type] ? 'block' : 'none';
        }
        syncHidden();
    }

    tplEl && tplEl.addEventListener('change', function () {
        const name = tplEl.value;
        if (!name || !TEMPLATES[name]) return;
        rows = (TEMPLATES[name] || []).map(function (x) { return Object.assign({}, x); });
        tplEl.value = '';
        render();
        syncHidden();
    });

    addBtn && addBtn.addEventListener('click', function () {
        rows.push({ key: '', label: '', type: 'text', required: false });
        render();
        syncHidden();
    });

    if (rawEl) {
        rawEl.addEventListener('blur', function () {
            try {
                const v = JSON.parse(rawEl.value || '[]');
                if (Array.isArray(v)) {
                    rows = v;
                    render();
                    syncHidden();
                }
            } catch (e) { /* ignore */ }
        });
    }

    const form = hiddenEl.closest('form');
    form && form.addEventListener('submit', function () { pullFromDom(); });

    parseInitial();
    render();
    syncHidden();
})();
</script>

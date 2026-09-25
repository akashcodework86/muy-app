@extends('layouts.admin')

@section('title', 'MIS Data Assistant')
@section('heading', 'MIS Data Assistant')

@push('styles')
<style>
    .mia-shell{max-width:1180px;margin:0 auto;display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:1rem;align-items:start}
    .mia-card{background:#fff;border:1px solid #dbe4ee;border-radius:20px;box-shadow:0 8px 30px rgba(15,23,42,.06);overflow:hidden}
    .mia-hero{padding:1.2rem 1.3rem;background:linear-gradient(135deg,#0f766e,#2563eb);color:#fff;display:flex;justify-content:space-between;gap:1rem;align-items:center}
    .mia-hero h2{margin:0;font-size:1.3rem}.mia-hero p{margin:.3rem 0 0;color:#dbeafe;font-size:.82rem}.mia-live{padding:.35rem .65rem;border-radius:999px;background:rgba(255,255,255,.16);font-size:.7rem;font-weight:800;white-space:nowrap}
    .mia-chat{height:min(58vh,590px);min-height:390px;overflow:auto;padding:1.1rem;background:#f8fafc;display:flex;flex-direction:column;gap:.85rem;scroll-behavior:smooth}
    .mia-msg{display:flex;gap:.65rem;align-items:flex-start}.mia-msg--user{justify-content:flex-end}.mia-avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#dbeafe;color:#1d4ed8;font-weight:900;flex:0 0 auto}.mia-bubble{max-width:min(760px,86%);border:1px solid #e2e8f0;border-radius:5px 16px 16px 16px;background:#fff;padding:.8rem .9rem;color:#1e293b;font-size:.86rem;line-height:1.55}.mia-msg--user .mia-bubble{background:#4338ca;color:#fff;border-color:#4338ca;border-radius:16px 5px 16px 16px}.mia-answer strong{font-size:1rem;color:#0f172a}.mia-meta{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.6rem}.mia-pill{padding:.22rem .48rem;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:.66rem;font-weight:800}.mia-note{margin-top:.55rem;color:#64748b;font-size:.7rem}.mia-source{display:inline-flex;margin-top:.65rem;color:#0f766e;font-weight:800;text-decoration:none;font-size:.75rem}.mia-source:hover{text-decoration:underline}
    .mia-table-wrap{overflow:auto;max-height:270px;margin-top:.7rem;border:1px solid #e2e8f0;border-radius:12px}.mia-table{width:100%;border-collapse:collapse;font-size:.76rem}.mia-table th,.mia-table td{padding:.5rem .65rem;text-align:left;border-bottom:1px solid #eef2f7}.mia-table th{position:sticky;top:0;background:#f8fafc;color:#64748b;text-transform:uppercase;font-size:.62rem;letter-spacing:.05em}.mia-table td:last-child,.mia-table th:last-child{text-align:right;font-weight:800}
    .mia-compose{padding:.85rem;border-top:1px solid #e2e8f0;background:#fff}.mia-form{display:flex;gap:.55rem}.mia-input{flex:1;border:1px solid #cbd5e1;border-radius:13px;padding:.78rem .9rem;font:inherit;color:#0f172a;outline:none}.mia-input:focus{border-color:#4f46e5;box-shadow:0 0 0 3px #eef2ff}.mia-send{border:0;border-radius:13px;padding:.75rem 1.1rem;background:#0f766e;color:#fff;font-weight:800;cursor:pointer}.mia-send:disabled{opacity:.55;cursor:wait}.mia-disclaimer{margin:.45rem .2rem 0;color:#94a3b8;font-size:.66rem}
    .mia-side{display:flex;flex-direction:column;gap:1rem}.mia-side-card{padding:1rem}.mia-side-card h3{margin:0 0 .7rem;color:#0f172a;font-size:.9rem}.mia-prompts{display:flex;flex-direction:column;gap:.5rem}.mia-prompt{width:100%;text-align:left;border:1px solid #dbe4ee;border-radius:11px;background:#fff;padding:.65rem;color:#334155;font-size:.75rem;line-height:1.35;cursor:pointer}.mia-prompt:hover{border-color:#818cf8;background:#eef2ff}.mia-safety{margin:0;padding-left:1rem;color:#64748b;font-size:.73rem;line-height:1.6}.mia-typing{display:flex;gap:.2rem;padding:.25rem}.mia-typing span{width:6px;height:6px;border-radius:50%;background:#94a3b8;animation:miaPulse 1s infinite alternate}.mia-typing span:nth-child(2){animation-delay:.2s}.mia-typing span:nth-child(3){animation-delay:.4s}@keyframes miaPulse{to{opacity:.25;transform:translateY(-2px)}}
    @media(max-width:850px){.mia-shell{grid-template-columns:1fr}.mia-side{order:-1}.mia-prompts{display:grid;grid-template-columns:1fr 1fr}.mia-chat{height:52vh}}
    @media(max-width:540px){.mia-prompts{grid-template-columns:1fr}.mia-form{flex-direction:column}.mia-send{width:100%}.mia-bubble{max-width:94%}.mia-hero{align-items:flex-start}}
</style>
@endpush

@section('content')
<div class="mia-shell">
    <section class="mia-card" aria-label="MIS assistant chat">
        <header class="mia-hero">
            <div><h2>Ask about programme data</h2><p>Ask in Hindi, English or Hinglish · answers are always in English</p></div>
            <span class="mia-live">● READ ONLY</span>
        </header>
        <div class="mia-chat" id="miaChat" aria-live="polite">
            <div class="mia-msg">
                <div class="mia-avatar">AI</div>
                <div class="mia-bubble">Hello! Ask me about CFA applications, onboarding, services, market linkages or staff data. You can include a district, financial year, month, or request a date-wise or district-wise breakdown.</div>
            </div>
        </div>
        <div class="mia-compose">
            <form class="mia-form" id="miaForm">
                @csrf
                <input class="mia-input" id="miaQuestion" name="question" maxlength="500" autocomplete="off" placeholder="Example: Show the district-wise CFA count for August 2026" aria-label="Ask a data question">
                <button class="mia-send" id="miaSend" type="submit">Ask MIS</button>
            </form>
            <p class="mia-disclaimer">Answers are generated from read-only MIS queries. Always use the matching-records link for audit.</p>
        </div>
    </section>
    <aside class="mia-side">
        <section class="mia-card mia-side-card">
            <h3>Try a question</h3>
            <div class="mia-prompts">
                <button class="mia-prompt" type="button">How many incubatees were onboarded in FY 2026-27?</button>
                <button class="mia-prompt" type="button">Show the district-wise CFA count for August 2026</button>
                <button class="mia-prompt" type="button">Show the service-wise breakdown of pending approvals</button>
                <button class="mia-prompt" type="button">Show online market linkages by district</button>
                <button class="mia-prompt" type="button">How many CFA forms were received today?</button>
            </div>
        </section>
        <section class="mia-card mia-side-card">
            <h3>Built for reliable reporting</h3>
            <ul class="mia-safety"><li>State Admin access only</li><li>No database write permission</li><li>Applied scope shown with answer</li><li>Matching source records available</li><li>Unsupported answer is never guessed</li></ul>
        </section>
    </aside>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('miaForm');
    const input = document.getElementById('miaQuestion');
    const chat = document.getElementById('miaChat');
    const send = document.getElementById('miaSend');
    const endpoint = @json(route('admin.mis-assistant.ask'));
    const token = form.querySelector('input[name="_token"]').value;

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const scroll = () => { chat.scrollTop = chat.scrollHeight; };
    const userMessage = text => {
        chat.insertAdjacentHTML('beforeend', `<div class="mia-msg mia-msg--user"><div class="mia-bubble">${escapeHtml(text)}</div></div>`); scroll();
    };
    const typing = () => {
        const id = `typing-${Date.now()}`;
        chat.insertAdjacentHTML('beforeend', `<div class="mia-msg" id="${id}"><div class="mia-avatar">AI</div><div class="mia-bubble"><div class="mia-typing"><span></span><span></span><span></span></div></div></div>`); scroll(); return id;
    };
    const assistantMessage = data => {
        const pills = (data.filters || []).map(item => `<span class="mia-pill">${escapeHtml(item)}</span>`).join('');
        const rows = (data.rows || []).map(row => `<tr><td>${escapeHtml(row.label)}</td><td>${Number(row.count || 0).toLocaleString('en-IN')}</td></tr>`).join('');
        const table = rows ? `<div class="mia-table-wrap"><table class="mia-table"><thead><tr><th>${escapeHtml(data.group_label || 'Breakup')}</th><th>Count</th></tr></thead><tbody>${rows}</tbody></table></div>` : '';
        const source = data.source_url ? `<a class="mia-source" href="${escapeHtml(data.source_url)}">${escapeHtml(data.source_label || 'View records')} →</a>` : '';
        chat.insertAdjacentHTML('beforeend', `<div class="mia-msg"><div class="mia-avatar">AI</div><div class="mia-bubble mia-answer"><strong>${escapeHtml(data.answer)}</strong>${pills ? `<div class="mia-meta">${pills}</div>` : ''}${table}${source}<div class="mia-note">${escapeHtml(data.note || '')}</div></div></div>`); scroll();
    };
    const ask = async question => {
        question = question.trim(); if (!question) return;
        userMessage(question); input.value = ''; send.disabled = true; const typingId = typing();
        try {
            const response = await fetch(endpoint, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token}, body:JSON.stringify({question})});
            const data = await response.json();
            document.getElementById(typingId)?.remove();
            if (!response.ok) throw new Error(data.message || 'Could not read MIS data.');
            assistantMessage(data);
        } catch (error) {
            document.getElementById(typingId)?.remove();
            assistantMessage({answer:error.message || 'Something went wrong.', rows:[], note:'Please try again.'});
        } finally { send.disabled = false; input.focus(); }
    };
    form.addEventListener('submit', event => { event.preventDefault(); ask(input.value); });
    document.querySelectorAll('.mia-prompt').forEach(button => button.addEventListener('click', () => ask(button.textContent)));
})();
</script>
@endpush

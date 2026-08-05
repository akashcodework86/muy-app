@php
    $announcementUser = auth()->user();
    $showShortlistAnnouncement = session('show_case_study_shortlist_announcement')
        && $announcementUser
        && in_array($announcementUser->role, ['district_staff', 'hub_admin', 'state_admin'], true);
    $shortlistAnnouncementRoute = match ($announcementUser?->role) {
        'state_admin' => 'admin.case-study-shortlists.index',
        'hub_admin' => 'hub.case-study-shortlists.index',
        'district_staff' => 'staff.case-study-shortlists.index',
        default => null,
    };
@endphp

@if($showShortlistAnnouncement && $shortlistAnnouncementRoute && \Illuminate\Support\Facades\Route::has($shortlistAnnouncementRoute))
<style>
    .csa-overlay{position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.38);backdrop-filter:blur(2px);animation:csaFadeIn .22s ease-out both}.csa-panel{position:absolute;right:24px;top:92px;width:min(520px,calc(100vw - 32px));box-sizing:border-box;background:#fff;border:1px solid #dbe4f0;border-radius:20px;box-shadow:0 24px 70px rgba(15,23,42,.28);overflow:hidden;animation:csaSlideIn .48s cubic-bezier(.16,1,.3,1) both}.csa-accent{height:5px;background:linear-gradient(90deg,#4f46e5,#7c3aed,#0ea5e9)}.csa-body{padding:1.25rem 1.35rem 1.35rem}.csa-top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem}.csa-kicker{margin:0 0 .3rem;color:#4f46e5;font-size:.72rem;font-weight:850;text-transform:uppercase;letter-spacing:.08em}.csa-title{margin:0;color:#0f172a;font-size:1.3rem;line-height:1.25}.csa-close{border:0;background:#f1f5f9;color:#475569;width:34px;height:34px;border-radius:50%;font-size:1.25rem;line-height:1;cursor:pointer}.csa-toggle{display:inline-flex;background:#f1f5f9;border-radius:10px;padding:3px;margin:.95rem 0 .75rem}.csa-toggle button{border:0;background:transparent;color:#64748b;padding:.38rem .7rem;border-radius:8px;font:inherit;font-size:.78rem;font-weight:800;cursor:pointer}.csa-toggle button.is-active{background:#fff;color:#4338ca;box-shadow:0 1px 4px rgba(15,23,42,.14)}.csa-copy{color:#475569;font-size:.9rem;line-height:1.62}.csa-copy p{margin:.4rem 0}.csa-copy strong{color:#172033}.csa-actions{display:flex;flex-wrap:wrap;gap:.6rem;margin-top:1.05rem}.csa-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:0;border-radius:10px;padding:.68rem 1rem;font:inherit;font-size:.84rem;font-weight:850;cursor:pointer}.csa-btn--primary{background:#4f46e5;color:#fff}.csa-btn--secondary{background:#fff;color:#334155;border:1px solid #cbd5e1}@keyframes csaFadeIn{from{opacity:0}to{opacity:1}}@keyframes csaSlideIn{from{opacity:0;transform:translateX(calc(100% + 48px))}to{opacity:1;transform:translateX(0)}}@keyframes csaSlideOut{to{opacity:0;transform:translateX(calc(100% + 48px))}}.csa-overlay.is-closing{animation:csaFadeIn .2s ease-in reverse both}.csa-overlay.is-closing .csa-panel{animation:csaSlideOut .2s ease-in both}@media(max-width:640px){.csa-panel{position:absolute;top:auto;right:12px;bottom:12px;width:calc(100vw - 24px);max-height:calc(100vh - 24px);overflow:auto}.csa-body{padding:1rem}.csa-title{font-size:1.12rem}@keyframes csaSlideIn{from{opacity:0;transform:translateY(calc(100% + 32px))}to{opacity:1;transform:translateY(0)}}@keyframes csaSlideOut{to{opacity:0;transform:translateY(calc(100% + 32px))}}}@media(prefers-reduced-motion:reduce){.csa-overlay,.csa-panel{animation:none!important}}
</style>

<div class="csa-overlay" id="case-study-announcement" role="presentation">
    <section class="csa-panel" role="dialog" aria-modal="true" aria-labelledby="csa-title-en">
        <div class="csa-accent"></div>
        <div class="csa-body">
            <div class="csa-top">
                <div>
                    <p class="csa-kicker">New module · नया मॉड्यूल</p>
                    <h2 class="csa-title" id="csa-title-en" data-csa-panel="en">New: Monthly Case Study Shortlist</h2>
                    <h2 class="csa-title" id="csa-title-hi" data-csa-panel="hi" hidden>नया मॉड्यूल: मासिक केस स्टडी शॉर्टलिस्ट</h2>
                </div>
                <button type="button" class="csa-close" data-csa-close aria-label="Close announcement">×</button>
            </div>

            <div class="csa-toggle" role="group" aria-label="Announcement language">
                <button type="button" class="is-active" data-csa-lang="en">English</button>
                <button type="button" data-csa-lang="hi">हिंदी</button>
            </div>

            <div class="csa-copy" data-csa-panel="en">
                <p>The Monthly Case Study Shortlist module is now available.</p>
                <p>District staff can shortlist up to <strong>5 onboarded incubatees every month</strong> for potential case studies. Incubatees can be searched and filtered by programme year, name, application number, phone number, block, gender, business stage and business category.</p>
                <p>Each incubatee can be shortlisted only once. Hub and State Admins can view the shortlisted incubatees and add remarks.</p>
            </div>
            <div class="csa-copy" data-csa-panel="hi" hidden>
                <p>मासिक केस स्टडी शॉर्टलिस्ट मॉड्यूल अब उपलब्ध है।</p>
                <p>जिला स्टाफ संभावित केस स्टडी के लिए हर महीने अधिकतम <strong>5 ऑनबोर्डेड इनक्यूबेटी</strong> शॉर्टलिस्ट कर सकता है। इनक्यूबेटी को कार्यक्रम वर्ष, नाम, आवेदन संख्या, फोन नंबर, ब्लॉक, लिंग, व्यवसाय चरण और व्यवसाय श्रेणी के आधार पर खोजा और फ़िल्टर किया जा सकता है।</p>
                <p>प्रत्येक इनक्यूबेटी को केवल एक बार शॉर्टलिस्ट किया जा सकता है। हब और स्टेट एडमिन शॉर्टलिस्ट किए गए इनक्यूबेटी देख सकते हैं और अपनी टिप्पणी जोड़ सकते हैं।</p>
            </div>

            <div class="csa-actions" data-csa-panel="en">
                <a href="{{ route($shortlistAnnouncementRoute) }}" class="csa-btn csa-btn--primary">Open Monthly Shortlist</a>
                <button type="button" class="csa-btn csa-btn--secondary" data-csa-close>Got it</button>
            </div>
            <div class="csa-actions" data-csa-panel="hi" hidden>
                <a href="{{ route($shortlistAnnouncementRoute) }}" class="csa-btn csa-btn--primary">मासिक शॉर्टलिस्ट खोलें</a>
                <button type="button" class="csa-btn csa-btn--secondary" data-csa-close>समझ गया</button>
            </div>
        </div>
    </section>
</div>

<script>
(() => {
    const overlay = document.getElementById('case-study-announcement');
    if (!overlay) return;
    const showLanguage = (language) => {
        overlay.querySelectorAll('[data-csa-panel]').forEach((panel) => { panel.hidden = panel.dataset.csaPanel !== language; });
        overlay.querySelectorAll('[data-csa-lang]').forEach((button) => { button.classList.toggle('is-active', button.dataset.csaLang === language); });
        overlay.querySelector('.csa-panel')?.setAttribute('aria-labelledby', language === 'hi' ? 'csa-title-hi' : 'csa-title-en');
    };
    const close = () => {
        if (overlay.classList.contains('is-closing')) return;
        overlay.classList.add('is-closing');
        window.setTimeout(() => overlay.remove(), 210);
    };
    overlay.querySelectorAll('[data-csa-lang]').forEach((button) => button.addEventListener('click', () => showLanguage(button.dataset.csaLang)));
    overlay.querySelectorAll('[data-csa-close]').forEach((button) => button.addEventListener('click', close));
    overlay.addEventListener('click', (event) => { if (event.target === overlay) close(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); }, {once:true});
    overlay.querySelector('[data-csa-close]')?.focus();
})();
</script>
@endif

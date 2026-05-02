@php
    $isGeneral = ($active ?? '') === 'general';
    $isServices = ($active ?? '') === 'services';
    $pill = function (bool $on): string {
        return $on
            ? 'background:#4f46e5;color:#fff;border-color:#4338ca;font-weight:600;'
            : 'background:#fff;color:#3f3f46;border-color:#e4e4e7;font-weight:500;';
    };
@endphp
<nav aria-label="Service module settings sections" style="display:flex; gap:0.4rem; margin:0 0 1rem; flex-wrap:wrap; max-width:55rem;">
    <a href="{{ route('admin.service-module-settings.edit') }}"
       style="display:inline-block; padding:0.45rem 0.85rem; border-radius:8px; font-size:0.88rem; text-decoration:none; border:1px solid; {{ $pill($isGeneral) }}">
        General
    </a>
    <a href="{{ route('admin.service-module-settings.services') }}"
       style="display:inline-block; padding:0.45rem 0.85rem; border-radius:8px; font-size:0.88rem; text-decoration:none; border:1px solid; {{ $pill($isServices) }}">
        Per-service availability
    </a>
</nav>

@php
    $caseModel = $case ?? null;
    $isThroughReap = $caseModel instanceof \App\Models\ServiceCase
        && $caseModel->displaysReapSupportRoute();
    $reapDocs = $isThroughReap ? $caseModel->reapAttachments() : collect();
    $convergenceDocs = $isThroughReap ? $caseModel->convergenceAttachments() : ($caseModel?->attachments ?? collect());
    $attachmentRoute = $attachmentRoute ?? 'spoc.service-cases.attachments.download';
    $docButtonClass = $docButtonClass ?? 'spoc-doc-btn js-doc-open';
@endphp

@if ($isThroughReap)
    @if ($reapDocs->isNotEmpty())
        <div style="background:linear-gradient(180deg,#fff7ed 0%,#fffbeb 100%);border:1px solid #fdba74;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;box-shadow:0 8px 22px -18px rgba(234,88,12,0.35);">
            <h3 style="margin:0 0 0.65rem;font-size:0.95rem;color:#9a3412;">REAP document</h3>
            <ul style="margin:0;padding-left:1.1rem;font-size:0.85rem;">
                @foreach ($reapDocs as $att)
                    <li style="margin-bottom:0.35rem;">
                        <button
                            type="button"
                            class="{{ $docButtonClass }}"
                            data-doc-url="{{ route($attachmentRoute, [$caseModel, $att]) }}"
                            data-doc-name="{{ $att->original_name }}"
                            data-case-id="{{ (int) $caseModel->id }}"
                        >
                            View REAP document
                        </button>
                        <span style="margin-left:0.3rem;">{{ $att->original_name }}</span>
                        <span style="color:#71717a;">({{ number_format((int) ($att->size_bytes / 1024), 0) }} KB)</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($convergenceDocs->isNotEmpty())
        <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
            <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Convergence documents</h3>
            <ul style="margin:0;padding-left:1.1rem;font-size:0.85rem;">
                @foreach ($convergenceDocs as $att)
                    <li style="margin-bottom:0.35rem;">
                        <button
                            type="button"
                            class="{{ $docButtonClass }}"
                            data-doc-url="{{ route($attachmentRoute, [$caseModel, $att]) }}"
                            data-doc-name="{{ $att->original_name }}"
                            data-case-id="{{ (int) $caseModel->id }}"
                        >
                            View document
                        </button>
                        <span style="margin-left:0.3rem;">{{ $att->original_name }}</span>
                        <span style="color:#71717a;">({{ number_format((int) ($att->size_bytes / 1024), 0) }} KB)</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@elseif ($caseModel instanceof \App\Models\ServiceCase && $caseModel->attachments->isNotEmpty())
    <div style="background:#fff;border:1px solid #e4e4e7;border-radius:12px;padding:1rem 1.1rem;max-width:52rem;margin-bottom:1rem;">
        <h3 style="margin:0 0 0.65rem;font-size:0.95rem;">Attachments</h3>
        <ul style="margin:0;padding-left:1.1rem;font-size:0.85rem;">
            @foreach ($caseModel->attachments as $att)
                <li style="margin-bottom:0.35rem;">
                    <button
                        type="button"
                        class="{{ $docButtonClass }}"
                        data-doc-url="{{ route($attachmentRoute, [$caseModel, $att]) }}"
                        data-doc-name="{{ $att->original_name }}"
                        data-case-id="{{ (int) $caseModel->id }}"
                    >
                        View document
                    </button>
                    <span style="margin-left:0.3rem;">{{ $att->original_name }}</span>
                    <span style="color:#71717a;">({{ number_format((int) ($att->size_bytes / 1024), 0) }} KB)</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif

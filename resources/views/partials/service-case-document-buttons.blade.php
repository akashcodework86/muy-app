@php
    $caseModel = $case ?? null;
    $isThroughReap = $caseModel instanceof \App\Models\ServiceCase
        && $caseModel->displaysReapSupportRoute();
    $reapDocs = $isThroughReap ? $caseModel->reapAttachments() : collect();
    $convergenceDocs = $isThroughReap ? $caseModel->convergenceAttachments() : collect();
    $attachmentRoute = $attachmentRoute ?? 'spoc.service-cases.attachments.download';
    $docButtonClass = $docButtonClass ?? 'sq-btn sq-btn--doc js-doc-open';
    $reapButtonClass = $reapButtonClass ?? 'sq-btn sq-btn--reap js-doc-open';
@endphp

@if ($isThroughReap)
    @foreach ($reapDocs as $doc)
        <button
            type="button"
            class="{{ $reapButtonClass }}"
            data-doc-url="{{ route($attachmentRoute, [$caseModel, $doc]) }}"
            data-doc-name="{{ $doc->original_name }}"
            data-case-id="{{ (int) $caseModel->id }}"
            title="{{ $doc->original_name }}"
        >REAP document</button>
    @endforeach
    @foreach ($convergenceDocs as $doc)
        <button
            type="button"
            class="{{ $docButtonClass }}"
            data-doc-url="{{ route($attachmentRoute, [$caseModel, $doc]) }}"
            data-doc-name="{{ $doc->original_name }}"
            data-case-id="{{ (int) $caseModel->id }}"
            title="{{ $doc->original_name }}"
        >Convergence document</button>
    @endforeach
@elseif ($caseModel instanceof \App\Models\ServiceCase && $caseModel->attachments->isNotEmpty())
    @php $doc = $caseModel->attachments->first(); @endphp
    <button
        type="button"
        class="{{ $docButtonClass }}"
        data-doc-url="{{ route($attachmentRoute, [$caseModel, $doc]) }}"
        data-doc-name="{{ $doc->original_name }}"
        data-case-id="{{ (int) $caseModel->id }}"
    >View document</button>
@endif

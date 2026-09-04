@if (!empty($session?->proof_path))
    @php
        $proofInlineUrl = route($proofRoute, ['mentorshipSession' => $session, 'inline' => 1]);
        $sizeClass = !empty($large) ? 'mr-proof-thumb--lg' : '';
    @endphp
    <a class="mr-proof-thumb {{ $sizeClass }}" href="{{ $proofInlineUrl }}" target="_blank" rel="noopener" title="View meeting screenshot">
        <img src="{{ $proofInlineUrl }}" alt="Meeting screenshot">
    </a>
@endif

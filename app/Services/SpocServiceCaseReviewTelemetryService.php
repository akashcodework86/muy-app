<?php

namespace App\Services;

use App\Models\ServiceCase;
use Illuminate\Support\Facades\Cache;

class SpocServiceCaseReviewTelemetryService
{
    private const TTL_HOURS = 12;

    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return [
            'document_viewed_at' => null,
            'document_view_source' => null,
            'full_page_visited_at' => null,
            'quick_review_opened_at' => null,
            'accumulated_seconds' => 0,
            'last_event_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public function merge(int $spocUserId, int $serviceCaseId, array $patch): array
    {
        $key = $this->cacheKey($spocUserId, $serviceCaseId);
        $state = array_merge($this->defaultState(), Cache::get($key, []), $patch);
        $state['last_event_at'] = now()->toIso8601String();
        Cache::put($key, $state, now()->addHours(self::TTL_HOURS));

        return $state;
    }

    public function markDocumentViewed(int $spocUserId, int $serviceCaseId, string $source): array
    {
        $existing = $this->get($spocUserId, $serviceCaseId);

        if (! empty($existing['document_viewed_at'])) {
            return $existing;
        }

        return $this->merge($spocUserId, $serviceCaseId, [
            'document_viewed_at' => now()->toIso8601String(),
            'document_view_source' => $source,
        ]);
    }

    public function markFullPageVisited(int $spocUserId, int $serviceCaseId): array
    {
        $existing = $this->get($spocUserId, $serviceCaseId);
        if (! empty($existing['full_page_visited_at'])) {
            return $existing;
        }

        return $this->merge($spocUserId, $serviceCaseId, [
            'full_page_visited_at' => now()->toIso8601String(),
        ]);
    }

    public function markQuickReviewOpened(int $spocUserId, int $serviceCaseId): array
    {
        $existing = $this->get($spocUserId, $serviceCaseId);
        if (! empty($existing['quick_review_opened_at'])) {
            return $existing;
        }

        return $this->merge($spocUserId, $serviceCaseId, [
            'quick_review_opened_at' => now()->toIso8601String(),
        ]);
    }

    public function addReviewSeconds(int $spocUserId, int $serviceCaseId, int $seconds): array
    {
        $seconds = max(0, min($seconds, 7200));
        if ($seconds === 0) {
            return $this->get($spocUserId, $serviceCaseId);
        }

        $existing = $this->get($spocUserId, $serviceCaseId);

        return $this->merge($spocUserId, $serviceCaseId, [
            'accumulated_seconds' => (int) ($existing['accumulated_seconds'] ?? 0) + $seconds,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $spocUserId, int $serviceCaseId): array
    {
        return array_merge(
            $this->defaultState(),
            Cache::get($this->cacheKey($spocUserId, $serviceCaseId), [])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotForApproval(
        ServiceCase $serviceCase,
        int $spocUserId,
        string $approvalChannel,
        int $clientReviewSeconds = 0,
    ): array {
        if ($clientReviewSeconds > 0) {
            $this->addReviewSeconds($spocUserId, (int) $serviceCase->id, $clientReviewSeconds);
        }

        $state = $this->get($spocUserId, (int) $serviceCase->id);
        $serviceCase->loadMissing('attachments');
        $hasAttachment = $serviceCase->attachments->isNotEmpty();
        $documentViewed = ! empty($state['document_viewed_at']);

        return [
            'had_attachment' => $hasAttachment,
            'document_viewed' => $documentViewed,
            'document_viewed_at' => $state['document_viewed_at'],
            'document_view_source' => $state['document_view_source'],
            'full_page_visited' => ! empty($state['full_page_visited_at']),
            'full_page_visited_at' => $state['full_page_visited_at'],
            'quick_review_opened' => ! empty($state['quick_review_opened_at']),
            'quick_review_opened_at' => $state['quick_review_opened_at'],
            'case_page_seconds' => (int) ($state['accumulated_seconds'] ?? 0),
            'approved_without_document_view' => $hasAttachment && ! $documentViewed,
            'approval_channel' => $approvalChannel,
        ];
    }

    public function clear(int $spocUserId, int $serviceCaseId): void
    {
        Cache::forget($this->cacheKey($spocUserId, $serviceCaseId));
    }

    private function cacheKey(int $spocUserId, int $serviceCaseId): string
    {
        return 'spoc_case_review:'.$spocUserId.':'.$serviceCaseId;
    }
}

<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\ServiceCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class HubFieldActivityHighlightsService
{
    /**
     * Random approved photos belonging to the selected hub.
     *
     * @param  list<int>  $districtIds
     * @return list<array{module: string, title: string, district: string, date: string, image_url: string}>
     */
    public function forHub(
        int $hubId,
        array $districtIds,
        ?FiscalYear $activeFy,
        Carbon $phase3FloorDate,
    ): array {
        if ($hubId <= 0) {
            return [];
        }

        $from = $activeFy?->starts_on
            ? Carbon::parse($activeFy->starts_on)->startOfDay()
            : $phase3FloorDate->copy()->startOfDay();
        $fyEnd = $activeFy?->ends_on
            ? Carbon::parse($activeFy->ends_on)->endOfDay()
            : now()->endOfDay();
        $to = $fyEnd->min(now()->endOfDay());

        $sources = [
            [
                'key' => 'technical_training',
                'table' => 'technical_trainings',
                'date' => 'event_date',
                'title' => 'session_name',
                'module' => 'Technical Training',
                'collections' => ['attendance_media_json'],
            ],
            [
                'key' => 'lakhpati_technical_training',
                'table' => 'potential_lakhpati_technical_trainings',
                'date' => 'session_date',
                'title' => 'session_title',
                'module' => 'Lakhpati Technical Training',
                'collections' => ['workshop_photos_json'],
            ],
            [
                'key' => 'line_department_meeting',
                'table' => 'line_department_meetings',
                'date' => 'meeting_date',
                'title' => 'department_name',
                'module' => 'Line Department Meeting',
                'collections' => ['proof_media_json', 'photos_json'],
            ],
            [
                'key' => 'community_org_outreach',
                'table' => 'community_organization_outreach_visits',
                'date' => 'visit_date',
                'title' => 'organization_name',
                'module' => 'Community Outreach',
                'collections' => ['photos_json'],
            ],
        ];

        $highlights = collect();

        foreach ($sources as $source) {
            $table = $source['table'];
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'status')
                || ! Schema::hasColumn($table, $source['date'])) {
                continue;
            }

            $columns = array_values(array_unique(array_filter([
                'id', 'hub_id', 'district_id', 'district_name', $source['date'], $source['title'], ...$source['collections'],
            ], fn (string $column): bool => Schema::hasColumn($table, $column))));

            try {
                $query = DB::table($table)
                    ->where('status', ServiceCase::STATUS_APPROVED)
                    ->whereDate($source['date'], '>=', $from->toDateString())
                    ->whereDate($source['date'], '<=', $to->toDateString())
                    ->where(function ($scope) use ($table, $hubId, $districtIds): void {
                        $hasScope = false;
                        if (Schema::hasColumn($table, 'hub_id')) {
                            $scope->where('hub_id', $hubId);
                            $hasScope = true;
                        }
                        if ($districtIds !== [] && Schema::hasColumn($table, 'district_id')) {
                            $method = $hasScope ? 'orWhereIn' : 'whereIn';
                            $scope->{$method}('district_id', $districtIds);
                            $hasScope = true;
                        }
                        if (! $hasScope) {
                            $scope->whereRaw('1 = 0');
                        }
                    })
                    ->inRandomOrder()
                    ->limit(75);

                $rows = $query->get($columns);
            } catch (\Throwable) {
                continue;
            }

            $sourcePhotoCount = 0;
            foreach ($rows as $row) {
                foreach ($source['collections'] as $collection) {
                    $items = $this->decodeMediaList($row->{$collection} ?? null);
                    foreach ($items as $index => $item) {
                        if (! $this->isBrowserImage($item)) {
                            continue;
                        }

                        $path = trim((string) ($item['path'] ?? ''));
                        if ($path === '' || ! Storage::exists($path)) {
                            continue;
                        }

                        $activityDate = Carbon::parse($row->{$source['date']});
                        $highlights->push([
                            'module' => $source['module'],
                            'title' => trim((string) ($row->{$source['title']} ?? '')) ?: $source['module'],
                            'district' => trim((string) ($row->district_name ?? '')) ?: $this->hubFallbackLabel($row, $hubId),
                            'date' => $activityDate->format('d M Y'),
                            'image_url' => route('hub.field-highlights.image', [
                                'module' => $source['key'],
                                'record' => (int) $row->id,
                                'collection' => $collection,
                                'index' => (int) $index,
                            ]),
                        ]);
                        $sourcePhotoCount++;
                        if ($sourcePhotoCount >= 24) {
                            break 3;
                        }
                    }
                }
            }
        }

        return $highlights->shuffle()->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function decodeMediaList(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return collect(is_array($value) ? $value : [])
            ->filter(fn ($item): bool => is_array($item))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $item */
    private function isBrowserImage(array $item): bool
    {
        $mime = strtolower(trim((string) ($item['mime'] ?? '')));
        if (str_starts_with($mime, 'image/')) {
            return ! in_array($mime, ['image/heic', 'image/heif'], true);
        }

        $name = strtolower((string) ($item['original_name'] ?? $item['path'] ?? ''));

        return (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', $name);
    }

    private function hubFallbackLabel(object $row, int $hubId): string
    {
        return (int) ($row->hub_id ?? 0) === $hubId ? 'Hub-level activity' : 'Hub district';
    }
}

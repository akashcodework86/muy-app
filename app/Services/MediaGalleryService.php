<?php

namespace App\Services;

use App\Models\ServiceCase;
use App\Support\LineDepartmentMeetingOptions;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MediaGalleryService
{
    /** @var array<int, string> */
    private array $districtNameCache = [];

    /**
     * Select a small, approved/visible set of field photographs for an automated
     * monthly report. The same availability and workflow-status rules as the
     * Media Gallery are used, so the report never bypasses gallery approvals.
     *
     * @return list<array{section: string, title: string, district: string, date: string, path: string}>
     */
    public function monthlyReportHighlights(Carbon $from, Carbon $to, int $limit = 8): array
    {
        $highlights = [];
        $filters = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];

        foreach ($this->sections() as $sectionKey => $section) {
            if (count($highlights) >= $limit || ! $this->sectionIsAvailable($section)) {
                continue;
            }

            try {
                $rows = $this->albumRows($section, $filters, 2);
            } catch (\Throwable) {
                continue;
            }

            foreach ($rows as $row) {
                if (count($highlights) >= $limit) {
                    break 2;
                }

                $photo = $this->photosFromRow($section, $row, $sectionKey)[0] ?? null;
                if (! is_array($photo)) {
                    continue;
                }

                $item = $this->resolveMediaItem(
                    $sectionKey,
                    (int) $row->id,
                    (string) $photo['collection'],
                    (int) $photo['index'],
                );
                if ($item === null) {
                    continue;
                }

                $absolutePath = Storage::disk($item['disk'])->path($item['path']);
                $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp'], true)) {
                    continue;
                }

                $date = $row->{$section['date']} ?? null;
                $highlights[] = [
                    'section' => (string) $section['label'],
                    'title' => $this->rowTitle($section, $row),
                    'district' => $this->rowDistrict($section, $row),
                    'date' => $date ? Carbon::parse($date)->format('d M Y') : '—',
                    'path' => $absolutePath,
                ];
            }
        }

        return $highlights;
    }

    /**
     * Approved field photographs grouped by Media Gallery section for QPR-style
     * field visit pages.
     *
     * @return array<string, list<array{section: string, title: string, district: string, date: string, path: string}>>
     */
    public function monthlyReportPhotosBySection(Carbon $from, Carbon $to, int $perSection = 4): array
    {
        $grouped = [];
        $filters = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];

        foreach ($this->sections() as $sectionKey => $section) {
            if (! $this->sectionIsAvailable($section)) {
                continue;
            }

            try {
                $rows = $this->albumRows($section, $filters, $perSection);
            } catch (\Throwable) {
                continue;
            }

            foreach ($rows as $row) {
                $photo = $this->photosFromRow($section, $row, $sectionKey)[0] ?? null;
                if (! is_array($photo)) {
                    continue;
                }

                $item = $this->resolveMediaItem(
                    $sectionKey,
                    (int) $row->id,
                    (string) $photo['collection'],
                    (int) $photo['index'],
                );
                if ($item === null) {
                    continue;
                }

                $absolutePath = Storage::disk($item['disk'])->path($item['path']);
                $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp'], true)) {
                    continue;
                }

                $label = (string) $section['label'];
                $date = $row->{$section['date']} ?? null;
                $grouped[$label][] = [
                    'section' => $label,
                    'title' => $this->rowTitle($section, $row),
                    'district' => $this->rowDistrict($section, $row),
                    'date' => $date ? Carbon::parse($date)->format('d M Y') : '—',
                    'path' => $absolutePath,
                ];

                if (count($grouped[$label]) >= $perSection) {
                    break;
                }
            }
        }

        return $grouped;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sections(): array
    {
        return [
            'block-workshops' => [
                'label' => 'Block Workshops',
                'description' => 'Photos from block-level field workshops',
                'table' => 'block_workshops',
                'date' => 'visit_date',
                'title' => 'block',
                'title_fallback' => 'Block workshop',
                'collections' => ['visit_media_json'],
                'images_only' => true,
                'district_id' => 'district_id',
                'district_name' => null,
                'status' => 'submitted_or_null',
                'detail_route' => 'admin.block-workshops.show',
                'detail_param' => 'blockWorkshop',
                'context_fields' => ['area'],
                'briefing_fields' => ['remark'],
            ],
            'district-workshops' => [
                'label' => 'District Workshops',
                'description' => 'District workshop session photos',
                'table' => 'district_workshop_sessions',
                'date' => 'event_date',
                'title' => 'topic',
                'title_fallback' => 'District workshop',
                'collections' => ['workshop_photos_json'],
                'images_only' => true,
                'district_id' => 'district_id',
                'district_name' => 'district_name',
                'status' => null,
                'detail_route' => 'admin.district-workshop-sessions.show',
                'detail_param' => 'districtWorkshopSession',
                'context_fields' => ['workshop_mode'],
                'briefing_fields' => ['notes'],
            ],
            'demo-days' => [
                'label' => 'Demo Days',
                'description' => 'Demo day event photos',
                'table' => 'demo_days',
                'date' => 'event_date',
                'title' => 'event_name',
                'title_fallback' => 'Demo day',
                'collections' => ['event_photos_json'],
                'images_only' => true,
                'district_id' => 'district_id',
                'district_name' => null,
                'status' => null,
                'detail_route' => 'admin.demo-days.show',
                'detail_param' => 'demoDay',
                'context_fields' => ['event_type', 'venue'],
                'briefing_fields' => ['summary', 'remarks'],
            ],
            'eap-edp' => [
                'label' => 'EAP / EDP Sessions',
                'description' => 'Entrepreneurship session photos',
                'table' => 'eap_edp_sessions',
                'date' => 'event_date',
                'title' => 'topic',
                'title_fallback' => 'EAP/EDP session',
                'collections' => ['session_photos_json'],
                'images_only' => true,
                'district_id' => 'district_id',
                'district_name' => 'district_name',
                'status' => null,
                'detail_route' => 'admin.eap-edp-sessions.show',
                'detail_param' => 'eapEdpSession',
                'context_fields' => ['program_type', 'venue_name_address'],
                'briefing_fields' => ['notes'],
            ],
            'technical-trainings' => [
                'label' => 'Technical Trainings',
                'description' => 'Technical training session media',
                'table' => 'technical_trainings',
                'date' => 'event_date',
                'title' => 'session_name',
                'title_fallback' => 'Technical training',
                'collections' => ['attendance_media_json'],
                'images_only' => true,
                'district_id' => 'district_id',
                'district_name' => 'district_name',
                'status' => 'approved',
                'detail_route' => 'admin.technical-trainings.show',
                'detail_param' => 'technicalTraining',
                'context_fields' => ['training_batch_name'],
                'briefing_fields' => ['session_brief'],
            ],
            'lakhpati-trainings' => [
                'label' => 'Lakhpati Technical Trainings',
                'description' => 'Lakhpati technical training photos',
                'table' => 'potential_lakhpati_technical_trainings',
                'date' => 'session_date',
                'title' => 'session_title',
                'title_fallback' => 'Lakhpati training',
                'collections' => ['workshop_photos_json'],
                'images_only' => true,
                'district_id' => 'district_id',
                'district_name' => 'district_name',
                'status' => 'approved',
                'detail_route' => 'admin.lakhpati-technical-trainings.show',
                'detail_param' => 'lakhpatiTechnicalTraining',
                'context_fields' => ['block', 'area'],
                'briefing_fields' => ['session_brief'],
            ],
            'stakeholder-consultation' => [
                'label' => 'Stakeholder Consultation',
                'description' => 'Stakeholder consultation workshop photos',
                'table' => 'stakeholder_consultation_workshops',
                'date' => 'workshop_date',
                'title' => 'workshop_title',
                'title_fallback' => 'Stakeholder consultation',
                'collections' => ['workshop_photos_json'],
                'images_only' => true,
                'district_id' => 'district_id',
                'district_name' => 'district_name',
                'status' => null,
                'detail_route' => 'admin.stakeholder-consultation-workshops.show',
                'detail_param' => 'scwWorkshop',
                'context_fields' => ['consultation_theme', 'venue'],
                'briefing_fields' => ['key_outcomes'],
            ],
            'capacity-building' => [
                'label' => 'Capacity Building',
                'description' => 'Stakeholder capacity building photos',
                'table' => 'stakeholder_capacity_building_sessions',
                'date' => 'session_date',
                'title' => 'session_title',
                'title_fallback' => 'Capacity building',
                'collections' => ['workshop_photos_json'],
                'images_only' => true,
                'district_id' => null,
                'district_name' => null,
                'status' => null,
                'detail_route' => 'admin.capacity-building-stakeholders.show',
                'detail_param' => 'cbsSession',
                'context_fields' => ['stakeholder_type', 'venue'],
                'briefing_fields' => ['topics_covered'],
            ],
            'line-department-meetings' => [
                'label' => 'Line Department Meetings',
                'description' => 'Meeting and visit photos',
                'table' => 'line_department_meetings',
                'date' => 'meeting_date',
                'title' => 'department_name',
                'title_fallback' => 'Line department meeting',
                'collections' => ['photos_json', 'proof_media_json'],
                'images_only' => true,
                'district_id' => 'district_id',
                'district_name' => 'district_name',
                'status' => 'approved',
                'detail_route' => 'admin.line-department-meetings.show',
                'detail_param' => 'ldmMeeting',
                'context_fields' => ['meeting_purpose', 'venue'],
                'briefing_fields' => ['agenda_summary', 'agenda_remark_outcome'],
            ],
            'community-outreach' => [
                'label' => 'Community Outreach',
                'description' => 'Community organisation outreach photos',
                'table' => 'community_organization_outreach_visits',
                'date' => 'visit_date',
                'title' => 'organization_name',
                'title_fallback' => 'Community outreach',
                'collections' => ['photos_json'],
                'images_only' => true,
                'district_id' => 'district_id',
                'district_name' => 'district_name',
                'status' => 'approved',
                'detail_route' => 'admin.community-org-outreach.show',
                'detail_param' => 'communityOrgOutreach',
                'context_fields' => ['purpose', 'organization_type'],
                'briefing_fields' => ['remarks', 'outcome'],
            ],
        ];
    }

    public function section(string $key): ?array
    {
        $sections = $this->sections();

        return $sections[$key] ?? null;
    }

    /**
     * @param  array{hub?: int|null, district?: int|null, from?: string, to?: string, q?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function sectionSummaries(array $filters = []): array
    {
        $summaries = [];

        foreach ($this->sections() as $key => $section) {
            if (! $this->sectionIsAvailable($section)) {
                continue;
            }

            $albums = $this->albumRows($section, $filters, limit: 40);
            $photoCount = 0;
            $cover = null;
            $albumCount = 0;

            foreach ($albums as $row) {
                $photos = $this->photosFromRow($section, $row, $key);
                if ($photos === []) {
                    continue;
                }
                $albumCount++;
                $photoCount += count($photos);
                if ($cover === null) {
                    $cover = $photos[0];
                }
            }

            // Approximate album total when more than the sample window.
            $totalAlbums = $this->countAlbumsWithMedia($section, $filters);

            $summaries[] = [
                'key' => $key,
                'label' => $section['label'],
                'description' => $section['description'],
                'album_count' => $totalAlbums,
                'photo_count' => $photoCount,
                'photo_count_sampled' => $albums->count() >= 40,
                'cover' => $cover,
                'url' => route('admin.media-gallery.section', $key),
            ];
        }

        return $summaries;
    }

    /**
     * @param  array{hub?: int|null, district?: int|null, from?: string, to?: string, q?: string}  $filters
     */
    public function paginateAlbums(string $sectionKey, array $filters = [], int $perPage = 24): LengthAwarePaginator
    {
        $section = $this->section($sectionKey);
        abort_unless($section !== null && $this->sectionIsAvailable($section), 404);

        $query = $this->baseAlbumQuery($section, $filters);
        $dateCol = $section['date'];

        $paginator = $query
            ->orderByDesc($dateCol)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(function ($row) use ($section, $sectionKey) {
                $photos = $this->photosFromRow($section, $row, $sectionKey);
                $date = $row->{$section['date']} ?? null;

                return [
                    'id' => (int) $row->id,
                    'title' => $this->rowTitle($section, $row),
                    'district' => $this->rowDistrict($section, $row),
                    'date' => $date ? Carbon::parse($date)->format('d M Y') : '—',
                    'date_raw' => $date ? Carbon::parse($date)->toDateString() : null,
                    'description' => $this->rowContext($section, $row),
                    'briefing' => $this->rowBriefing($section, $row),
                    'photo_count' => count($photos),
                    'thumbs' => array_slice($photos, 0, 4),
                    'url' => route('admin.media-gallery.show', [$sectionKey, (int) $row->id]),
                    'detail_url' => route($section['detail_route'], [
                        $section['detail_param'] => (int) $row->id,
                    ]),
                ];
            })->filter(fn (array $album): bool => $album['photo_count'] > 0)->values()
        );

        return $paginator;
    }

    /**
     * @return array{album: array<string, mixed>, photos: list<array<string, mixed>>, section: array<string, mixed>}|null
     */
    public function album(string $sectionKey, int $recordId): ?array
    {
        $section = $this->section($sectionKey);
        if ($section === null || ! $this->sectionIsAvailable($section)) {
            return null;
        }

        $row = $this->findRow($section, $recordId);
        if ($row === null) {
            return null;
        }

        $photos = $this->photosFromRow($section, $row, $sectionKey);
        $date = $row->{$section['date']} ?? null;

        return [
            'section' => array_merge($section, ['key' => $sectionKey]),
            'album' => [
                'id' => (int) $row->id,
                'title' => $this->rowTitle($section, $row),
                'district' => $this->rowDistrict($section, $row),
                'date' => $date ? Carbon::parse($date)->format('d M Y') : '—',
                'description' => $this->rowContext($section, $row),
                'briefing' => $this->rowBriefing($section, $row),
                'detail_url' => route($section['detail_route'], [
                    $section['detail_param'] => (int) $row->id,
                ]),
            ],
            'photos' => $photos,
        ];
    }

    /**
     * @return array{path: string, disk: string, original_name: string, mime: string}|null
     */
    public function resolveMediaItem(string $sectionKey, int $recordId, string $collection, int $index): ?array
    {
        $section = $this->section($sectionKey);
        if ($section === null || ! $this->sectionIsAvailable($section)) {
            return null;
        }

        if (! in_array($collection, $section['collections'], true)) {
            return null;
        }

        $row = $this->findRow($section, $recordId);
        if ($row === null) {
            return null;
        }

        $items = $this->decodeMediaList($row->{$collection} ?? null);
        $item = $items[$index] ?? null;
        if (! is_array($item)) {
            return null;
        }

        if (($section['images_only'] ?? false) && ! $this->isBrowserImage($item)) {
            return null;
        }

        $path = trim((string) ($item['path'] ?? ''));
        if ($path === '') {
            return null;
        }

        $disk = (string) ($item['disk'] ?? config('filesystems.default'));
        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        return [
            'path' => $path,
            'disk' => $disk,
            'original_name' => (string) ($item['original_name'] ?? basename($path)),
            'mime' => (string) ($item['mime'] ?? ''),
        ];
    }

    /**
     * @param  list<int>|null  $indices
     * @return list<array{path: string, disk: string, original_name: string, mime: string}>
     */
    public function resolveAlbumMediaFiles(string $sectionKey, int $recordId, ?array $indices = null): array
    {
        $data = $this->album($sectionKey, $recordId);
        if ($data === null) {
            return [];
        }

        $wanted = is_array($indices) ? array_values(array_unique(array_map('intval', $indices))) : null;

        $files = [];
        foreach ($data['photos'] as $i => $photo) {
            if ($wanted !== null && ! in_array((int) $i, $wanted, true)) {
                continue;
            }

            $item = $this->resolveMediaItem(
                $sectionKey,
                $recordId,
                (string) $photo['collection'],
                (int) $photo['index'],
            );
            if ($item !== null) {
                $files[] = $item;
            }
        }

        return $files;
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function sectionIsAvailable(array $section): bool
    {
        $table = $section['table'];
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $section['date'])) {
            return false;
        }

        foreach ($section['collections'] as $collection) {
            if (Schema::hasColumn($table, $collection)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array{hub?: int|null, district?: int|null, from?: string, to?: string, q?: string}  $filters
     */
    private function albumRows(array $section, array $filters, int $limit): Collection
    {
        return $this->baseAlbumQuery($section, $filters)
            ->orderByDesc($section['date'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array{hub?: int|null, district?: int|null, from?: string, to?: string, q?: string}  $filters
     */
    private function countAlbumsWithMedia(array $section, array $filters): int
    {
        try {
            return (int) $this->baseAlbumQuery($section, $filters)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array{hub?: int|null, district?: int|null, from?: string, to?: string, q?: string}  $filters
     */
    private function baseAlbumQuery(array $section, array $filters): Builder
    {
        $table = $section['table'];
        $availableCollections = array_values(array_filter(
            $section['collections'],
            fn (string $c): bool => Schema::hasColumn($table, $c),
        ));

        $columns = array_values(array_unique(array_filter([
            'id',
            $section['date'],
            $section['title'],
            $section['district_id'],
            $section['district_name'],
            ...$this->extraSelectColumns($section),
            ...$availableCollections,
        ], fn (?string $column): bool => $column !== null && $column !== '' && Schema::hasColumn($table, $column))));

        $query = DB::table($table)->select($columns);

        $query->where(function (Builder $scope) use ($table, $availableCollections): void {
            foreach ($availableCollections as $i => $collection) {
                $method = $i === 0 ? 'where' : 'orWhere';
                $scope->{$method}(function (Builder $inner) use ($table, $collection): void {
                    $inner->whereNotNull($table.'.'.$collection)
                        ->where($table.'.'.$collection, '!=', '')
                        ->where($table.'.'.$collection, '!=', '[]')
                        ->where($table.'.'.$collection, '!=', 'null');
                });
            }
        });

        $this->applyStatusFilter($query, $table, $section['status'] ?? null);
        $this->applyGeoFilters($query, $table, $section, $filters);
        $this->applyDateFilters($query, $table, $section['date'], $filters);
        $this->applySearchFilter($query, $table, $section, $filters);

        return $query;
    }

    private function applyStatusFilter(Builder $query, string $table, ?string $statusMode): void
    {
        if ($statusMode === null || ! Schema::hasColumn($table, 'status')) {
            return;
        }

        if ($statusMode === 'approved') {
            $query->where($table.'.status', ServiceCase::STATUS_APPROVED);

            return;
        }

        if ($statusMode === 'submitted_or_null') {
            $query->where(function (Builder $q) use ($table): void {
                $q->where($table.'.status', 'submitted')
                    ->orWhereNull($table.'.status');
            });
        }
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array{hub?: int|null, district?: int|null, from?: string, to?: string, q?: string}  $filters
     */
    private function applyGeoFilters(Builder $query, string $table, array $section, array $filters): void
    {
        $hubId = (int) ($filters['hub'] ?? 0);
        $districtId = (int) ($filters['district'] ?? 0);
        $districtCol = $section['district_id'] ?? null;

        if ($districtId > 0 && $districtCol && Schema::hasColumn($table, $districtCol)) {
            $query->where($table.'.'.$districtCol, $districtId);

            return;
        }

        if ($hubId > 0 && $districtCol && Schema::hasColumn($table, $districtCol) && Schema::hasTable('districts')) {
            $districtIds = DB::table('districts')->where('hub_id', $hubId)->pluck('id')->all();
            if ($districtIds === []) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->whereIn($table.'.'.$districtCol, $districtIds);
        }
    }

    /**
     * @param  array{hub?: int|null, district?: int|null, from?: string, to?: string, q?: string}  $filters
     */
    private function applyDateFilters(Builder $query, string $table, string $dateCol, array $filters): void
    {
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));

        if ($from !== '') {
            $query->whereDate($table.'.'.$dateCol, '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate($table.'.'.$dateCol, '<=', $to);
        }
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array{hub?: int|null, district?: int|null, from?: string, to?: string, q?: string}  $filters
     */
    private function applySearchFilter(Builder $query, string $table, array $section, array $filters): void
    {
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q === '') {
            return;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
        $titleCol = $section['title'];
        $districtNameCol = $section['district_name'] ?? null;

        $query->where(function (Builder $scope) use ($table, $titleCol, $districtNameCol, $like, $section): void {
            if ($titleCol && Schema::hasColumn($table, $titleCol)) {
                $scope->where($table.'.'.$titleCol, 'like', $like);
            }
            if ($districtNameCol && Schema::hasColumn($table, $districtNameCol)) {
                $scope->orWhere($table.'.'.$districtNameCol, 'like', $like);
            }
            foreach ($this->extraSelectColumns($section) as $col) {
                $scope->orWhere($table.'.'.$col, 'like', $like);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function findRow(array $section, int $recordId): ?object
    {
        $table = $section['table'];
        $availableCollections = array_values(array_filter(
            $section['collections'],
            fn (string $c): bool => Schema::hasColumn($table, $c),
        ));

        $columns = array_values(array_unique(array_filter([
            'id',
            $section['date'],
            $section['title'],
            $section['district_id'],
            $section['district_name'],
            ...$this->extraSelectColumns($section),
            ...$availableCollections,
        ], fn (?string $column): bool => $column !== null && $column !== '' && Schema::hasColumn($table, $column))));

        return DB::table($table)->where('id', $recordId)->first($columns);
    }

    /**
     * @param  array<string, mixed>  $section
     * @return list<array<string, mixed>>
     */
    private function photosFromRow(array $section, object $row, string $sectionKey): array
    {
        $photos = [];
        $imagesOnly = (bool) ($section['images_only'] ?? false);

        foreach ($section['collections'] as $collection) {
            if (! Schema::hasColumn($section['table'], $collection)) {
                continue;
            }

            $items = $this->decodeMediaList($row->{$collection} ?? null);
            foreach ($items as $index => $item) {
                if ($imagesOnly && ! $this->isBrowserImage($item)) {
                    continue;
                }

                $path = trim((string) ($item['path'] ?? ''));
                if ($path === '') {
                    continue;
                }

                $disk = (string) ($item['disk'] ?? config('filesystems.default'));
                if (! Storage::disk($disk)->exists($path)) {
                    continue;
                }

                $name = (string) ($item['original_name'] ?? basename($path));
                $routeParams = [
                    'section' => $sectionKey,
                    'record' => (int) $row->id,
                    'collection' => $collection,
                    'index' => (int) $index,
                ];
                $photos[] = [
                    'collection' => $collection,
                    'index' => (int) $index,
                    'name' => $name,
                    'mime' => (string) ($item['mime'] ?? ''),
                    'thumb_url' => route('admin.media-gallery.photo', $routeParams + ['inline' => 1, 'size' => 'thumb']),
                    'preview_url' => route('admin.media-gallery.photo', $routeParams + ['inline' => 1, 'size' => 'preview']),
                    'inline_url' => route('admin.media-gallery.photo', $routeParams + ['inline' => 1, 'size' => 'preview']),
                    'download_url' => route('admin.media-gallery.photo', $routeParams),
                ];
            }
        }

        return $photos;
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function rowTitle(array $section, object $row): string
    {
        $titleCol = $section['title'];
        $title = $titleCol && isset($row->{$titleCol})
            ? trim((string) $row->{$titleCol})
            : '';

        return $title !== '' ? $title : (string) $section['title_fallback'];
    }

    /**
     * @param  array<string, mixed>  $section
     * @return list<string>
     */
    private function extraSelectColumns(array $section): array
    {
        $table = (string) $section['table'];
        $columns = [];
        foreach ([...($section['context_fields'] ?? []), ...($section['briefing_fields'] ?? [])] as $column) {
            if (is_string($column) && $column !== '' && Schema::hasColumn($table, $column)) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function rowContext(array $section, object $row): string
    {
        $parts = [];
        foreach ($section['context_fields'] ?? [] as $column) {
            if (! is_string($column) || ! isset($row->{$column})) {
                continue;
            }
            $value = trim((string) $row->{$column});
            if ($value === '') {
                continue;
            }
            $parts[] = $this->humanizeField($column, $value);
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function rowBriefing(array $section, object $row): string
    {
        $parts = [];
        foreach ($section['briefing_fields'] ?? [] as $column) {
            if (! is_string($column) || ! isset($row->{$column})) {
                continue;
            }
            $value = trim((string) $row->{$column});
            if ($value === '' || in_array($value, $parts, true)) {
                continue;
            }
            $parts[] = $value;
        }

        return implode("\n\n", $parts);
    }

    private function humanizeField(string $column, string $value): string
    {
        if ($column === 'meeting_purpose') {
            $label = LineDepartmentMeetingOptions::meetingPurposes()[$value] ?? null;
            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        if ($column === 'meeting_purpose_other') {
            return $value;
        }

        if (str_contains($value, '_') && ! str_contains($value, ' ')) {
            return ucfirst(str_replace('_', ' ', $value));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function rowDistrict(array $section, object $row): string
    {
        $nameCol = $section['district_name'] ?? null;
        if ($nameCol && isset($row->{$nameCol})) {
            $name = trim((string) $row->{$nameCol});
            if ($name !== '') {
                return $name;
            }
        }

        $idCol = $section['district_id'] ?? null;
        $districtId = $idCol ? (int) ($row->{$idCol} ?? 0) : 0;
        if ($districtId > 0 && Schema::hasTable('districts')) {
            if (! array_key_exists($districtId, $this->districtNameCache)) {
                $name = DB::table('districts')->where('id', $districtId)->value('name');
                $this->districtNameCache[$districtId] = is_string($name) ? trim($name) : '';
            }
            if ($this->districtNameCache[$districtId] !== '') {
                return $this->districtNameCache[$districtId];
            }
        }

        return '—';
    }

    /** @return list<array<string, mixed>> */
    private function decodeMediaList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return collect(is_array($value) ? $value : [])
            ->filter(fn ($item): bool => is_array($item))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $item */
    private function isBrowserImage(array $item): bool
    {
        $type = strtolower(trim((string) ($item['type'] ?? '')));
        if ($type === 'file') {
            return false;
        }

        $mime = strtolower(trim((string) ($item['mime'] ?? '')));
        if (str_starts_with($mime, 'image/')) {
            return ! in_array($mime, ['image/heic', 'image/heif'], true);
        }

        $name = strtolower((string) ($item['original_name'] ?? $item['path'] ?? ''));

        return (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', $name);
    }
}

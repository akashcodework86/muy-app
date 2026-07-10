<?php

namespace App\Services;

use App\Models\AccelerationServiceItem;
use App\Models\AccelerationServiceItemCatalog;
use App\Models\AccelerationServiceItemMedia;
use App\Models\AccelerationServiceSession;
use App\Models\User;
use App\Support\AccelerationItemSchemas;
use App\Support\AccelerationServicesOptions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AccelerationServiceRecorder
{
    public function __construct(
        private readonly AccelerationServicesIncubateeService $incubatees,
        private readonly SchemaValidator $schemaValidator,
    ) {}

    /**
     * @return array{session: AccelerationServiceSession, item_count: int}
     */
    public function store(Request $request, User $user): array
    {
        if (! Schema::hasTable('acceleration_service_sessions')) {
            throw ValidationException::withMessages([
                'service_date' => 'Database tables are missing. Please run migrations first.',
            ]);
        }

        $validated = $request->validate([
            'service_date' => ['required', 'date'],
            'legacy_phase1_application_id' => ['required', 'integer', 'min:1'],
            'service_detail' => ['nullable', 'array'],
            'service_detail.*' => ['string', 'max:64'],
            'cross_cutting' => ['nullable', 'array'],
            'cross_cutting.*' => ['string', 'max:64'],
            'partnership' => ['nullable', 'array'],
            'partnership.*' => ['string', 'max:64'],
            'payload' => ['nullable', 'array'],
            'custom_service_detail' => ['nullable', 'array'],
            'custom_service_detail.*' => ['nullable', 'string', 'max:191'],
            'custom_cross_cutting' => ['nullable', 'array'],
            'custom_cross_cutting.*' => ['nullable', 'string', 'max:191'],
            'custom_partnership' => ['nullable', 'array'],
            'custom_partnership.*' => ['nullable', 'string', 'max:191'],
        ]);

        $applicant = $this->incubatees->findPhase1Applicant((int) $validated['legacy_phase1_application_id']);
        if ($applicant === null) {
            throw ValidationException::withMessages([
                'legacy_phase1_application_id' => 'Phase 1 applicant not found.',
            ]);
        }

        $items = $this->buildItemsFromRequest($request, $validated);
        if ($items === []) {
            throw ValidationException::withMessages([
                'service_detail' => 'Select at least one service.',
            ]);
        }

        $serviceDate = (string) $validated['service_date'];
        $fiscalYearId = $this->incubatees->resolveFiscalYearIdForDate($serviceDate);
        $incubateeKey = (string) $applicant['incubatee_key'];
        $countsFor72 = $this->incubatees->shouldCountFor72($incubateeKey, $fiscalYearId);

        return DB::transaction(function () use ($user, $applicant, $serviceDate, $fiscalYearId, $incubateeKey, $countsFor72, $items, $request): array {
            $session = AccelerationServiceSession::query()->create([
                'service_date' => $serviceDate,
                'fiscal_year_id' => $fiscalYearId,
                'legacy_phase1_application_id' => (int) $applicant['legacy_phase1_application_id'],
                'incubatee_key' => $incubateeKey,
                'incubatee_source' => 'phase1',
                'applicant_name' => (string) $applicant['applicant_name'],
                'application_no' => (string) ($applicant['application_no'] ?? ''),
                'phone' => (string) ($applicant['phone'] ?? ''),
                'district_name' => (string) ($applicant['district_name'] ?? ''),
                'onboard_label' => (string) ($applicant['onboard_label'] ?? ''),
                'counts_for_7_2' => $countsFor72,
                'submitted_by_user_id' => (int) $user->id,
                'submitted_by_name' => (string) $user->name,
            ]);

            $itemCount = 0;
            foreach ($items as $item) {
                $create = [
                    'session_id' => $session->id,
                    'section' => (string) $item['section'],
                    'item_key' => (string) $item['item_key'],
                    'item_label' => (string) $item['item_label'],
                    'remarks' => trim((string) ($item['remarks'] ?? '')) ?: null,
                    'is_custom' => (bool) ($item['is_custom'] ?? false),
                    'is_buyer_seller_meet' => (string) $item['item_key'] === AccelerationServicesOptions::BUYER_SELLER_MEET_KEY,
                ];

                if (Schema::hasColumn('acceleration_service_items', 'payload')) {
                    $create['payload'] = $item['payload'] ?? [];
                }

                $itemRow = AccelerationServiceItem::query()->create($create);

                $this->storeMediaForItem($request, (string) $item['item_key'], (int) $itemRow->id, (int) $user->id);
                $itemCount++;
            }

            return [
                'session' => $session->fresh(['items.media']),
                'item_count' => $itemCount,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>
     */
    private function buildItemsFromRequest(Request $request, array $validated): array
    {
        $items = [];
        $allPayload = is_array($validated['payload'] ?? null) ? $validated['payload'] : [];

        $sectionMap = [
            AccelerationServicesOptions::SECTION_SERVICE_DETAIL => array_merge(
                array_values(array_filter((array) ($validated['service_detail'] ?? []))),
                $this->registerCustomLabels(
                    AccelerationServicesOptions::SECTION_SERVICE_DETAIL,
                    (array) ($validated['custom_service_detail'] ?? []),
                    (int) ($request->user()?->id ?? 0),
                ),
            ),
            AccelerationServicesOptions::SECTION_CROSS_CUTTING => array_merge(
                array_values(array_filter((array) ($validated['cross_cutting'] ?? []))),
                $this->registerCustomLabels(
                    AccelerationServicesOptions::SECTION_CROSS_CUTTING,
                    (array) ($validated['custom_cross_cutting'] ?? []),
                    (int) ($request->user()?->id ?? 0),
                ),
            ),
            AccelerationServicesOptions::SECTION_PARTNERSHIP => array_merge(
                array_values(array_filter((array) ($validated['partnership'] ?? []))),
                $this->registerCustomLabels(
                    AccelerationServicesOptions::SECTION_PARTNERSHIP,
                    (array) ($validated['custom_partnership'] ?? []),
                    (int) ($request->user()?->id ?? 0),
                ),
            ),
        ];

        $seen = [];
        foreach ($sectionMap as $section => $keys) {
            foreach ($keys as $entry) {
                $isCustom = is_array($entry);
                $key = $isCustom ? (string) ($entry['key'] ?? '') : trim((string) $entry);
                if ($key === '') {
                    continue;
                }

                $dedupe = $section.'|'.$key;
                if (isset($seen[$dedupe])) {
                    continue;
                }
                $seen[$dedupe] = true;

                $label = $isCustom
                    ? (string) ($entry['label'] ?? '')
                    : AccelerationServicesOptions::labelForKey($section, $key);

                $schema = AccelerationItemSchemas::forKey($key, $section);
                $rawPayload = is_array($allPayload[$key] ?? null) ? $allPayload[$key] : [];

                try {
                    $cleanPayload = $this->schemaValidator->validate($schema, $rawPayload);
                } catch (ValidationException $e) {
                    $messages = [];
                    foreach ($e->errors() as $field => $errs) {
                        foreach ($errs as $err) {
                            $messages['payload.'.$key.'.'.$field] = $err;
                        }
                    }
                    throw ValidationException::withMessages($messages !== [] ? $messages : [
                        'payload.'.$key => 'Please complete the required fields for '.$label.'.',
                    ]);
                }

                $itemDate = trim((string) ($cleanPayload['service_item_date'] ?? ''));
                $remarks = $itemDate !== ''
                    ? 'Date: '.$itemDate
                    : null;

                $items[] = [
                    'section' => $section,
                    'item_key' => $key,
                    'item_label' => $label,
                    'remarks' => $remarks,
                    'payload' => $cleanPayload,
                    'is_custom' => $isCustom,
                ];
            }
        }

        return $items;
    }

    /**
     * @param  list<string>  $labels
     * @return list<array{key: string, label: string}>
     */
    private function registerCustomLabels(string $section, array $labels, int $userId): array
    {
        if (! Schema::hasTable('acceleration_service_item_catalog')) {
            return [];
        }

        $out = [];
        foreach ($labels as $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }

            $key = AccelerationServicesOptions::catalogKeyFromLabel($label);
            AccelerationServiceItemCatalog::query()->updateOrCreate(
                ['item_key' => $key],
                [
                    'section' => $section,
                    'item_label' => $label,
                    'is_system' => false,
                    'is_active' => true,
                    'created_by_user_id' => $userId > 0 ? $userId : null,
                ],
            );

            $out[] = ['key' => $key, 'label' => $label];
        }

        return $out;
    }

    private function storeMediaForItem(Request $request, string $itemKey, int $itemId, int $userId): void
    {
        if (! Schema::hasTable('acceleration_service_item_media')) {
            return;
        }

        $files = $request->file('media.'.$itemKey, []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('acceleration-service-media/'.$itemId, 'local');
            AccelerationServiceItemMedia::query()->create([
                'item_id' => $itemId,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName() ?: 'upload',
                'mime_type' => $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'uploaded_by_user_id' => $userId,
            ]);
        }
    }
}

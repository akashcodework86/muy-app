<?php

namespace App\Services;

use App\Models\AccelerationServiceItem;
use App\Models\AccelerationServiceItemCatalog;
use App\Models\AccelerationServiceItemMedia;
use App\Models\AccelerationServiceSession;
use App\Models\User;
use App\Support\AccelerationItemSchemas;
use App\Support\AccelerationServicesAccess;
use App\Support\AccelerationServicesApproval;
use App\Support\AccelerationServicesOptions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        return $this->persist($request, $user, null, false);
    }

    /**
     * @return array{session: AccelerationServiceSession, item_count: int}
     */
    public function update(Request $request, User $user, AccelerationServiceSession $session): array
    {
        return $this->persist($request, $user, $session, false);
    }

    /**
     * Relaxed validation autosave — creates/updates a draft session.
     *
     * @return array{session: AccelerationServiceSession, item_count: int}
     */
    public function autosave(Request $request, User $user, ?AccelerationServiceSession $session = null): array
    {
        return $this->persist($request, $user, $session, true);
    }

    /**
     * @return array{session: AccelerationServiceSession, item_count: int}
     */
    private function persist(Request $request, User $user, ?AccelerationServiceSession $existing, bool $asDraft): array
    {
        if (! Schema::hasTable('acceleration_service_sessions')) {
            throw ValidationException::withMessages([
                'service_date' => 'Database tables are missing. Please run migrations first.',
            ]);
        }

        if ($existing && $existing->isLocked()) {
            throw ValidationException::withMessages([
                'service_date' => 'This entry is approved and locked. Use “Add more services” to log a new entry for this incubatee.',
            ]);
        }

        $allowedSections = AccelerationServicesAccess::allowedSections($user);

        $validated = $request->validate([
            'service_date' => [$asDraft ? 'nullable' : 'required', 'date'],
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

        if (! in_array(AccelerationServicesOptions::SECTION_CROSS_CUTTING, $allowedSections, true)) {
            $validated['cross_cutting'] = [];
            $validated['custom_cross_cutting'] = [];
        }
        if (! in_array(AccelerationServicesOptions::SECTION_PARTNERSHIP, $allowedSections, true)) {
            $validated['partnership'] = [];
            $validated['custom_partnership'] = [];
        }

        $applicant = $this->incubatees->findPhase1Applicant((int) $validated['legacy_phase1_application_id']);
        if ($applicant === null) {
            throw ValidationException::withMessages([
                'legacy_phase1_application_id' => 'Phase 1 applicant not found.',
            ]);
        }

        $items = $this->buildItemsFromRequest($request, $validated, $asDraft, $existing, $allowedSections);
        if (! $asDraft && $items === []) {
            throw ValidationException::withMessages([
                'service_detail' => 'Select at least one service.',
            ]);
        }

        $serviceDate = (string) ($validated['service_date'] ?? ($existing?->service_date?->toDateString() ?? now()->toDateString()));
        if ($serviceDate === '') {
            $serviceDate = now()->toDateString();
        }
        $fiscalYearId = $this->incubatees->resolveFiscalYearIdForDate($serviceDate);
        $incubateeKey = (string) $applicant['incubatee_key'];

        $workflowReady = AccelerationServicesApproval::workflowReady();
        $previousStatus = $existing ? (string) $existing->status : null;
        $wasDraft = $existing ? (bool) $existing->is_draft : false;

        $result = DB::transaction(function () use ($user, $applicant, $serviceDate, $fiscalYearId, $incubateeKey, $items, $request, $existing, $asDraft, $workflowReady, $wasDraft): array {
            $countsFor72 = $existing && ! $existing->is_draft
                ? (bool) $existing->counts_for_7_2
                : $this->incubatees->shouldCountFor72($incubateeKey, $fiscalYearId);

            $statusAttrs = [];
            if ($workflowReady) {
                if ($asDraft && (! $existing || $wasDraft)) {
                    $statusAttrs['status'] = AccelerationServicesApproval::STATUS_DRAFT;
                } else {
                    // Any submit/edit (incl. autosave of a submitted entry) restarts the approval chain.
                    $statusAttrs = [
                        'status' => AccelerationServicesApproval::initialStatusFor($user),
                        'first_approved_by_user_id' => null,
                        'first_approved_by_name' => null,
                        'first_approved_at' => null,
                        'final_approved_by_user_id' => null,
                        'final_approved_by_name' => null,
                        'final_approved_at' => null,
                    ];
                }
            }

            if ($existing) {
                $session = $existing;
                $attrs = [
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
                    'submitted_by_user_id' => (int) ($session->submitted_by_user_id ?: $user->id),
                    'submitted_by_name' => (string) ($session->submitted_by_name ?: $user->name),
                ];
                if (Schema::hasColumn('acceleration_service_sessions', 'is_draft')) {
                    $attrs['is_draft'] = $asDraft
                        ? (bool) $existing->is_draft
                        : false;
                }
                $session->fill(array_merge($attrs, $statusAttrs))->save();

                $keepMediaByKey = [];
                foreach ($session->items()->with('media')->get() as $oldItem) {
                    $keepMediaByKey[(string) $oldItem->item_key] = $oldItem->media->all();
                    $oldItem->delete();
                }
            } else {
                $create = [
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
                ];
                if (Schema::hasColumn('acceleration_service_sessions', 'is_draft')) {
                    $create['is_draft'] = $asDraft;
                }
                $session = AccelerationServiceSession::query()->create(array_merge($create, $statusAttrs));
                $keepMediaByKey = [];
            }

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

                $previousMedia = $keepMediaByKey[(string) $item['item_key']] ?? collect();
                foreach ($previousMedia as $media) {
                    $media->item_id = $itemRow->id;
                    $media->save();
                }

                $this->storeMediaForItem($request, (string) $item['item_key'], (int) $itemRow->id, (int) $user->id);
                $itemCount++;
            }

            // Drop orphan media for removed keys
            foreach ($keepMediaByKey as $key => $mediaSet) {
                $kept = collect($items)->contains(fn ($i) => (string) $i['item_key'] === (string) $key);
                if ($kept) {
                    continue;
                }
                foreach ($mediaSet as $media) {
                    Storage::disk((string) $media->disk)->delete((string) $media->path);
                    $media->delete();
                }
            }

            return [
                'session' => $session->fresh(['items.media']),
                'item_count' => $itemCount,
            ];
        });

        if (! $asDraft) {
            $action = 'submitted';
            if ($existing && ! $wasDraft) {
                $action = $previousStatus === AccelerationServicesApproval::STATUS_SENT_BACK
                    ? 'resubmitted'
                    : 'updated';
            }

            AccelerationServicesApproval::log($result['session'], $user, $action, null, [
                'item_count' => (int) $result['item_count'],
                'service_date' => $serviceDate,
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<string>  $allowedSections
     * @return list<array<string, mixed>>
     */
    private function buildItemsFromRequest(
        Request $request,
        array $validated,
        bool $asDraft = false,
        ?AccelerationServiceSession $existing = null,
        array $allowedSections = [],
    ): array {
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

        $existingMediaKeys = [];
        if ($existing) {
            foreach ($existing->items()->withCount('media')->get() as $row) {
                if ((int) $row->media_count > 0) {
                    $existingMediaKeys[(string) $row->item_key] = true;
                }
            }
        }

        $seen = [];
        foreach ($sectionMap as $section => $keys) {
            foreach ($keys as $entry) {
                $isCustom = is_array($entry);
                $key = $isCustom ? (string) ($entry['key'] ?? '') : trim((string) $entry);
                if ($key === '') {
                    continue;
                }
                // Soft skills retired from UI — ignore if posted.
                if ($key === 'soft_skills') {
                    continue;
                }
                if ($allowedSections !== [] && ! in_array($section, $allowedSections, true)) {
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
                if ($asDraft) {
                    $schema = array_map(static function (array $field): array {
                        $field['required'] = false;

                        return $field;
                    }, $schema);
                }

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

                if (! $asDraft && (
                    $key === 'business_formalization'
                    || $key === 'funding_investment_support'
                    || AccelerationServicesOptions::isMarketLinkageKey($key)
                    || $key === AccelerationServicesOptions::BUYER_SELLER_MEET_KEY
                )) {
                    $needsMediaAlways = in_array($key, ['business_formalization', 'funding_investment_support'], true);
                    $orderValue = (float) ($cleanPayload['order_value'] ?? 0);
                    $needsMedia = $needsMediaAlways || $orderValue > 0;

                    if ($needsMedia) {
                        $hasNewFiles = $this->requestHasMediaForKey($request, $key);
                        $hasExisting = isset($existingMediaKeys[$key]);
                        if (! $hasNewFiles && ! $hasExisting) {
                            $message = match ($key) {
                                'business_formalization' => 'Upload registration documents / photos for Business Formalization.',
                                'funding_investment_support' => 'Upload scheme application / sanction documents for Convergence — Funding and Investment Support.',
                                default => (
                                    AccelerationServicesOptions::isMarketLinkageKey($key) || $key === AccelerationServicesOptions::BUYER_SELLER_MEET_KEY
                                        ? 'Upload proof of order / PO documents when Order / PO value is filled for '.$label.'.'
                                        : 'Upload documents for '.$label.'.'
                                ),
                            };
                            throw ValidationException::withMessages([
                                'media.'.$key => $message,
                            ]);
                        }
                    }
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

    private function requestHasMediaForKey(Request $request, string $itemKey): bool
    {
        $files = $request->file('media.'.$itemKey, []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                return true;
            }
        }

        return false;
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

        $existingFingerprints = AccelerationServiceItemMedia::query()
            ->where('item_id', $itemId)
            ->get(['original_name', 'size_bytes'])
            ->map(static fn ($m) => strtolower((string) $m->original_name).'|'.(int) $m->size_bytes)
            ->flip()
            ->all();

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $originalName = $file->getClientOriginalName() ?: 'upload';
            $sizeBytes = (int) $file->getSize();
            $fingerprint = strtolower($originalName).'|'.$sizeBytes;
            // Autosave + final save (or repeated autosaves) can resend the same file input.
            if (isset($existingFingerprints[$fingerprint])) {
                continue;
            }
            $existingFingerprints[$fingerprint] = true;

            $path = $file->store('acceleration-service-media/'.$itemId, 'local');
            AccelerationServiceItemMedia::query()->create([
                'item_id' => $itemId,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $sizeBytes,
                'uploaded_by_user_id' => $userId,
            ]);
        }
    }
}

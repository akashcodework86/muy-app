<?php

namespace App\Services;

use App\Models\District;
use App\Models\TrainingPackage;
use App\Models\TrainingPackageMonthSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TrainingPackageMonthSessionService
{
    public function slotsForDistrictMonth(int $districtId, int $calendarYear, int $calendarMonth): Collection
    {
        return $this->plannedSlotsForDistrictMonth($districtId, $calendarYear, $calendarMonth);
    }

    public function plannedSlotsForDistrictMonth(int $districtId, int $calendarYear, int $calendarMonth): Collection
    {
        if ($districtId <= 0) {
            return collect();
        }

        return TrainingPackageMonthSession::query()
            ->with(['trainingPackage:id,month_session_id,submitted_by_name,event_date'])
            ->where('district_id', $districtId)
            ->where('calendar_year', $calendarYear)
            ->where('calendar_month', $calendarMonth)
            ->where('is_extra', false)
            ->orderBy('sort_order')
            ->get();
    }

    public function extraSlotsForDistrictMonth(int $districtId, int $calendarYear, int $calendarMonth): Collection
    {
        if ($districtId <= 0) {
            return collect();
        }

        return TrainingPackageMonthSession::query()
            ->with(['trainingPackage:id,month_session_id,submitted_by_name,event_date'])
            ->where('district_id', $districtId)
            ->where('calendar_year', $calendarYear)
            ->where('calendar_month', $calendarMonth)
            ->where('is_extra', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array{required:int,filled:int,remaining:int,extra_filled:int}
     */
    public function districtMonthSummary(int $districtId, int $calendarYear, int $calendarMonth): array
    {
        $planned = $this->plannedSlotsForDistrictMonth($districtId, $calendarYear, $calendarMonth);
        $extras = $this->extraSlotsForDistrictMonth($districtId, $calendarYear, $calendarMonth);

        return $this->summarizePlannedAndExtraSlots($planned, $extras);
    }

    /**
     * @return array{required:int,filled:int,remaining:int,extra_filled:int}
     */
    public function statewideMonthSummary(int $calendarYear, int $calendarMonth): array
    {
        $slots = TrainingPackageMonthSession::query()
            ->with(['trainingPackage:id,month_session_id'])
            ->where('calendar_year', $calendarYear)
            ->where('calendar_month', $calendarMonth)
            ->get();

        $planned = $slots->filter(fn (TrainingPackageMonthSession $slot): bool => ! (bool) $slot->is_extra);
        $extras = $slots->filter(fn (TrainingPackageMonthSession $slot): bool => (bool) $slot->is_extra);

        return $this->summarizePlannedAndExtraSlots($planned, $extras);
    }

    /**
     * @return Collection<int, array{
     *     district:District,
     *     slots:Collection<int, TrainingPackageMonthSession>,
     *     extra_slots:Collection<int, TrainingPackageMonthSession>,
     *     summary:array{required:int,filled:int,remaining:int,extra_filled:int}
     * }>
     */
    public function districtsWithSlotsForMonth(int $calendarYear, int $calendarMonth): Collection
    {
        $districts = District::query()
            ->with(['hub:id,name'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $slotsByDistrict = TrainingPackageMonthSession::query()
            ->with(['trainingPackage:id,month_session_id,submitted_by_name,event_date'])
            ->where('calendar_year', $calendarYear)
            ->where('calendar_month', $calendarMonth)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('district_id');

        return $districts->map(function (District $district) use ($slotsByDistrict): array {
            $slots = $slotsByDistrict->get($district->id, collect());
            $planned = $slots->filter(fn (TrainingPackageMonthSession $slot): bool => ! (bool) $slot->is_extra)->values();
            $extras = $slots->filter(fn (TrainingPackageMonthSession $slot): bool => (bool) $slot->is_extra)->values();

            return [
                'district' => $district,
                'slots' => $planned,
                'extra_slots' => $extras,
                'summary' => $this->summarizePlannedAndExtraSlots($planned, $extras),
            ];
        });
    }

    public function findOpenSlotForDistrict(int $slotId, int $districtId): ?TrainingPackageMonthSession
    {
        $slot = TrainingPackageMonthSession::query()
            ->with('trainingPackage')
            ->whereKey($slotId)
            ->where('district_id', $districtId)
            ->where('is_extra', false)
            ->first();

        if (! $slot || $slot->trainingPackage !== null) {
            return null;
        }

        return $slot;
    }

    public function assertSessionDateMatchesSlot(TrainingPackageMonthSession $slot, string $sessionDate): void
    {
        $date = \Illuminate\Support\Carbon::parse($sessionDate);

        if ((int) $date->year !== (int) $slot->calendar_year || (int) $date->month !== (int) $slot->calendar_month) {
            throw ValidationException::withMessages([
                'session_date' => 'Date of session must fall within the planned calendar month for the selected session.',
            ]);
        }
    }

    public function createExtraSlotForDistrictMonth(
        int $districtId,
        int $calendarYear,
        int $calendarMonth,
        string $sessionName,
        int $userId,
        string $sessionDate,
    ): TrainingPackageMonthSession {
        $sessionName = trim($sessionName);
        if ($sessionName === '') {
            throw ValidationException::withMessages([
                'extra_session_name' => 'Extra session name is required.',
            ]);
        }

        $maxSortOrder = (int) TrainingPackageMonthSession::query()
            ->where('district_id', $districtId)
            ->where('calendar_year', $calendarYear)
            ->where('calendar_month', $calendarMonth)
            ->max('sort_order');

        $slot = TrainingPackageMonthSession::query()->create([
            'district_id' => $districtId,
            'calendar_year' => $calendarYear,
            'calendar_month' => $calendarMonth,
            'sort_order' => $maxSortOrder + 1,
            'session_name' => $sessionName,
            'is_extra' => true,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ]);

        $this->assertSessionDateMatchesSlot($slot, $sessionDate);

        return $slot;
    }

    /**
     * @param  array<int, array{
     *     district_id:int,
     *     sessions:array<int, array{id?:int,session_name:string}>,
     *     extra_sessions?:array<int, array{id?:int,session_name:string}>
     * }>  $districtPlans
     */
    public function syncMonthPlan(int $calendarYear, int $calendarMonth, array $districtPlans, int $userId): void
    {
        DB::transaction(function () use ($calendarYear, $calendarMonth, $districtPlans, $userId): void {
            foreach ($districtPlans as $districtPlan) {
                $districtId = (int) ($districtPlan['district_id'] ?? 0);
                if ($districtId <= 0) {
                    continue;
                }

                $sessions = collect((array) ($districtPlan['sessions'] ?? []))
                    ->map(function ($session, int $index): array {
                        return [
                            'id' => isset($session['id']) ? (int) $session['id'] : 0,
                            'session_name' => trim((string) ($session['session_name'] ?? '')),
                            'sort_order' => $index + 1,
                        ];
                    })
                    ->filter(fn (array $session): bool => $session['session_name'] !== '')
                    ->values();

                $existing = TrainingPackageMonthSession::query()
                    ->with('trainingPackage:id,month_session_id')
                    ->where('district_id', $districtId)
                    ->where('calendar_year', $calendarYear)
                    ->where('calendar_month', $calendarMonth)
                    ->get()
                    ->keyBy('id');

                $keptIds = $existing
                    ->filter(fn (TrainingPackageMonthSession $slot): bool => (bool) $slot->is_extra)
                    ->keys()
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                $reservedSortOrders = $existing
                    ->filter(fn (TrainingPackageMonthSession $slot): bool => (bool) $slot->is_extra)
                    ->pluck('sort_order')
                    ->map(fn ($sortOrder): int => (int) $sortOrder)
                    ->all();

                foreach ($sessions as $session) {
                    $targetSortOrder = (int) $session['sort_order'];
                    while (in_array($targetSortOrder, $reservedSortOrders, true)) {
                        $targetSortOrder++;
                    }
                    $reservedSortOrders[] = $targetSortOrder;

                    $slotId = (int) $session['id'];
                    if ($slotId > 0 && $existing->has($slotId)) {
                        /** @var TrainingPackageMonthSession $slot */
                        $slot = $existing->get($slotId);
                        if ((bool) $slot->is_extra) {
                            continue;
                        }

                        $keptIds[] = $slot->id;

                        $slot->sort_order = $targetSortOrder;
                        $slot->session_name = (string) $session['session_name'];
                        $slot->updated_by_user_id = $userId;
                        $slot->save();

                        continue;
                    }

                    $created = TrainingPackageMonthSession::query()->create([
                        'district_id' => $districtId,
                        'calendar_year' => $calendarYear,
                        'calendar_month' => $calendarMonth,
                        'sort_order' => $targetSortOrder,
                        'session_name' => (string) $session['session_name'],
                        'is_extra' => false,
                        'created_by_user_id' => $userId,
                        'updated_by_user_id' => $userId,
                    ]);

                    $keptIds[] = (int) $created->id;
                }

                foreach ($existing as $slot) {
                    if ((bool) $slot->is_extra) {
                        continue;
                    }

                    if ($slot->trainingPackage !== null) {
                        continue;
                    }

                    if (! in_array((int) $slot->id, $keptIds, true)) {
                        $slot->delete();
                    }
                }

                $extraSessions = collect((array) ($districtPlan['extra_sessions'] ?? []))
                    ->map(fn ($session): array => [
                        'id' => isset($session['id']) ? (int) $session['id'] : 0,
                        'session_name' => trim((string) ($session['session_name'] ?? '')),
                    ])
                    ->filter(fn (array $session): bool => $session['id'] > 0 && $session['session_name'] !== '');

                foreach ($extraSessions as $extraSession) {
                    if (! $existing->has((int) $extraSession['id'])) {
                        continue;
                    }

                    /** @var TrainingPackageMonthSession $slot */
                    $slot = $existing->get((int) $extraSession['id']);
                    if (! (bool) $slot->is_extra) {
                        continue;
                    }

                    $slot->session_name = (string) $extraSession['session_name'];
                    $slot->updated_by_user_id = $userId;
                    $slot->save();
                }
            }
        });
    }

    /**
     * @return int Number of districts that received the default required sessions.
     */
    public function assignDefaultSessionsForMonth(int $calendarYear, int $calendarMonth, int $userId): int
    {
        $districtPlans = District::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(function (District $district) use ($calendarYear, $calendarMonth): bool {
                return $this->plannedSlotsForDistrictMonth((int) $district->id, $calendarYear, $calendarMonth)->isEmpty();
            })
            ->map(fn (District $district): array => [
                'district_id' => (int) $district->id,
                'sessions' => [
                    ['session_name' => 'Session 1'],
                    ['session_name' => 'Session 2'],
                ],
            ])
            ->values()
            ->all();

        if ($districtPlans === []) {
            return 0;
        }

        $this->syncMonthPlan($calendarYear, $calendarMonth, $districtPlans, $userId);

        return count($districtPlans);
    }

    public function slotIsLinkedToPackage(int $slotId): bool
    {
        return TrainingPackage::query()->where('month_session_id', $slotId)->exists();
    }

    public function deleteMonthSession(TrainingPackageMonthSession $slot): void
    {
        DB::transaction(function () use ($slot): void {
            $slot = TrainingPackageMonthSession::query()
                ->with('trainingPackage')
                ->whereKey($slot->id)
                ->lockForUpdate()
                ->firstOrFail();

            $package = $slot->trainingPackage;
            if ($package !== null) {
                $this->deleteTrainingPackageMedia($package);
                $package->delete();
            }

            $slot->delete();
        });
    }

    /**
     * @param  Collection<int, TrainingPackageMonthSession>  $plannedSlots
     * @param  Collection<int, TrainingPackageMonthSession>  $extraSlots
     * @return array{required:int,filled:int,remaining:int,extra_filled:int}
     */
    private function summarizePlannedAndExtraSlots(Collection $plannedSlots, Collection $extraSlots): array
    {
        $required = $plannedSlots->count();
        $filled = $plannedSlots
            ->filter(fn (TrainingPackageMonthSession $slot): bool => $slot->trainingPackage !== null)
            ->count();
        $extraFilled = $extraSlots
            ->filter(fn (TrainingPackageMonthSession $slot): bool => $slot->trainingPackage !== null)
            ->count();

        return [
            'required' => $required,
            'filled' => $filled,
            'remaining' => max(0, $required - $filled),
            'extra_filled' => $extraFilled,
        ];
    }

    private function deleteTrainingPackageMedia(TrainingPackage $trainingPackage): void
    {
        foreach ((array) $trainingPackage->attendance_media_json as $media) {
            if (! is_array($media)) {
                continue;
            }

            $path = (string) ($media['path'] ?? '');
            if ($path !== '' && Storage::exists($path)) {
                Storage::delete($path);
            }
        }
    }
}

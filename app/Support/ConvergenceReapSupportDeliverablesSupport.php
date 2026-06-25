<?php

namespace App\Support;

use App\Models\Service;
use App\Models\ServiceCase;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ConvergenceReapSupportDeliverablesSupport
{
    /**
     * @param  list<int>|null  $districtIds
     */
    public static function countCases(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        if ($districtIds === [] || ! Schema::hasTable('service_cases') || ! Schema::hasTable('services')) {
            return 0;
        }

        return (int) self::scopedQuery($districtIds, $periodFrom, $periodTo)->count();
    }

    /**
     * @return list<int>
     */
    public static function convergenceServiceIds(): array
    {
        if (! Schema::hasTable('services')) {
            return [];
        }

        return Service::query()
            ->where('is_active', true)
            ->whereHas('category', function ($q): void {
                $q->whereIn('slug', ConvergenceReapSupport::CONVERGENCE_CATEGORY_SLUGS)
                    ->orWhere('slug', 'like', '%convergence%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    public static function countSchematicConvergenceCases(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): int
    {
        if ($districtIds === [] || ! Schema::hasTable('service_cases') || ! Schema::hasTable('services')) {
            return 0;
        }

        return (int) self::schematicConvergenceScopedQuery($districtIds, $periodFrom, $periodTo)->count();
    }

    /**
     * All convergence-category services plus dedicated REAP-support services (MIS 8.1 superset of 8.2).
     *
     * @return list<int>
     */
    public static function schematicConvergenceServiceIds(): array
    {
        return array_values(array_unique(array_merge(
            self::convergenceServiceIds(),
            self::reapSupportServiceIds(),
        )));
    }

    /**
     * @return list<int>
     */
    public static function reapSupportServiceIds(): array
    {
        if (! Schema::hasTable('services') || ! Schema::hasColumn('services', 'counts_toward_reap_support')) {
            return [];
        }

        return Service::query()
            ->where('is_active', true)
            ->where(function ($scope): void {
                $scope->where('counts_toward_reap_support', true);
                foreach (ConvergenceReapSupport::knownReapSupportServiceCodes() as $code) {
                    $scope->orWhere('code', $code);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private static function scopedQuery(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        return self::buildAchievementScopedQuery(
            $districtIds,
            $periodFrom,
            $periodTo,
            fn (Builder $query): Builder => tap($query, fn (Builder $q) => self::applyAchievementScope($q)),
        );
    }

    /**
     * @param  list<int>|null  $districtIds
     */
    private static function schematicConvergenceScopedQuery(?array $districtIds, ?Carbon $periodFrom, ?Carbon $periodTo): Builder
    {
        return self::buildAchievementScopedQuery(
            $districtIds,
            $periodFrom,
            $periodTo,
            fn (Builder $query): Builder => tap($query, fn (Builder $q) => self::applySchematicConvergenceAchievementScope($q)),
        );
    }

    /**
     * @param  callable(Builder): Builder  $applyScope
     */
    private static function buildAchievementScopedQuery(
        ?array $districtIds,
        ?Carbon $periodFrom,
        ?Carbon $periodTo,
        callable $applyScope,
    ): Builder {
        $dateExpr = self::achievementDateExpression();
        $statuses = [ServiceCase::STATUS_APPROVED, ServiceCase::STATUS_COMPLETED];

        $query = DB::table('service_cases as sc')
            ->leftJoin('cfa_submissions as cs', 'cs.id', '=', 'sc.cfa_submission_id')
            ->whereIn('sc.status', $statuses);

        $applyScope($query);

        if ($districtIds !== null) {
            app(\App\Services\LegacyApplicationServiceCaseSupport::class)
                ->applyAchievementDistrictScopeToServiceCaseQuery($query, $districtIds);
        }

        $floor = Carbon::parse((string) config('program_deliverables.phase3_floor_date', '2026-04-01'))->startOfDay();
        if ($periodFrom && $periodTo) {
            $from = $periodFrom->copy();
            if ($periodTo->gte($floor) && $from->lt($floor)) {
                $from = $floor->copy();
            }
            $query->whereBetween(DB::raw($dateExpr), [$from->toDateTimeString(), $periodTo->toDateTimeString()]);
        } elseif ($periodTo && $periodTo->gte($floor)) {
            $query->where(DB::raw($dateExpr), '>=', $floor->toDateTimeString());
        }

        return $query;
    }

    /**
     * Count convergence cases marked Through REAP, plus all cases on dedicated REAP-support services.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    /**
     * Same OR scope as deliverables counting, without status/date filters — for service-case listings.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function applyListingScope($query, string $tableAlias = 'service_cases'): void
    {
        self::applyAchievementScope($query, $tableAlias);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function applyAchievementScope($query, string $tableAlias = 'sc'): void
    {
        $convergenceServiceIds = self::convergenceServiceIds();
        $reapSupportServiceIds = self::reapSupportServiceIds();

        if ($convergenceServiceIds === [] && $reapSupportServiceIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function ($scope) use ($convergenceServiceIds, $reapSupportServiceIds, $tableAlias): void {
            if ($reapSupportServiceIds !== []) {
                $scope->whereIn("{$tableAlias}.service_id", $reapSupportServiceIds);
            }
            if ($convergenceServiceIds !== []) {
                $scope->orWhere(function ($convergenceScope) use ($convergenceServiceIds, $tableAlias): void {
                    $convergenceScope->whereIn("{$tableAlias}.service_id", $convergenceServiceIds);
                    ConvergenceReapSupport::applyThroughReapPayloadScope($convergenceScope, $tableAlias);
                });
            }
        });
    }

    /**
     * MIS 8.1: all convergence service cases plus dedicated REAP-support services (no through_reap filter).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function applySchematicConvergenceAchievementScope($query, string $tableAlias = 'sc'): void
    {
        $serviceIds = self::schematicConvergenceServiceIds();
        if ($serviceIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn("{$tableAlias}.service_id", $serviceIds);
    }

    private static function achievementDateExpression(): string
    {
        $parts = [];
        foreach (['approved_at', 'completed_at', 'delivered_on', 'submitted_at', 'created_at'] as $column) {
            if (Schema::hasColumn('service_cases', $column)) {
                $parts[] = 'sc.'.$column;
            }
        }

        if ($parts === []) {
            return 'sc.created_at';
        }

        return count($parts) === 1 ? $parts[0] : 'COALESCE('.implode(', ', $parts).')';
    }
}

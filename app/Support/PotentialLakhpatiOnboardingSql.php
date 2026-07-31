<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared SQL for "Potential Lakhpati Didi/ SHG Members/ CBOs" onboarded counts
 * (admin onboarded page + program deliverables 2.1.1).
 */
final class PotentialLakhpatiOnboardingSql
{
    public static function payloadJson(string $path, string $payloadColumn = 'cs.payload'): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "json_extract({$payloadColumn}, '{$path}')";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT({$payloadColumn}, '{$path}'))";
    }

    public static function isLegacyCfaSourceSql(string $sourceColumn = 'cs.source'): string
    {
        return "LOWER(TRIM(COALESCE({$sourceColumn}, ''))) IN ('legacy_phase2', 'rbiphase2')";
    }

    public static function phase3CfaSourceSql(string $sourceColumn = 'cs.source'): string
    {
        return 'NOT ('.self::isLegacyCfaSourceSql($sourceColumn).')';
    }

    /**
     * SQL boolean — onboarded row qualifies for MIS 2.1.1.
     *
     * Phase 3: SHG/CBO category, or Individual with SHG/CBO member Yes.
     * Legacy Phase 2: Lakhpati Yes + member Yes — read from payload OR legacy DB row
     * (Phase-2 UI shows legacy DB; payload mirror is often empty for these fields).
     */
    public static function qualifiesSql(string $payloadColumn = 'cs.payload', string $sourceColumn = 'cs.source'): string
    {
        $categoryJson = self::payloadJson('$.category', $payloadColumn);
        $appCategoryJson = self::payloadJson('$.app_category', $payloadColumn);
        $memberYes = self::payloadMemberYesSql($payloadColumn);
        $lakhpatiYes = self::payloadLakhpatiYesSql($payloadColumn);

        $phase3Qualifies = '(
            '.self::phase3CfaSourceSql($sourceColumn)."
            AND (
                LOWER(TRIM(COALESCE({$categoryJson}, ''))) IN ('shg', 'cbo')
                OR LOWER(TRIM(COALESCE({$appCategoryJson}, ''))) IN ('shg', 'cbo')
                OR (
                    (
                        LOWER(TRIM(COALESCE({$categoryJson}, ''))) = 'individual'
                        OR LOWER(TRIM(COALESCE({$appCategoryJson}, ''))) = 'individual'
                    )
                    AND {$memberYes}
                )
            )
        )";

        $legacyQualifies = '(
            '.self::isLegacyCfaSourceSql($sourceColumn)."
            AND (
                ({$lakhpatiYes} AND {$memberYes})
                OR ".self::legacyApplicantDetailsQualifiesSql($payloadColumn)."
            )
        )";

        return "({$phase3Qualifies} OR {$legacyQualifies})";
    }

    /** SUM(CASE WHEN qualifies THEN 1 ELSE 0 END) for aggregate selects. */
    public static function sumCaseCountSql(string $payloadColumn = 'cs.payload', string $sourceColumn = 'cs.source'): string
    {
        return 'SUM(CASE WHEN '.self::qualifiesSql($payloadColumn, $sourceColumn).' THEN 1 ELSE 0 END)';
    }

    /**
     * Phase 3 only — Individual with Member of SHG/CBO = Yes (SHG members).
     */
    public static function phase3ShgMembersOnboardingSql(string $payloadColumn = 'cs.payload', string $sourceColumn = 'cs.source'): string
    {
        $categoryJson = self::payloadJson('$.category', $payloadColumn);
        $appCategoryJson = self::payloadJson('$.app_category', $payloadColumn);
        $memberYes = self::payloadMemberYesSql($payloadColumn);

        return '(
            '.self::phase3CfaSourceSql($sourceColumn)."
            AND (
                LOWER(TRIM(COALESCE({$categoryJson}, ''))) = 'individual'
                OR LOWER(TRIM(COALESCE({$appCategoryJson}, ''))) = 'individual'
            )
            AND {$memberYes}
        )";
    }

    /** Phase 3 only — category CBO. */
    public static function phase3CboOnboardingSql(string $payloadColumn = 'cs.payload', string $sourceColumn = 'cs.source'): string
    {
        $categoryJson = self::payloadJson('$.category', $payloadColumn);
        $appCategoryJson = self::payloadJson('$.app_category', $payloadColumn);

        return '(
            '.self::phase3CfaSourceSql($sourceColumn)."
            AND (
                LOWER(TRIM(COALESCE({$categoryJson}, ''))) = 'cbo'
                OR LOWER(TRIM(COALESCE({$appCategoryJson}, ''))) = 'cbo'
            )
        )";
    }

    private static function payloadMemberYesSql(string $payloadColumn): string
    {
        $paths = ['$.is_member', '$.is_shg_member', '$.member_of_shg', '$.member_of_shg_cbo'];
        $parts = [];
        foreach ($paths as $path) {
            $json = self::payloadJson($path, $payloadColumn);
            $parts[] = self::sqlValueIsYes($json);
        }

        return '('.implode(' OR ', $parts).')';
    }

    private static function payloadLakhpatiYesSql(string $payloadColumn): string
    {
        $paths = ['$.lakhpati', '$.lakhpati_didi', '$.is_lakhpati_didi'];
        $parts = [];
        foreach ($paths as $path) {
            $json = self::payloadJson($path, $payloadColumn);
            $parts[] = self::sqlValueIsYes($json);
        }

        return '('.implode(' OR ', $parts).')';
    }

    /**
     * Legacy Phase-2 fields live in rbi_applicant_details, keyed by payload.legacy_application_id.
     */
    private static function legacyApplicantDetailsQualifiesSql(string $payloadColumn): string
    {
        $table = self::qualifiedLegacyApplicantDetailsTable();
        if ($table === null) {
            return '0 = 1';
        }

        $legacyIdJson = self::payloadJson('$.legacy_application_id', $payloadColumn);
        $lakhpatiYes = self::sqlValueIsYes('legacy_d.lakhpati');
        $memberYes = self::sqlValueIsYes('legacy_d.is_shg_member');

        return "EXISTS (
            SELECT 1
            FROM {$table} AS legacy_d
            WHERE legacy_d.application_id = CAST({$legacyIdJson} AS UNSIGNED)
              AND legacy_d.application_id > 0
              AND {$lakhpatiYes}
              AND {$memberYes}
        )";
    }

    private static function qualifiedLegacyApplicantDetailsTable(): ?string
    {
        // Cross-database EXISTS only works on MySQL/MariaDB (production). SQLite tests use payload only.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return null;
        }

        $database = (string) config('database.connections.legacy.database', '');
        if ($database === '') {
            return null;
        }

        try {
            if (! Schema::connection('legacy')->hasTable('rbi_applicant_details')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $safeDb = str_replace('`', '', $database);

        return '`'.$safeDb.'`.rbi_applicant_details';
    }

    private static function sqlValueIsYes(string $expression): string
    {
        return "LOWER(TRIM(COALESCE(CAST({$expression} AS CHAR), ''))) IN ('yes', 'y', '1', 'true')";
    }
}

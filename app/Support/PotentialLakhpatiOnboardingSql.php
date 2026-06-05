<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

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
     * Legacy Phase 2: Lakhpati Didi Yes and SHG/CBO member Yes (both required).
     */
    public static function qualifiesSql(string $payloadColumn = 'cs.payload', string $sourceColumn = 'cs.source'): string
    {
        $categoryJson = self::payloadJson('$.category', $payloadColumn);
        $appCategoryJson = self::payloadJson('$.app_category', $payloadColumn);
        $isMemberJson = self::payloadJson('$.is_member', $payloadColumn);
        $isShgMemberJson = self::payloadJson('$.is_shg_member', $payloadColumn);
        $lakhpatiJson = self::payloadJson('$.lakhpati', $payloadColumn);

        $memberYes = "(
            LOWER(TRIM(COALESCE({$isMemberJson}, ''))) = 'yes'
            OR LOWER(TRIM(COALESCE({$isShgMemberJson}, ''))) = 'yes'
        )";

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
            AND LOWER(TRIM(COALESCE({$lakhpatiJson}, ''))) = 'yes'
            AND {$memberYes}
        )";

        return "({$phase3Qualifies} OR {$legacyQualifies})";
    }

    /** SUM(CASE WHEN qualifies THEN 1 ELSE 0 END) for aggregate selects. */
    public static function sumCaseCountSql(string $payloadColumn = 'cs.payload', string $sourceColumn = 'cs.source'): string
    {
        return 'SUM(CASE WHEN '.self::qualifiesSql($payloadColumn, $sourceColumn).' THEN 1 ELSE 0 END)';
    }
}

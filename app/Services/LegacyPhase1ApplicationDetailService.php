<?php

namespace App\Services;

use App\Models\CfaSubmission;
use App\Models\District;
use App\Services\LegacyPhase1\LegacyPhase1DistrictResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full Phase-1 legacy read for a mirrored {@see CfaSubmission} (source legacy_phase1 / rbiphase1).
 */
class LegacyPhase1ApplicationDetailService
{
    /**
     * @return array{
     *   viewRow: array<string, string>,
     *   legacy_phase1_id: int,
     *   tblapplication: array<string, mixed>,
     *   services: list<array{label: string, detail: ?string}>,
     *   district_mismatch_warning: ?string
     * }|null
     */
    /**
     * @return list<array{label: string, detail: ?string}>
     */
    public function servicesForLegacyId(int $legacyId): array
    {
        if ($legacyId <= 0 || ! $this->legacyAvailable()) {
            return [];
        }

        try {
            $row = $this->fetchRowById($legacyId);
        } catch (\Throwable) {
            return [];
        }

        if ($row === null) {
            return [];
        }

        return $this->extractServices((array) $row);
    }

    public function tryBuild(CfaSubmission $submission): ?array
    {
        if (! $this->isPhase1Source((string) ($submission->source ?? ''))) {
            return null;
        }

        if (! $this->legacyAvailable()) {
            return null;
        }

        $payload = is_array($submission->payload) ? $submission->payload : [];
        $legacyId = (int) ($payload['legacy_phase1_id'] ?? $payload['legacy_id'] ?? 0);

        try {
            $row = $legacyId > 0
                ? $this->fetchRowById($legacyId)
                : null;
            if ($row === null) {
                $row = $this->fetchRowBySubmissionFallback($submission);
            }
        } catch (\Throwable) {
            return null;
        }

        if ($row === null) {
            return null;
        }

        $legacyId = (int) ($row->ID ?? 0);
        if ($legacyId <= 0) {
            return null;
        }

        $district = District::query()->find($submission->district_id);
        $districtName = trim((string) ($district?->name ?? ''));
        $legacyDistrictKey = trim((string) ($row->FatherName ?? ''));
        $resolvedDistrict = LegacyPhase1DistrictResolver::canonicalNameForLegacyFatherName($legacyDistrictKey)
            ?? ($legacyDistrictKey !== '' ? $legacyDistrictKey : null);

        $districtMismatchWarning = null;
        if ($districtName !== '' && $resolvedDistrict !== null && $this->normDistrict($districtName) !== $this->normDistrict($resolvedDistrict)) {
            $districtMismatchWarning = 'Legacy row district ('.$resolvedDistrict.') does not match this CFA’s district ('.$districtName.'). Data below is still shown from legacy; verify the correct application.';
        }

        $rowArray = (array) $row;

        return [
            'viewRow' => $this->buildViewRow($row, $resolvedDistrict),
            'legacy_phase1_id' => $legacyId,
            'tblapplication' => $rowArray,
            'services' => $this->extractServices($rowArray),
            'district_mismatch_warning' => $districtMismatchWarning,
        ];
    }

    private function isPhase1Source(string $source): bool
    {
        return in_array(mb_strtolower(trim($source)), ['legacy_phase1', 'rbiphase1'], true);
    }

    private function legacyAvailable(): bool
    {
        if ((string) config('database.connections.legacy_phase1.database', '') === '') {
            return false;
        }

        try {
            return Schema::connection('legacy_phase1')->hasTable('tblapplication');
        } catch (\Throwable) {
            return false;
        }
    }

    private function fetchRowById(int $legacyId): ?object
    {
        return DB::connection('legacy_phase1')
            ->table('tblapplication')
            ->where('ID', $legacyId)
            ->first();
    }

    private function fetchRowBySubmissionFallback(CfaSubmission $submission): ?object
    {
        $appNo = trim((string) ($submission->application_no ?? ''));
        if ($appNo !== '' && ! str_starts_with(mb_strtolower($appNo), 'legacy-p1-')) {
            $byAppNo = DB::connection('legacy_phase1')
                ->table('tblapplication')
                ->where('ApplicationNumber', $appNo)
                ->orderByDesc('ID')
                ->first();
            if ($byAppNo !== null) {
                return $byAppNo;
            }
        }

        $phone = $this->normalizePhone((string) ($submission->phone ?? ''));
        if ($phone === '') {
            return null;
        }

        return DB::connection('legacy_phase1')
            ->table('tblapplication')
            ->where('MobileNumber', 'like', '%'.$phone)
            ->orderByDesc('ID')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    private function buildViewRow(object $row, ?string $resolvedDistrict): array
    {
        $na = '—';
        $onboardRaw = $row->onboard ?? null;

        $partners = $this->collectMarketPartners((array) $row);

        return [
            'application_no' => $this->scalar($row->ApplicationNumber ?? null, $na),
            'applicant_name' => $this->scalar($row->FullName ?? null, $na),
            'phone' => $this->scalar($row->MobileNumber ?? null, $na),
            'application_date' => $this->scalar($row->ApplicationDate ?? null, $na),
            'gender' => $this->scalar($row->gender ?? null, $na),
            'education' => $this->scalar($row->education ?? null, $na),
            'district' => $this->scalar($resolvedDistrict, $na),
            'legacy_district_key' => $this->scalar($row->FatherName ?? null, $na),
            'legacy_region' => $this->scalar($row->hub ?? null, $na),
            'village' => $this->scalar($row->City ?? null, $na),
            'onboard_status' => LegacyPhase1DistrictResolver::onboardLabel(is_string($onboardRaw) ? $onboardRaw : null),
            'application_status' => $this->scalar($row->application_status ?? null, $na),
            'market_linkage' => $partners !== [] ? implode(', ', $partners) : $na,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array{label: string, detail: ?string}>
     */
    private function extractServices(array $row): array
    {
        $services = [];

        /** @var list<array{column: string, label: string, type: string, detail?: string}> $fields */
        $fields = config('legacy_phase1.service_fields', []);

        foreach ($fields as $field) {
            $column = (string) ($field['column'] ?? '');
            $label = (string) ($field['label'] ?? $column);
            $type = (string) ($field['type'] ?? 'yes');
            $value = $row[$column] ?? null;

            if ($type === 'yes') {
                if (! $this->isYes($value)) {
                    continue;
                }
                $detail = null;
                $detailCol = (string) ($field['detail'] ?? '');
                if ($detailCol !== '' && trim((string) ($row[$detailCol] ?? '')) !== '') {
                    $detail = trim((string) $row[$detailCol]);
                }
                $services[] = ['label' => $label, 'detail' => $detail];
            } elseif ($type === 'text') {
                $text = trim((string) ($value ?? ''));
                if ($text === '' || $this->isNo($text)) {
                    continue;
                }
                $services[] = ['label' => $label, 'detail' => $text];
            }
        }

        $partners = $this->collectMarketPartners($row);
        if ($partners !== []) {
            $services[] = [
                'label' => 'Market linkage',
                'detail' => implode(', ', array_slice($partners, 0, 5)).(count($partners) > 5 ? '…' : ''),
            ];
        }

        return $services;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function collectMarketPartners(array $row): array
    {
        $partners = [];
        foreach (['partner1', 'partner2', 'partner3', 'partner4', 'partner5', 'mar_partner'] as $col) {
            $raw = trim((string) ($row[$col] ?? ''));
            if ($raw === '' || $this->isNo($raw)) {
                continue;
            }
            foreach (preg_split('/[,;]+/', $raw) ?: [] as $part) {
                $part = trim($part);
                if ($part !== '' && ! $this->isNo($part) && stripos($part, 'offline') === false && stripos($part, 'no,') !== 0) {
                    $partners[] = $part;
                }
            }
        }

        return array_values(array_unique($partners));
    }

    private function scalar(mixed $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_scalar($value)) {
            return trim((string) $value) !== '' ? trim((string) $value) : $fallback;
        }

        return $fallback;
    }

    private function isYes(mixed $value): bool
    {
        $v = mb_strtolower(trim((string) ($value ?? '')));

        return in_array($v, ['yes', 'y', '1', 'true'], true);
    }

    private function isNo(string $value): bool
    {
        $v = mb_strtolower(trim($value));

        return in_array($v, ['no', 'n', '0', 'false', 'na', 'n/a'], true);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return strlen($digits) >= 10 ? substr($digits, -10) : '';
    }

    private function normDistrict(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');

        return mb_strtolower($s);
    }
}

<?php

namespace App\Services;

use App\Models\CfaSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CfaPhoneRegistryService
{
    /**
     * Check a mobile number against the current MIS and the two legacy phases.
     *
     * @return array{available: bool, duplicate: ?array<string, mixed>, unavailable_sources: list<string>}
     */
    public function inspect(string $phone, ?int $ignoreCfaId = null): array
    {
        $phone = $this->normalize($phone);
        $duplicate = $this->currentDuplicate($phone, $ignoreCfaId);
        $unavailable = [];

        if ($duplicate === null) {
            $legacy = $this->phase2Duplicate($phone);
            $duplicate = $legacy['duplicate'];
            if (! $legacy['available']) {
                $unavailable[] = 'Phase 2';
            }
        }

        if ($duplicate === null) {
            $phase1 = $this->phase1Duplicate($phone);
            $duplicate = $phase1['duplicate'];
            if (! $phase1['available']) {
                $unavailable[] = 'Phase 1';
            }
        }

        return [
            'available' => $duplicate === null && $unavailable === [],
            'duplicate' => $duplicate,
            'unavailable_sources' => $unavailable,
        ];
    }

    public function assertAvailableForNewCfa(string $phone): void
    {
        $this->assertAvailable($phone);
    }

    public function assertAvailable(string $phone, ?int $ignoreCfaId = null): void
    {
        $result = $this->inspect($phone, $ignoreCfaId);

        if ($result['duplicate'] !== null) {
            $duplicate = $result['duplicate'];
            $details = array_filter([
                $duplicate['name'] ?? null,
                $duplicate['application_no'] ?? null,
                $duplicate['phase'] ?? null,
            ]);

            throw ValidationException::withMessages([
                'phone' => 'This mobile number is already registered'.($details ? ' ('.implode(' · ', $details).')' : '').'.',
            ]);
        }

        if ($result['unavailable_sources'] !== []) {
            throw ValidationException::withMessages([
                'phone' => 'The mobile number could not be verified against '.implode(' and ', $result['unavailable_sources']).'. Please try again later or contact the State Admin.',
            ]);
        }
    }

    /** @return array<string, mixed>|null */
    private function currentDuplicate(string $phone, ?int $ignoreCfaId): ?array
    {
        $query = CfaSubmission::query()->where('phone', $phone);
        if ($ignoreCfaId !== null) {
            $query->whereKeyNot($ignoreCfaId);
        }

        $row = $query->with('fiscalYear:id,name')->orderByDesc('id')->first();
        if (! $row) {
            return null;
        }

        return [
            'name' => $row->applicant_name ?: null,
            'application_no' => $row->application_no ?: null,
            'phase' => $row->source === 'legacy_phase2' ? 'Legacy Phase 2' : 'Current MUY',
            'fy' => $row->fiscalYear?->name,
            'source' => 'cfa_submissions',
        ];
    }

    /** @return array{available: bool, duplicate: ?array<string, mixed>} */
    private function phase2Duplicate(string $phone): array
    {
        if (trim((string) config('database.connections.legacy.database', '')) === '') {
            return ['available' => false, 'duplicate' => null];
        }

        try {
            $row = DB::connection('legacy')
                ->table('rbi_applicant_details as d')
                ->leftJoin('rbi_applications as a', 'a.id', '=', 'd.application_id')
                ->where('d.phone', $phone)
                ->select(['d.application_id', 'd.applicant_name', 'a.application_no'])
                ->orderByDesc('d.application_id')
                ->first();
        } catch (Throwable) {
            return ['available' => false, 'duplicate' => null];
        }

        return [
            'available' => true,
            'duplicate' => $row ? [
                'name' => $row->applicant_name ?: null,
                'application_no' => $row->application_no ?: null,
                'phase' => 'Legacy Phase 2',
                'fy' => '2025-26',
                'source' => 'rbi_applicant_details',
            ] : null,
        ];
    }

    /** @return array{available: bool, duplicate: ?array<string, mixed>} */
    private function phase1Duplicate(string $phone): array
    {
        if (trim((string) config('database.connections.legacy_phase1.database', '')) === '') {
            return ['available' => false, 'duplicate' => null];
        }

        try {
            $row = DB::connection('legacy_phase1')
                ->table('tblapplication')
                ->where('MobileNumber', $phone)
                ->select(['ID', 'ApplicationNumber', 'FullName'])
                ->orderByDesc('ID')
                ->first();
        } catch (Throwable) {
            return ['available' => false, 'duplicate' => null];
        }

        return [
            'available' => true,
            'duplicate' => $row ? [
                'name' => $row->FullName ?: null,
                'application_no' => $row->ApplicationNumber ?: null,
                'phase' => 'Legacy Phase 1',
                'fy' => '2024-25',
                'source' => 'tblapplication',
            ] : null,
        ];
    }

    private function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}

<?php

namespace App\Services;

class BlockWorkshopParticipantRowsService
{
    public const MAX_ROWS = 500;

    /**
     * Build or resize participant rows to match male + female counts.
     *
     * @param  list<array<string, mixed>>|null  $existing
     * @return list<array<string, mixed>>
     */
    public function syncRowCount(
        ?array $existing,
        int $maleCount,
        int $femaleCount,
        string $districtName,
        string $blockName,
        ?int $defaultGramPanchayatId,
        ?string $defaultGramPanchayatName,
    ): array {
        $targetTotal = min(self::MAX_ROWS, max(0, $maleCount + $femaleCount));
        $existing = is_array($existing) ? $existing : [];
        $rows = [];

        for ($i = 0; $i < $targetTotal; $i++) {
            $prev = isset($existing[$i]) && is_array($existing[$i]) ? $existing[$i] : [];
            $defaultGender = $i < $maleCount ? 'M' : ($i < $maleCount + $femaleCount ? 'F' : '');

            $rows[] = $this->normalizeRow(
                $prev,
                $i + 1,
                $districtName,
                $blockName,
                $defaultGramPanchayatId,
                $defaultGramPanchayatName,
                $defaultGender,
            );
        }

        return $rows;
    }

    /**
     * @param  list<mixed>|null  $raw
     * @return list<array<string, mixed>>
     */
    public function sanitizeIncoming(?array $raw, int $expectedCount): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        $limit = min(self::MAX_ROWS, max(0, $expectedCount));

        for ($i = 0; $i < $limit; $i++) {
            $item = isset($raw[$i]) && is_array($raw[$i]) ? $raw[$i] : [];
            $out[] = $this->normalizeRow(
                $item,
                $i + 1,
                (string) ($item['district_name'] ?? ''),
                (string) ($item['block_name'] ?? ''),
                isset($item['gram_panchayat_id']) ? (int) $item['gram_panchayat_id'] : null,
                (string) ($item['gram_panchayat_name'] ?? ''),
                (string) ($item['gender'] ?? ''),
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(
        array $row,
        int $sr,
        string $districtName,
        string $blockName,
        ?int $defaultGramPanchayatId,
        ?string $defaultGramPanchayatName,
        string $defaultGender = '',
    ): array {
        $gender = strtoupper(trim((string) ($row['gender'] ?? $defaultGender)));
        if (! in_array($gender, ['M', 'F'], true)) {
            $gender = '';
        }

        $gpId = isset($row['gram_panchayat_id']) && (int) $row['gram_panchayat_id'] > 0
            ? (int) $row['gram_panchayat_id']
            : $defaultGramPanchayatId;

        $gpName = trim((string) ($row['gram_panchayat_name'] ?? ''));
        if ($gpName === '' && $defaultGramPanchayatName !== null) {
            $gpName = trim($defaultGramPanchayatName);
        }

        $mobile = preg_replace('/\D+/', '', (string) ($row['mobile'] ?? ''));

        return [
            'sr' => $sr,
            'name' => mb_substr(trim((string) ($row['name'] ?? '')), 0, 191),
            'mobile' => mb_substr($mobile, 0, 15),
            'gender' => $gender,
            'district_name' => mb_substr(trim($districtName), 0, 191),
            'block_name' => mb_substr(trim($blockName), 0, 191),
            'gram_panchayat_id' => $gpId > 0 ? $gpId : null,
            'gram_panchayat_name' => mb_substr($gpName, 0, 191),
        ];
    }
}

<?php

namespace App\Support;

final class StakeholderConsultationWorkshopOptions
{
    /** @return array<string, string> */
    public static function lineDepartments(): array
    {
        return [
            'agriculture' => 'Agriculture',
            'tourism' => 'Tourism',
            'rural_development' => 'Rural Development',
            'usrlm' => 'USRLM',
            'msme' => 'MSME / Industry',
            'horticulture' => 'Horticulture',
            'animal_husbandry' => 'Animal Husbandry',
            'forest' => 'Forest',
            'education' => 'Education',
            'other' => 'Other',
        ];
    }

    public static function lineDepartmentLabel(string $key): string
    {
        return self::lineDepartments()[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /** @return array<string, string> */
    public static function stakeholderTypes(): array
    {
        return [
            'line_department' => 'Line department officials',
            'reap' => 'REAP',
            'usrlm' => 'USRLM',
            'incubatees' => 'Incubatees',
            'cbos_shgs' => 'CBOs / SHGs',
            'academia' => 'Academia',
            'other' => 'Other',
        ];
    }

    public static function stakeholderTypeLabel(string $key): string
    {
        return self::stakeholderTypes()[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
}

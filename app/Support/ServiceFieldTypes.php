<?php

namespace App\Support;

/**
 * Central registry for every field type that can appear in a Service's
 * `field_schema`. Anything that wants to render, validate, or format a
 * schema field should consult this registry so behaviour stays consistent
 * across the admin schema builder, the staff submission form, the SPOC
 * review view, and the incubatee-facing display.
 *
 * A schema is a JSON array of rows. Each row is:
 *   [
 *     'key'      => 'gstin',               // unique within the schema, snake_case
 *     'label'    => 'GSTIN',               // human label shown above the input
 *     'type'     => 'text',                // one of TYPES keys
 *     'required' => true,                  // whether the submitter must fill it
 *     'help'     => '15 char GST number',  // optional helper text under the field
 *     'options'  => [                      // only for select/multiselect
 *       ['value' => 'regular',    'label' => 'Regular'],
 *       ['value' => 'composition','label' => 'Composition'],
 *     ],
 *     'min'      => 0,                     // optional bound (number/amount/text len)
 *     'max'      => 191,                   // optional upper bound
 *   ]
 */
class ServiceFieldTypes
{
    public const TEXT = 'text';

    public const TEXTAREA = 'textarea';

    public const NUMBER = 'number';

    public const AMOUNT = 'amount';

    public const DATE = 'date';

    public const URL = 'url';

    public const EMAIL = 'email';

    public const PHONE = 'phone';

    public const SELECT = 'select';

    public const RADIO = 'radio';

    public const MULTISELECT = 'multiselect';

    public const CHECKBOX = 'checkbox';

    public const FILE = 'file';

    /**
     * Master registry. `supports_options` tells the builder UI whether to
     * show the "options" editor for this type.
     *
     * @return array<string, array{label:string, input:string, supports_options:bool, description:string}>
     */
    public static function all(): array
    {
        return [
            self::TEXT => [
                'label' => 'Short text',
                'input' => 'text',
                'supports_options' => false,
                'description' => 'Single-line text (name, reference number, GSTIN).',
            ],
            self::TEXTAREA => [
                'label' => 'Long text',
                'input' => 'textarea',
                'supports_options' => false,
                'description' => 'Multi-line notes or remarks.',
            ],
            self::NUMBER => [
                'label' => 'Number',
                'input' => 'number',
                'supports_options' => false,
                'description' => 'Integer or decimal (count, units).',
            ],
            self::AMOUNT => [
                'label' => 'Amount (₹)',
                'input' => 'amount',
                'supports_options' => false,
                'description' => 'Rupee amount — displayed as ₹1,23,456.',
            ],
            self::DATE => [
                'label' => 'Date',
                'input' => 'date',
                'supports_options' => false,
                'description' => 'A single calendar date.',
            ],
            self::URL => [
                'label' => 'URL / link',
                'input' => 'url',
                'supports_options' => false,
                'description' => 'Full web link (https://…). Rendered as a clickable link.',
            ],
            self::EMAIL => [
                'label' => 'Email',
                'input' => 'email',
                'supports_options' => false,
                'description' => 'Email address.',
            ],
            self::PHONE => [
                'label' => 'Phone (10-digit)',
                'input' => 'tel',
                'supports_options' => false,
                'description' => '10-digit Indian mobile number.',
            ],
            self::SELECT => [
                'label' => 'Dropdown (pick one)',
                'input' => 'select',
                'supports_options' => true,
                'description' => 'Single choice from a fixed list of options.',
            ],
            self::RADIO => [
                'label' => 'Radio (pick one)',
                'input' => 'radio',
                'supports_options' => true,
                'description' => 'Single choice shown as radio buttons.',
            ],
            self::MULTISELECT => [
                'label' => 'Multi-select (pick many)',
                'input' => 'multiselect',
                'supports_options' => true,
                'description' => 'Multiple choices from a fixed list.',
            ],
            self::CHECKBOX => [
                'label' => 'Checkbox (yes / no)',
                'input' => 'checkbox',
                'supports_options' => false,
                'description' => 'Single yes/no confirmation.',
            ],
            self::FILE => [
                'label' => 'File upload',
                'input' => 'file',
                'supports_options' => false,
                'description' => 'Single supporting file upload (pdf/image).',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function isValid(string $type): bool
    {
        return array_key_exists($type, self::all());
    }

    public static function supportsOptions(string $type): bool
    {
        return (bool) (self::all()[$type]['supports_options'] ?? false);
    }

    public static function label(string $type): string
    {
        return (string) (self::all()[$type]['label'] ?? $type);
    }

    /**
     * Normalise a raw schema array coming from user input or DB into a
     * predictable shape. Invalid rows are silently dropped so legacy data
     * doesn't break the admin UI or the staff form.
     *
     * @param  mixed  $raw
     * @return list<array<string, mixed>>
     */
    public static function normalizeSchema($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        $seenKeys = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rawKey = $row['key'] ?? $row['id'] ?? $row['field_id'] ?? '';
            $key = is_string($rawKey) ? trim($rawKey) : '';

            $rawLabel = $row['label'] ?? $row['title'] ?? $row['name'] ?? '';
            $label = is_string($rawLabel) ? trim($rawLabel) : '';

            $rawType = isset($row['type']) && is_string($row['type']) ? strtolower(trim($row['type'])) : '';
            $typeAliasMap = [
                'short_text' => self::TEXT,
                'long_text' => self::TEXTAREA,
                'dropdown' => self::SELECT,
                'multi_select' => self::MULTISELECT,
                'date_picker' => self::DATE,
                'yes_no' => self::CHECKBOX,
                'boolean' => self::CHECKBOX,
                'file_upload' => self::FILE,
            ];
            $type = $typeAliasMap[$rawType] ?? $rawType;

            if ($key === '' && $label !== '') {
                $key = $label;
            }

            if ($key === '' || $label === '' || ! self::isValid($type)) {
                continue;
            }

            $key = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $key));
            if ($key === '' || isset($seenKeys[$key])) {
                continue;
            }
            $seenKeys[$key] = true;

            $normalised = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'required' => ! empty($row['required']),
            ];

            if (isset($row['help']) && is_string($row['help']) && $row['help'] !== '') {
                $normalised['help'] = trim($row['help']);
            }

            foreach (['min', 'max'] as $bound) {
                if (isset($row[$bound]) && is_numeric($row[$bound])) {
                    $normalised[$bound] = (int) $row[$bound];
                }
            }

            if (self::supportsOptions($type)) {
                $options = [];
                $rawOptions = $row['options'] ?? [];
                if (is_array($rawOptions)) {
                    foreach ($rawOptions as $opt) {
                        if (is_string($opt)) {
                            $s = trim($opt);
                            if ($s !== '') {
                                $options[] = ['value' => $s, 'label' => $s];
                            }

                            continue;
                        }
                        if (! is_array($opt)) {
                            continue;
                        }
                        $v = isset($opt['value']) && is_scalar($opt['value']) ? (string) $opt['value'] : '';
                        $l = isset($opt['label']) && is_string($opt['label']) ? trim($opt['label']) : '';
                        $v = trim($v);
                        if ($v === '' || $l === '') {
                            continue;
                        }
                        $options[] = ['value' => $v, 'label' => $l];
                    }
                }
                $normalised['options'] = $options;
            }

            if (isset($row['visible_if']) && is_array($row['visible_if'])) {
                $visibleField = isset($row['visible_if']['field']) && is_string($row['visible_if']['field'])
                    ? trim($row['visible_if']['field'])
                    : '';
                $visibleValue = $row['visible_if']['value'] ?? null;
                if ($visibleField !== '' && is_scalar($visibleValue)) {
                    $visibleField = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $visibleField));
                    if ($visibleField !== '' && $visibleField !== $key) {
                        $normalised['visible_if'] = [
                            'field' => $visibleField,
                            'value' => (string) $visibleValue,
                        ];
                    }
                }
            }

            $out[] = $normalised;
        }

        return $out;
    }
}

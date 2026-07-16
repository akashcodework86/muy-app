<?php

namespace App\Services;

use App\Support\ServiceFieldTypes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates user-submitted payloads against a Service's `field_schema`.
 *
 * Call site:
 *   $clean = app(SchemaValidator::class)->validate($schema, $rawPayload);
 *
 * Behaviour:
 *   - Unknown keys in the payload are silently dropped (defence-in-depth
 *     against form tampering).
 *   - Required fields that are blank raise a ValidationException.
 *   - Type-specific rules (url / email / phone / date / amount / options)
 *     are applied.
 *   - Returned array is keyed by schema key and contains only canonical,
 *     trimmed values ready to be persisted into service_cases.payload.
 */
class SchemaValidator
{
    /**
     * @param  list<array<string, mixed>>  $schema
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $schema, array $payload): array
    {
        $schema = ServiceFieldTypes::normalizeSchema($schema);
        if ($schema === []) {
            return [];
        }

        $rules = [];
        $attributes = [];
        $input = [];

        foreach ($schema as $field) {
            $key = $field['key'];
            $attributes[$key] = $field['label'];

            $value = $payload[$key] ?? null;
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    $value = null;
                }
            }
            $input[$key] = $value;
        }

        foreach ($schema as $field) {
            $key = $field['key'];
            $type = $field['type'];
            $isVisible = $this->isVisibleField($field, $input);
            $required = $isVisible && ! empty($field['required']);
            if (! $isVisible) {
                $input[$key] = null;
            }

            $rules[$key] = $this->rulesFor($field, $required);
            if ($type === ServiceFieldTypes::MULTISELECT) {
                $allowed = $this->optionValues($field);
                if ($allowed !== []) {
                    $rules[$key.'.*'] = Rule::in($allowed);
                }
            }
        }

        $validated = Validator::make($input, $rules, [], $attributes)->validate();

        $out = [];
        foreach ($schema as $field) {
            $key = $field['key'];
            if (! array_key_exists($key, $validated)) {
                continue;
            }
            $v = $validated[$key];
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            if (! $this->isVisibleField($field, $input)) {
                continue;
            }

            $out[$key] = $this->canonicalize($field, $v);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private function rulesFor(array $field, bool $required): array
    {
        $type = $field['type'];
        $rules = [$required ? 'required' : 'nullable'];

        switch ($type) {
            case ServiceFieldTypes::TEXT:
                $rules[] = 'string';
                $rules[] = 'max:'.(int) ($field['max'] ?? 191);
                break;

            case ServiceFieldTypes::TEXTAREA:
                $rules[] = 'string';
                $rules[] = 'max:'.(int) ($field['max'] ?? 2000);
                break;

            case ServiceFieldTypes::NUMBER:
                $rules[] = 'numeric';
                if (isset($field['min'])) {
                    $rules[] = 'min:'.(int) $field['min'];
                }
                if (isset($field['max'])) {
                    $rules[] = 'max:'.(int) $field['max'];
                }
                break;

            case ServiceFieldTypes::AMOUNT:
                $rules[] = 'numeric';
                $rules[] = 'min:'.(int) ($field['min'] ?? 0);
                if (isset($field['max'])) {
                    $rules[] = 'max:'.(int) $field['max'];
                }
                break;

            case ServiceFieldTypes::DATE:
                $rules[] = 'date';
                break;

            case ServiceFieldTypes::URL:
                $rules[] = 'url';
                $rules[] = 'max:500';
                break;

            case ServiceFieldTypes::EMAIL:
                $rules[] = 'email';
                $rules[] = 'max:191';
                break;

            case ServiceFieldTypes::PHONE:
                $rules[] = 'string';
                $rules[] = 'regex:/^[6-9]\d{9}$/';
                break;

            case ServiceFieldTypes::SELECT:
            case ServiceFieldTypes::RADIO:
                $allowed = $this->optionValues($field);
                if ($allowed !== []) {
                    $rules[] = Rule::in($allowed);
                }
                break;

            case ServiceFieldTypes::MULTISELECT:
                $allowed = $this->optionValues($field);
                $rules = [$required ? 'required' : 'nullable', 'array'];
                if ($allowed !== []) {
                    $rules[] = 'min:'.($required ? 1 : 0);
                }
                break;

            case ServiceFieldTypes::CHECKBOX:
                $rules = $required
                    ? ['accepted']
                    : ['nullable', 'boolean'];
                break;

            case ServiceFieldTypes::FILE:
                // Stored as original filename after upload is accepted.
                $rules[] = 'string';
                $rules[] = 'max:255';
                break;
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private function optionValues(array $field): array
    {
        $out = [];
        foreach ((array) ($field['options'] ?? []) as $opt) {
            if (is_array($opt) && isset($opt['value'])) {
                $out[] = (string) $opt['value'];
            }
        }

        return $out;
    }

    /**
     * Convert validated input into the canonical shape we persist.
     * Mostly a no-op, except for checkbox -> bool and amount/number
     * -> numeric coercion.
     *
     * @param  array<string, mixed>  $field
     */
    private function canonicalize(array $field, mixed $v): mixed
    {
        switch ($field['type']) {
            case ServiceFieldTypes::CHECKBOX:
                return (bool) $v;

            case ServiceFieldTypes::NUMBER:
            case ServiceFieldTypes::AMOUNT:
                if (is_numeric($v)) {
                    return 0 + $v;
                }

                return $v;

            case ServiceFieldTypes::MULTISELECT:
                if (! is_array($v)) {
                    return [];
                }
                $allowed = $this->optionValues($field);
                $v = array_values(array_unique(array_map('strval', $v)));
                if ($allowed !== []) {
                    $v = array_values(array_intersect($v, $allowed));
                }

                return $v;

            default:
                return is_string($v) ? trim($v) : $v;
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $input
     */
    private function isVisibleField(array $field, array $input): bool
    {
        $cond = $field['visible_if'] ?? null;
        if (! is_array($cond)) {
            return true;
        }
        $depField = isset($cond['field']) ? (string) $cond['field'] : '';
        if ($depField === '' || ! array_key_exists($depField, $input)) {
            return empty($cond['any']);
        }
        $actual = $input[$depField];
        if (! empty($cond['any'])) {
            if (is_array($actual)) {
                return array_filter($actual, static fn ($v) => $v !== null && $v !== '') !== [];
            }

            return $actual !== null && $actual !== '' && $actual !== false;
        }
        $depValue = isset($cond['value']) ? (string) $cond['value'] : '';
        if (is_array($actual)) {
            return in_array($depValue, array_map('strval', $actual), true);
        }

        return (string) ($actual ?? '') === $depValue;
    }
}

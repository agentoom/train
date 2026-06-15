<?php

namespace App\Services\Dataset;

use App\Contracts\Dataset\DatasetValidatorInterface;
use Illuminate\Support\Facades\Log;

class DatasetValidationService implements DatasetValidatorInterface
{
    /**
     * Validate a single row against an optional JSON schema.
     *
     * @param  array<string, mixed>       $row
     * @param  array<string, mixed>|null  $schema
     */
    public function validate(array $payload, ?array $schema): bool
    {
        if ($schema === null) {
            return ! empty($payload);
        }

        return $this->validateAgainstSchema($payload, $schema);
    }

    /**
     * Validate multiple rows, returning only valid ones.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>|null         $schema
     * @return array<int, array<string, mixed>>
     */
    public function filterValidRows(array $rows, ?array $schema = null): array
    {
        return array_values(array_filter($rows, fn (array $row) => $this->validate($row, $schema)));
    }

    /**
     * Basic JSON schema validation (type + required properties).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $schema
     */
    private function validateAgainstSchema(array $data, array $schema): bool
    {
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $requiredKey) {
                if (! array_key_exists($requiredKey, $data)) {
                    Log::debug('Dataset row missing required key', ['key' => $requiredKey]);

                    return false;
                }
            }
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $propSchema) {
                if (! array_key_exists($key, $data)) {
                    continue;
                }

                if (isset($propSchema['type']) && ! $this->checkType($data[$key], $propSchema['type'])) {
                    Log::debug('Dataset row property type mismatch', ['key' => $key, 'expected' => $propSchema['type']]);

                    return false;
                }
            }
        }

        return true;
    }

    private function checkType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer', 'int' => is_int($value),
            'number' => is_numeric($value),
            'boolean', 'bool' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value) || is_object($value),
            'null' => is_null($value),
            default => true,
        };
    }
}

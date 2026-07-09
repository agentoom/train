<?php

namespace App\Services\Dataset;

use App\Models\DatasetRow;

class HashDeduplicationService
{
    /**
     * Generate a normalized SHA-256 content hash for a row payload.
     *
     * Normalization: lowercase, trim, stable key ordering, whitespace normalization.
     *
     * @param  array<string, mixed>  $payload
     */
    public function hash(array $payload): string
    {
        return hash('sha256', $this->normalize($payload));
    }

    /**
     * Check whether a content hash already exists in the given dataset version.
     */
    public function isDuplicate(int $datasetVersionId, string $contentHash): bool
    {
        return DatasetRow::where('dataset_version_id', $datasetVersionId)
            ->where('content_hash', $contentHash)
            ->where('is_duplicate', false)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalize(array $payload): string
    {
        return json_encode($this->normalizeValue($payload)) ?: '';
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);

            if ($isAssoc) {
                ksort($value);

                return array_map(fn ($v) => $this->normalizeValue($v), $value);
            }

            return array_map(fn ($v) => $this->normalizeValue($v), $value);
        }

        if (is_string($value)) {
            return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
        }

        return $value;
    }
}

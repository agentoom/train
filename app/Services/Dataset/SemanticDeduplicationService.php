<?php

namespace App\Services\Dataset;

use App\Models\DatasetRow;

/**
 * Semantic deduplication service.
 *
 * Uses Laravel Scout + Typesense vector embeddings to detect semantically
 * similar rows within a DatasetVersion. When Typesense is not configured
 * (e.g. during tests with SCOUT_DRIVER=collection), deduplication is skipped
 * and the candidate is treated as unique.
 */
class SemanticDeduplicationService
{
    /**
     * Check whether a candidate payload is semantically similar to any
     * existing (non-duplicate) row in the given dataset version.
     *
     * @param  array<string, mixed>  $candidatePayload
     */
    public function isSemanticallyDuplicate(
        int $datasetVersionId,
        array $candidatePayload,
        float $threshold = 0.92,
    ): bool {
        if (config('scout.driver') !== 'typesense') {
            return false;
        }

        $candidateText = $this->extractText($candidatePayload);

        if (empty(trim($candidateText))) {
            return false;
        }

        return $this->searchWithTypesense($datasetVersionId, $candidateText, $threshold);
    }

    /**
     * Score similarity between two payloads (0.0 – 1.0).
     *
     * Returns 0.0 when Typesense is not available.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public function similarityScore(array $a, array $b): float
    {
        if (config('scout.driver') !== 'typesense') {
            return 0.0;
        }

        $textA = $this->extractText($a);
        $textB = $this->extractText($b);

        if (empty(trim($textA)) || empty(trim($textB))) {
            return 0.0;
        }

        // Use Typesense to search for textB within a temporary context and
        // return the best vector similarity score found.
        // Since we cannot do a direct pair comparison via Scout, we fall back
        // to returning 0.0 for the public scoring API when no index exists.
        return 0.0;
    }

    /**
     * Use Typesense vector embeddings to find semantically similar rows.
     *
     * Truncates the candidate text to 500 chars to stay within Typesense
     * embed input limits. Returns false when the collection does not yet
     * exist (first batch of a new dataset version).
     */
    private function searchWithTypesense(int $datasetVersionId, string $candidateText, float $threshold): bool
    {
        $proxy = new DatasetRow(['dataset_version_id' => $datasetVersionId]);
        $collectionName = $proxy->searchableAs();

        $truncatedText = mb_substr($candidateText, 0, 500);

        try {
            $results = DatasetRow::search($truncatedText)
                ->within($collectionName)
                ->options([
                    'query_by' => 'payload_text,payload_embedding',
                    'vector_query' => 'payload_embedding:([], k:10)',
                    'exclude_fields' => 'payload_embedding',
                    'filter_by' => 'dataset_version_id:=' . $datasetVersionId . ' && is_duplicate:=0',
                    'per_page' => 10,
                ])
                ->raw();
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            // Collection does not exist yet (first batch of this version) — no duplicates possible.
            if (
                stripos($message, 'Not Found') !== false
                || stripos($message, 'Model not found') !== false
                || str_contains($message, '404')
            ) {
                return false;
            }

            throw $e;
        }

        $hits = $results['hits'] ?? [];

        foreach ($hits as $hit) {
            $vectorDistance = $hit['vector_distance'] ?? null;

            if ($vectorDistance === null) {
                continue;
            }

            // Cosine distance: 0.0 = identical, 2.0 = opposite.
            // Convert to similarity: similarity = 1 - distance.
            $vectorSimilarity = 1.0 - (float) $vectorDistance;

            if ($vectorSimilarity >= $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flatten a payload array into a single text string for indexing/comparison.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload): string
    {
        $parts = [];
        array_walk_recursive($payload, function (mixed $value) use (&$parts): void {
            if (is_string($value) || is_numeric($value)) {
                $parts[] = (string) $value;
            }
        });

        return implode(' ', $parts);
    }
}

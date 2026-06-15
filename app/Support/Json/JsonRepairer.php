<?php

namespace App\Support\Json;

final class JsonRepairer
{
    /**
     * Attempt to repair and decode malformed JSON from LLM output.
     *
     * @return array<int|string, mixed>|null Returns decoded array or null on failure.
     */
    public function repair(string $raw): ?array
    {
        $cleaned = $this->extractJsonContent($raw);

        $decoded = json_decode($cleaned, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $repaired = $this->applyRepairs($cleaned);

        $decoded = json_decode($repaired, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

    private function extractJsonContent(string $raw): string
    {
        // Strip markdown code fences
        $raw = preg_replace('/^```(?:json)?\s*/m', '', $raw) ?? $raw;
        $raw = preg_replace('/\s*```$/m', '', $raw) ?? $raw;

        // Extract first JSON object or array
        if (preg_match('/(\{.*\}|\[.*\])/s', $raw, $matches)) {
            return $matches[1];
        }

        return trim($raw);
    }

    private function applyRepairs(string $json): string
    {
        // Remove trailing commas before closing braces/brackets
        $json = preg_replace('/,\s*([\}\]])/s', '$1', $json) ?? $json;

        // Replace single quotes with double quotes (naive)
        $json = preg_replace("/(?<![\\\\])'/", '"', $json) ?? $json;

        return $json;
    }
}

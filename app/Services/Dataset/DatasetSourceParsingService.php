<?php

namespace App\Services\Dataset;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DatasetSourceParsingService
{
    private const MAX_SAMPLE_ROWS = 500;

    /**
     * Parse an uploaded file and return normalised rows + metadata.
     *
     * @return array{rows: array<int, array<string, mixed>>, schema: array<string, mixed>|null, metadata: array<string, mixed>}
     */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = match ($extension) {
            'json'        => $this->parseJson($file),
            'jsonl'       => $this->parseJsonl($file),
            'csv'         => $this->parseCsv($file),
            'txt'         => $this->parseTxt($file),
            'md', 'markdown' => $this->parseTxt($file),
            'xlsx'        => $this->parseXlsx($file),
            default       => $this->parseTxt($file),
        };

        $rows = array_slice($rows, 0, self::MAX_SAMPLE_ROWS);

        $schema = $this->inferSchema($rows);
        $metadata = $this->buildMetadata($rows, $extension);

        return [
            'rows'     => $rows,
            'schema'   => $schema,
            'metadata' => $metadata,
        ];
    }

    /**
     * Store the uploaded file and return the storage path.
     */
    public function store(UploadedFile $file): string
    {
        return $file->store('dataset-sources', 'local');
    }

    /**
     * Load rows from a stored file path (for augmentation sampling).
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadRows(string $filePath, string $sourceType): array
    {
        if (! Storage::disk('local')->exists($filePath)) {
            return [];
        }

        $content = Storage::disk('local')->get($filePath);

        if ($content === null || $content === false) {
            return [];
        }

        // Write to a temp file so we can use file-based parsers
        $tmp = tempnam(sys_get_temp_dir(), 'ds_src_');
        file_put_contents($tmp, $content);

        try {
            $rows = match ($sourceType) {
                'json'  => $this->parseJsonContent($content),
                'jsonl' => $this->parseJsonlContent($content),
                'csv'   => $this->parseCsvFromPath($tmp),
                default => $this->parseTxtContent($content),
            };
        } finally {
            @unlink($tmp);
        }

        return array_slice($rows, 0, self::MAX_SAMPLE_ROWS);
    }

    /**
     * Sample up to $count rows from the loaded rows, rotating by batch index.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function sampleRows(array $rows, int $count, int $batchIndex = 0): array
    {
        if (empty($rows)) {
            return [];
        }

        $total = count($rows);
        $count = min($count, $total);
        $offset = ($batchIndex * $count) % $total;

        return array_values(array_slice(array_merge($rows, $rows), $offset, $count));
    }

    // ─── Parsers ─────────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function parseJson(UploadedFile $file): array
    {
        return $this->parseJsonContent($file->get() ?? '');
    }

    /** @return array<int, array<string, mixed>> */
    private function parseJsonContent(string $content): array
    {
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return [];
        }

        // Root array of objects
        if (isset($decoded[0]) && is_array($decoded[0])) {
            return $decoded;
        }

        // Single object — wrap it
        if (! empty($decoded) && ! isset($decoded[0])) {
            return [$decoded];
        }

        return $decoded;
    }

    /** @return array<int, array<string, mixed>> */
    private function parseJsonl(UploadedFile $file): array
    {
        return $this->parseJsonlContent($file->get() ?? '');
    }

    /** @return array<int, array<string, mixed>> */
    private function parseJsonlContent(string $content): array
    {
        $rows = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function parseCsv(UploadedFile $file): array
    {
        return $this->parseCsvFromPath($file->getRealPath());
    }

    /** @return array<int, array<string, mixed>> */
    private function parseCsvFromPath(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $headers = null;

        while (($line = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = $line;

                continue;
            }

            if (count($line) === count($headers)) {
                $rows[] = array_combine($headers, $line);
            }
        }

        fclose($handle);

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function parseTxt(UploadedFile $file): array
    {
        return $this->parseTxtContent($file->get() ?? '');
    }

    /** @return array<int, array<string, mixed>> */
    private function parseTxtContent(string $content): array
    {
        $rows = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line !== '') {
                $rows[] = ['text' => $line];
            }
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function parseXlsx(UploadedFile $file): array
    {
        // PhpSpreadsheet is not guaranteed to be installed; fall back to CSV-like parsing
        // If PhpSpreadsheet is available it can be injected here in the future.
        Log::warning('DatasetSourceParsingService: xlsx parsing not available, treating as text', [
            'filename' => $file->getClientOriginalName(),
        ]);

        return $this->parseTxt($file);
    }

    // ─── Schema inference ────────────────────────────────────────────────────

    /**
     * Infer a simple JSON schema from the first few rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function inferSchema(array $rows): ?array
    {
        if (empty($rows)) {
            return null;
        }

        $sample = array_slice($rows, 0, 10);
        $allKeys = [];

        foreach ($sample as $row) {
            foreach (array_keys($row) as $key) {
                $allKeys[$key] = true;
            }
        }

        if (empty($allKeys)) {
            return null;
        }

        $properties = [];

        foreach (array_keys($allKeys) as $key) {
            $types = [];

            foreach ($sample as $row) {
                if (! array_key_exists($key, $row)) {
                    continue;
                }

                $types[] = $this->phpTypeToJsonType($row[$key]);
            }

            $types = array_unique($types);
            $properties[$key] = ['type' => count($types) === 1 ? $types[0] : $types];
        }

        return [
            'type'       => 'object',
            'properties' => $properties,
        ];
    }

    private function phpTypeToJsonType(mixed $value): string
    {
        return match (true) {
            is_null($value)   => 'null',
            is_bool($value)   => 'boolean',
            is_int($value)    => 'integer',
            is_float($value)  => 'number',
            is_array($value)  => 'array',
            default           => 'string',
        };
    }

    // ─── Metadata ────────────────────────────────────────────────────────────

    /**
     * Build metadata summary for the parsed rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildMetadata(array $rows, string $extension): array
    {
        if (empty($rows)) {
            return ['source_type' => $extension, 'row_count' => 0];
        }

        $allKeys = [];
        $fieldValues = [];

        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                $allKeys[$key] = ($allKeys[$key] ?? 0) + 1;

                if (is_string($value) && strlen($value) < 200) {
                    $fieldValues[$key][] = $value;
                }
            }
        }

        // Field distribution: how often each field appears
        $fieldDistribution = [];

        foreach ($allKeys as $key => $count) {
            $fieldDistribution[$key] = round($count / count($rows), 2);
        }

        // Common patterns: top 3 values per field (for small cardinality fields)
        $commonPatterns = [];

        foreach ($fieldValues as $key => $values) {
            $counts = array_count_values($values);
            arsort($counts);
            $top = array_slice(array_keys($counts), 0, 3);

            if (count($top) > 0 && count($top) < 20) {
                $commonPatterns[$key] = $top;
            }
        }

        return [
            'source_type'        => $extension,
            'row_count'          => count($rows),
            'estimated_schema'   => array_keys($allKeys),
            'field_distribution' => $fieldDistribution,
            'common_patterns'    => $commonPatterns,
        ];
    }
}

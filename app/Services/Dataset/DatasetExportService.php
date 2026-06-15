<?php

namespace App\Services\Dataset;

use App\Contracts\Dataset\DatasetExporterInterface;
use App\Enums\ExportFormat;
use App\Models\DatasetRow;
use App\Models\DatasetVersion;
use App\Services\Export\ExportPresetTransformer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatasetExportService implements DatasetExporterInterface
{
    public function __construct(private readonly ExportPresetTransformer $transformer) {}

    public function export(DatasetVersion $version, ExportFormat $format): StreamedResponse
    {
        $filename = sprintf('dataset-v%d-%s.%s', $version->version_number, $version->id, $format->extension());

        return new StreamedResponse(function () use ($version, $format) {
            if ($format->isPreset()) {
                $this->streamPreset($version, $format);
            } else {
                match ($format) {
                    ExportFormat::Json  => $this->streamJson($version),
                    ExportFormat::Jsonl => $this->streamJsonl($version),
                    ExportFormat::Csv   => $this->streamCsv($version),
                };
            }
        }, 200, [
            'Content-Type'        => $format->mimeType(),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Accel-Buffering'   => 'no',
        ]);
    }

    private function streamJson(DatasetVersion $version): void
    {
        echo '[';
        $first = true;

        foreach ($this->rowGenerator($version) as $payload) {
            if (! $first) {
                echo ',';
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            $first = false;
            ob_flush();
            flush();
        }

        echo ']';
        ob_flush();
        flush();
    }

    private function streamJsonl(DatasetVersion $version): void
    {
        foreach ($this->rowGenerator($version) as $payload) {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";
            ob_flush();
            flush();
        }
    }

    private function streamCsv(DatasetVersion $version): void
    {
        $handle = fopen('php://output', 'w');
        $headerWritten = false;

        try {
            foreach ($this->rowGenerator($version) as $payload) {
                if (! is_array($payload)) {
                    continue;
                }

                if (! $headerWritten) {
                    fputcsv($handle, array_keys($payload));
                    $headerWritten = true;
                }

                fputcsv($handle, array_map(
                    fn ($v) => is_array($v) ? json_encode($v) : $v,
                    array_values($payload),
                ));

                ob_flush();
                flush();
            }
        } finally {
            fclose($handle);
        }
    }

    private function streamPreset(DatasetVersion $version, ExportFormat $format): void
    {
        $rows = DatasetRow::where('dataset_version_id', $version->id)
            ->where('is_valid', true)
            ->where('is_duplicate', false)
            ->orderBy('row_index')
            ->cursor();

        foreach ($rows as $row) {
            $transformed = $this->transformer->transform($row, $format);

            if ($transformed === null) {
                continue;
            }

            echo json_encode($transformed, JSON_UNESCAPED_UNICODE) . "\n";
            ob_flush();
            flush();
        }
    }

    /** @return \Generator<array<mixed>> */
    private function rowGenerator(DatasetVersion $version): \Generator
    {
        $rows = DatasetRow::where('dataset_version_id', $version->id)
            ->where('is_valid', true)
            ->orderBy('row_index')
            ->cursor();

        foreach ($rows as $row) {
            yield $row->payload;
        }
    }
}

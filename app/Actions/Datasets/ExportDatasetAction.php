<?php

namespace App\Actions\Datasets;

use App\Enums\ExportFormat;
use App\Models\DatasetProject;
use App\Services\Dataset\DatasetExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDatasetAction
{
    public function __construct(private readonly DatasetExportService $exportService) {}

    public function execute(DatasetProject $project, ExportFormat $format): StreamedResponse
    {
        $version = $project->versions()
            ->latest('version_number')
            ->firstOrFail();

        return $this->exportService->export($version, $format);
    }
}

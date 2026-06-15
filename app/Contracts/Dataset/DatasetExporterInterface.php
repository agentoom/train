<?php

namespace App\Contracts\Dataset;

use App\Enums\ExportFormat;
use App\Models\DatasetVersion;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface DatasetExporterInterface
{
    public function export(DatasetVersion $version, ExportFormat $format): StreamedResponse;
}

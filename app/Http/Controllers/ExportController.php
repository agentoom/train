<?php

namespace App\Http\Controllers;

use App\Actions\Datasets\ExportDatasetAction;
use App\Enums\ExportFormat;
use App\Models\DatasetProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private readonly ExportDatasetAction $exportAction) {}

    public function __invoke(Request $request, DatasetProject $project): StreamedResponse
    {
        Gate::authorize('view', $project);

        $formatValue = $request->query('format', 'json');
        $format = ExportFormat::tryFrom($formatValue);

        if (! $format) {
            abort(422, __('Invalid export format. Allowed values: :formats.', [
                'formats' => implode(', ', array_map(fn (ExportFormat $f) => $f->value, ExportFormat::cases())),
            ]));
        }

        return $this->exportAction->execute($project, $format);
    }
}

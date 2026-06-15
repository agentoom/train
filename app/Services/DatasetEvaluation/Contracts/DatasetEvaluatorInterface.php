<?php

namespace App\Services\DatasetEvaluation\Contracts;

use App\DTOs\DatasetBatchDTO;
use App\DTOs\EvaluatorResultDTO;

interface DatasetEvaluatorInterface
{
    public function evaluate(DatasetBatchDTO $batch): EvaluatorResultDTO;
}

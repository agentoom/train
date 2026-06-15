<?php

namespace Database\Factories;

use App\Models\DatasetEvaluationReport;
use App\Models\DatasetFeedbackReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatasetFeedbackReport>
 */
class DatasetFeedbackReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evaluation_run_id' => DatasetEvaluationReport::factory(),
            'failure_analysis_json' => [
                'failures_by_type' => [],
                'systemic_issues' => [],
                'evaluator_scores' => [],
            ],
            'improvement_signals_json' => [],
            'system_health_score' => $this->faker->randomFloat(1, 40, 100),
        ];
    }
}

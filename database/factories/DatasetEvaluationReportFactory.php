<?php

namespace Database\Factories;

use App\Models\DatasetEvaluationReport;
use App\Models\DatasetVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatasetEvaluationReport>
 */
class DatasetEvaluationReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dataset_version_id' => DatasetVersion::factory(),
            'overall_score' => $this->faker->randomFloat(1, 40, 100),
            'passed' => $this->faker->boolean(),
            'verdict' => $this->faker->sentence(),
            'evaluator_scores' => [],
            'metadata' => [],
            'duplicate_rate' => $this->faker->randomFloat(4, 0, 0.2),
        ];
    }
}

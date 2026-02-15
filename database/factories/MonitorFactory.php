<?php

namespace Database\Factories;

use App\Enums\MonitorMethod;
use App\Models\Monitor;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonitorFactory extends Factory
{
    protected $model = Monitor::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->domainWord(),
            'url' => fake()->url(),
            'method' => MonitorMethod::GET,
            'expected_status_code' => 200,
            'interval' => 1,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withKeyword(string $keyword = 'ok'): static
    {
        return $this->state(fn (array $attributes) => [
            'keyword' => $keyword,
        ]);
    }

    public function withThresholds(int $warning = 1000, int $critical = 3000): static
    {
        return $this->state(fn (array $attributes) => [
            'warning_threshold_ms' => $warning,
            'critical_threshold_ms' => $critical,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\GithubRepository;
use App\Models\SyncTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GithubRepository>
 */
class GithubRepositoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'sync_target_id' => SyncTarget::factory(),
            'github_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'name' => $name,
            'full_name' => fake()->userName().'/'.$name,
            'description' => fake()->optional()->sentence(),
            'url' => 'https://github.com/'.fake()->userName().'/'.$name,
            'language' => fake()->randomElement(['PHP', 'JavaScript', 'TypeScript', 'Go', 'Python', null]),
            'stargazers_count' => fake()->numberBetween(0, 10_000),
            'open_issues_count' => fake()->numberBetween(0, 200),
            'is_archived' => false,
            'github_updated_at' => fake()->dateTimeBetween('-2 years'),
        ];
    }

    /**
     * Indicate that the repository is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
        ]);
    }
}

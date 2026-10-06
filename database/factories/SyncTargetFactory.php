<?php

namespace Database\Factories;

use App\Enums\SyncStatus;
use App\Enums\SyncTargetType;
use App\Models\SyncTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncTarget>
 */
class SyncTargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->userName(),
            'type' => SyncTargetType::User,
            'status' => SyncStatus::Pending,
            'last_synced_at' => null,
            'last_sync_error' => null,
        ];
    }

    /**
     * Indicate that the target is an organization.
     */
    public function organization(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SyncTargetType::Organization,
        ]);
    }

    /**
     * Indicate that the target has already synced successfully.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::Success,
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Indicate that the target failed its last sync.
     */
    public function failed(string $error = 'GitHub nem található felhasználó/organizáció ezzel a névvel.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::Failed,
            'last_sync_error' => $error,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Borrowing>
 */
class BorrowingFactory extends Factory
{
    protected $model = Borrowing::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'status' => Borrowing::STATUS_PENDING,
            'borrowed_at' => null,
            'returned_at' => null,
            'notes' => fake()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Borrowing::STATUS_APPROVED,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Borrowing::STATUS_REJECTED,
        ]);
    }

    public function borrowed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Borrowing::STATUS_BORROWED,
            'borrowed_at' => now(),
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Borrowing::STATUS_RETURNED,
            'borrowed_at' => now()->subDays(3),
            'returned_at' => now(),
        ]);
    }
}

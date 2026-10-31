<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'publisher' => fake()->company(),
            'publication_year' => fake()->numberBetween(1990, 2025),
            'isbn' => fake()->unique()->isbn13(),
            'stock' => fake()->numberBetween(1, 20),
            'rack_location' => 'Rak '.fake()->randomElement(['A', 'B', 'C', 'D']).'-'.fake()->numberBetween(1, 10),
            'rack_location' => 'Rak ' . fake()->randomElement(['A', 'B', 'C', 'D']) . '-' . fake()->numberBetween(1, 10),
            'description' => fake()->paragraph(),
            'cover' => fake()->imageUrl(),
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }
}

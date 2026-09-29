<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BorrowingItem>
 */
class BorrowingItemFactory extends Factory
{
    protected $model = BorrowingItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'borrowing_id' => Borrowing::factory(),
            'book_id' => Book::factory(),
            'quantity' => 1,
        ];
    }
}

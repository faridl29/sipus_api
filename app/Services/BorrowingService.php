<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BorrowingService
{
    /**
     * Create a new borrowing in PENDING status.
     *
     * @param  array<int, array{book_id: int, quantity: int}>  $itemsData
     */
    public function createBorrowing(User $user, array $itemsData, ?string $dueDate = null, ?string $notes = null): Borrowing
    {
        return DB::transaction(function () use ($user, $itemsData, $dueDate, $notes) {
            $borrowing = Borrowing::create([
                'user_id' => $user->id,
                'status' => Borrowing::STATUS_PENDING,
                'due_date' => $dueDate ? Carbon::parse($dueDate) : null,
                'notes' => $notes,
            ]);

            foreach ($itemsData as $item) {
                BorrowingItem::create([
                    'borrowing_id' => $borrowing->id,
                    'book_id' => $item['book_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $borrowing->load(['items.book', 'user']);
        });
    }

    /**
     * Create a direct walk-in borrowing (immediately BORROWED, stock decremented, due_date set to max 3 days).
     *
     * @param  array<int, array{book_id: int, quantity: int}>  $itemsData
     *
     * @throws InsufficientStockException
     */
    public function createDirectBorrowing(
        ?User $user,
        array $itemsData,
        ?string $dueDate = null,
        ?string $borrowerName = null,
        ?string $borrowerPhone = null,
        ?string $borrowerIdentityNumber = null,
        ?string $borrowerType = null,
        ?string $notes = null
    ): Borrowing {
        return DB::transaction(function () use ($user, $itemsData, $dueDate, $borrowerName, $borrowerPhone, $borrowerIdentityNumber, $borrowerType, $notes) {
            $borrowedAt = now();
            $maxDays = Borrowing::getMaxDaysForUser($user);
            $calculatedDueDate = $dueDate ? Carbon::parse($dueDate) : $borrowedAt->copy()->addDays($maxDays);

            $borrowing = Borrowing::create([
                'user_id' => $user?->id,
                'borrower_name' => $borrowerName ?? $user?->name,
                'borrower_phone' => $borrowerPhone,
                'borrower_identity_number' => $borrowerIdentityNumber ?? $user?->identity_number,
                'borrower_type' => $borrowerType ?? ($user ? $user->role : 'guest'),
                'status' => Borrowing::STATUS_BORROWED,
                'borrowed_at' => $borrowedAt,
                'due_date' => $calculatedDueDate,
                'notes' => $notes,
            ]);

            foreach ($itemsData as $item) {
                /** @var Book $book */
                $book = Book::withTrashed()->lockForUpdate()->findOrFail($item['book_id']);

                if ($book->stock < $item['quantity']) {
                    throw new InsufficientStockException($book->title, $book->stock, $item['quantity']);
                }

                $book->decrement('stock', $item['quantity']);

                BorrowingItem::create([
                    'borrowing_id' => $borrowing->id,
                    'book_id' => $item['book_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $borrowing->load(['items.book', 'user']);
        });
    }

    /**
     * Approve a PENDING borrowing.
     *
     * @throws InvalidStatusTransitionException
     */
    public function approveBorrowing(Borrowing $borrowing): Borrowing
    {
        if ($borrowing->status !== Borrowing::STATUS_PENDING) {
            throw new InvalidStatusTransitionException($borrowing->status, Borrowing::STATUS_APPROVED);
        }

        $borrowing->update([
            'status' => Borrowing::STATUS_APPROVED,
        ]);

        return $borrowing->fresh(['items.book', 'user']);
    }

    /**
     * Reject a PENDING borrowing.
     *
     * @throws InvalidStatusTransitionException
     */
    public function rejectBorrowing(Borrowing $borrowing, ?string $notes = null): Borrowing
    {
        if ($borrowing->status !== Borrowing::STATUS_PENDING) {
            throw new InvalidStatusTransitionException($borrowing->status, Borrowing::STATUS_REJECTED);
        }

        $updateData = ['status' => Borrowing::STATUS_REJECTED];
        if ($notes !== null) {
            $updateData['notes'] = $notes;
        }

        $borrowing->update($updateData);

        return $borrowing->fresh(['items.book', 'user']);
    }

    /**
     * Confirm that books are handed over to student (APPROVED -> BORROWED).
     * Deducts stock atomically and sets due_date (max 3 days).
     *
     * @throws InvalidStatusTransitionException
     * @throws InsufficientStockException
     */
    public function confirmBorrowing(Borrowing $borrowing): Borrowing
    {
        if ($borrowing->status !== Borrowing::STATUS_APPROVED) {
            throw new InvalidStatusTransitionException($borrowing->status, Borrowing::STATUS_BORROWED);
        }

        return DB::transaction(function () use ($borrowing) {
            $borrowing->load(['items', 'user']);

            // Verify stock and decrement for each book
            foreach ($borrowing->items as $item) {
                /** @var Book $book */
                $book = Book::withTrashed()->lockForUpdate()->findOrFail($item->book_id);

                if ($book->stock < $item->quantity) {
                    throw new InsufficientStockException($book->title, $book->stock, $item->quantity);
                }

                $book->decrement('stock', $item->quantity);
            }

            $borrowedAt = now();
            $maxDays = Borrowing::getMaxDaysForUser($borrowing->user);
            $dueDate = $borrowing->due_date ?? $borrowedAt->copy()->addDays($maxDays);

            $borrowing->update([
                'status' => Borrowing::STATUS_BORROWED,
                'borrowed_at' => $borrowedAt,
                'due_date' => $dueDate,
            ]);

            return $borrowing->fresh(['items.book', 'user']);
        });
    }

    /**
     * Process return of books (BORROWED -> RETURNED).
     * Restores stock atomically.
     *
     * @throws InvalidStatusTransitionException
     */
    public function returnBorrowing(Borrowing $borrowing): Borrowing
    {
        if ($borrowing->status !== Borrowing::STATUS_BORROWED) {
            throw new InvalidStatusTransitionException($borrowing->status, Borrowing::STATUS_RETURNED);
        }

        return DB::transaction(function () use ($borrowing) {
            $borrowing->load('items');

            // Restore stock for each book
            foreach ($borrowing->items as $item) {
                /** @var Book $book */
                $book = Book::withTrashed()->lockForUpdate()->findOrFail($item->book_id);
                $book->increment('stock', $item->quantity);
            }

            $borrowing->update([
                'status' => Borrowing::STATUS_RETURNED,
                'returned_at' => now(),
            ]);

            return $borrowing->fresh(['items.book', 'user']);
        });
    }
}

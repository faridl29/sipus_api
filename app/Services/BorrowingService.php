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
            $maxDays = Borrowing::getMaxDaysForBorrower($borrowerType ?? $user?->role);
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
     * Holds/reserves book stock and sets pickup deadline to 1 school day (weekday).
     *
     * @throws InvalidStatusTransitionException
     * @throws InsufficientStockException
     */
    public function approveBorrowing(Borrowing $borrowing): Borrowing
    {
        if ($borrowing->status !== Borrowing::STATUS_PENDING) {
            throw new InvalidStatusTransitionException($borrowing->status, Borrowing::STATUS_APPROVED);
        }

        return DB::transaction(function () use ($borrowing) {
            $borrowing->load('items');

            // Verify stock and hold/reserve (decrement) for each book
            foreach ($borrowing->items as $item) {
                /** @var Book $book */
                $book = Book::withTrashed()->lockForUpdate()->findOrFail($item->book_id);

                if ($book->stock < $item->quantity) {
                    throw new InsufficientStockException($book->title, $book->stock, $item->quantity);
                }

                $book->decrement('stock', $item->quantity);
            }

            $approvedAt = now();
            $borrowing->update([
                'status' => Borrowing::STATUS_APPROVED,
                'approved_at' => $approvedAt,
                'pickup_deadline' => $approvedAt->copy()->addWeekday(),
            ]);

            return $borrowing->fresh(['items.book', 'user']);
        });
    }

    /**
     * Reject a PENDING or APPROVED borrowing.
     * Restores stock if previously APPROVED.
     *
     * @throws InvalidStatusTransitionException
     */
    public function rejectBorrowing(Borrowing $borrowing, ?string $notes = null): Borrowing
    {
        if (! in_array($borrowing->status, [Borrowing::STATUS_PENDING, Borrowing::STATUS_APPROVED], true)) {
            throw new InvalidStatusTransitionException($borrowing->status, Borrowing::STATUS_REJECTED);
        }

        return DB::transaction(function () use ($borrowing, $notes) {
            $borrowing->load('items');

            // If it was already approved, release held stock
            if ($borrowing->status === Borrowing::STATUS_APPROVED) {
                foreach ($borrowing->items as $item) {
                    /** @var Book $book */
                    $book = Book::withTrashed()->lockForUpdate()->findOrFail($item->book_id);
                    $book->increment('stock', $item->quantity);
                }
            }

            $updateData = ['status' => Borrowing::STATUS_REJECTED];
            if ($notes !== null) {
                $updateData['notes'] = $notes;
            }

            $borrowing->update($updateData);

            return $borrowing->fresh(['items.book', 'user']);
        });
    }

    /**
     * Cancel a PENDING or APPROVED borrowing by the student.
     * Restores stock if previously APPROVED.
     *
     * @throws InvalidStatusTransitionException
     */
    public function cancelBorrowing(Borrowing $borrowing): Borrowing
    {
        if (! in_array($borrowing->status, [Borrowing::STATUS_PENDING, Borrowing::STATUS_APPROVED], true)) {
            throw new InvalidStatusTransitionException($borrowing->status, Borrowing::STATUS_CANCELLED);
        }

        return DB::transaction(function () use ($borrowing) {
            $borrowing->load('items');

            // If it was approved, release the held stock
            if ($borrowing->status === Borrowing::STATUS_APPROVED) {
                foreach ($borrowing->items as $item) {
                    /** @var Book $book */
                    $book = Book::withTrashed()->lockForUpdate()->findOrFail($item->book_id);
                    $book->increment('stock', $item->quantity);
                }
            }

            $borrowing->update(['status' => Borrowing::STATUS_CANCELLED]);

            return $borrowing->fresh(['items.book', 'user']);
        });
    }

    /**
     * Auto-cancel uncollected APPROVED borrowings that exceeded the 1 school day deadline.
     * Releases held stock back to available inventory.
     */
    public function expireUncollectedBorrowings(): int
    {
        $expiredBorrowings = Borrowing::where('status', Borrowing::STATUS_APPROVED)
            ->whereNotNull('pickup_deadline')
            ->where('pickup_deadline', '<', now())
            ->with('items')
            ->get();

        $count = 0;
        foreach ($expiredBorrowings as $borrowing) {
            DB::transaction(function () use ($borrowing) {
                foreach ($borrowing->items as $item) {
                    /** @var Book $book */
                    $book = Book::withTrashed()->lockForUpdate()->findOrFail($item->book_id);
                    $book->increment('stock', $item->quantity);
                }

                $cancelNote = 'Batal otomatis: Melewati batas waktu pengambilan 1 hari sekolah.';
                $borrowing->update([
                    'status' => Borrowing::STATUS_CANCELLED,
                    'notes' => trim(($borrowing->notes ? $borrowing->notes.' | ' : '').$cancelNote),
                ]);
            });
            $count++;
        }

        return $count;
    }

    /**
     * Auto-cancel uncollected APPROVED borrowings that exceeded the 1 school day deadline.
     * Releases held stock back to available inventory.
     */
    public function expireUncollectedBorrowings(): int
    {
        $expiredBorrowings = Borrowing::where('status', Borrowing::STATUS_APPROVED)
            ->whereNotNull('pickup_deadline')
            ->where('pickup_deadline', '<', now())
            ->with('items')
            ->get();

        $count = 0;
        foreach ($expiredBorrowings as $borrowing) {
            DB::transaction(function () use ($borrowing) {
                foreach ($borrowing->items as $item) {
                    /** @var Book $book */
                    $book = Book::withTrashed()->lockForUpdate()->findOrFail($item->book_id);
                    $book->increment('stock', $item->quantity);
                }

                $cancelNote = 'Batal otomatis: Melewati batas waktu pengambilan 1 hari sekolah.';
                $borrowing->update([
                    'status' => Borrowing::STATUS_CANCELLED,
                    'notes' => trim(($borrowing->notes ? $borrowing->notes . ' | ' : '') . $cancelNote),
                ]);
            });
            $count++;
        }

        return $count;
    }

    /**
     * Confirm that books are handed over to student (APPROVED -> BORROWED).
     * Stock was already held during approval, sets due_date (max 3 days).
     *
     * @throws InvalidStatusTransitionException
     */
    public function confirmBorrowing(Borrowing $borrowing): Borrowing
    {
        if ($borrowing->status !== Borrowing::STATUS_APPROVED) {
            throw new InvalidStatusTransitionException($borrowing->status, Borrowing::STATUS_BORROWED);
        }

        return DB::transaction(function () use ($borrowing) {
            $borrowing->load(['items', 'user']);

            $borrowedAt = now();
            $maxDays = Borrowing::getMaxDaysForBorrower($borrowing->borrower_type ?? $borrowing->user?->role);
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

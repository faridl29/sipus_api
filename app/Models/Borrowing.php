<?php

namespace App\Models;

use Database\Factories\BorrowingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Borrowing extends Model
{
    /** @use HasFactory<BorrowingFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_BORROWED = 'BORROWED';

    public const STATUS_RETURNED = 'RETURNED';

    public const MAX_BORROWING_DAYS_STUDENT = 3;

    public const MAX_BORROWING_DAYS_TEACHER = 7;

    public const MAX_BORROWING_DAYS_STAFF = 7;

    public const MAX_BORROWING_DAYS = 3;

    /**
     * Get maximum borrowing duration in days based on borrower type or user.
     */
    public static function getMaxDaysForBorrower(?string $borrowerType = null): int
    {
        if (in_array($borrowerType, ['teacher', 'staff'], true)) {
            return self::MAX_BORROWING_DAYS_TEACHER;
        }

        return self::MAX_BORROWING_DAYS_STUDENT;
    }

    public static function getMaxDaysForUser(?User $user = null): int
    {
        return self::MAX_BORROWING_DAYS_STUDENT;
    }

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_BORROWED,
        self::STATUS_RETURNED,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'borrower_name',
        'borrower_phone',
        'borrower_identity_number',
        'borrower_type',
        'status',
        'borrowed_at',
        'due_date',
        'returned_at',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'due_date' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * Check if the borrowing is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_BORROWED
            && $this->due_date !== null
            && now()->isAfter($this->due_date);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault(function (User $user, Borrowing $borrowing) {
            $user->name = $borrowing->borrower_name ?? 'Tamu / Umum';
            $user->role = $borrowing->borrower_type ?? 'guest';
            $user->identity_number = $borrowing->borrower_identity_number;
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(BorrowingItem::class);
    }
}

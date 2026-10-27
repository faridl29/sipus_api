<?php

namespace App\Models;

use Database\Factories\BorrowingFactory;
use Illuminate\Database\Eloquent\Builder;
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

    protected static function booted(): void
    {
        static::creating(function (Borrowing $borrowing) {
            if (empty($borrowing->transaction_code)) {
                $borrowing->transaction_code = self::generateTransactionCode();
            }
        });
    }

    /**
     * Generate unique sequential transaction code (TRX ID).
     * Example: TRX-20261011-0001
     */
    public static function generateTransactionCode(): string
    {
        $date = now()->format('Ymd');
        $prefix = "TRX-{$date}-";

        $latest = self::where('transaction_code', 'like', "{$prefix}%")
            ->latest('id')
            ->first();

        $sequence = 1;
        if ($latest && preg_match('/TRX-\d+-(\d+)/', $latest->transaction_code, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        $code = sprintf('TRX-%s-%04d', $date, $sequence);
        while (self::where('transaction_code', $code)->exists()) {
            $sequence++;
            $code = sprintf('TRX-%s-%04d', $date, $sequence);
        }

        return $code;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transaction_code',
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

    /**
     * Scope a query to search borrowings by transaction code, borrower name/identity, or book title.
     *
     * @param  Builder<Borrowing>  $query
     * @return Builder<Borrowing>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null) {
            return $query;
        }

        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $isSqlite = $query->getConnection()->getDriverName() === 'sqlite';
        $escapeSql = $isSqlite ? " ESCAPE '\\'" : '';

        $terms = array_filter(preg_split('/\s+/', $search) ?: []);

        return $query->where(function (Builder $q) use ($terms, $escapeSql) {
            foreach ($terms as $term) {
                $escapedTerm = addcslashes($term, '%_\\');
                $q->where(function (Builder $subQuery) use ($escapedTerm, $escapeSql) {
                    $subQuery->whereRaw("transaction_code LIKE ?{$escapeSql}", ["%{$escapedTerm}%"])
                        ->orWhereRaw("borrower_name LIKE ?{$escapeSql}", ["%{$escapedTerm}%"])
                        ->orWhereRaw("borrower_identity_number LIKE ?{$escapeSql}", ["%{$escapedTerm}%"])
                        ->orWhereHas('user', function (Builder $userQuery) use ($escapedTerm, $escapeSql) {
                            $userQuery->whereRaw("name LIKE ?{$escapeSql}", ["%{$escapedTerm}%"])
                                ->orWhereRaw("identity_number LIKE ?{$escapeSql}", ["%{$escapedTerm}%"]);
                        })
                        ->orWhereHas('items.book', function (Builder $bookQuery) use ($escapedTerm, $escapeSql) {
                            $bookQuery->whereRaw("title LIKE ?{$escapeSql}", ["%{$escapedTerm}%"]);
                        });
                });
            }
        });
    }
}

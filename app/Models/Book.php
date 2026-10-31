<?php

namespace App\Models;

use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'author',
        'publisher',
        'publication_year',
        'isbn',
        'stock',
        'description',
        'cover',
        'rack_location',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'publication_year' => 'integer',
        ];
    }

    public function isAvailable(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Scope a query to search books by title, author, publisher, or isbn.
     * Supports multi-word matching, wildcard escaping, and hyphen-agnostic ISBN.
     *
     * @param  Builder<Book>  $query
     * @return Builder<Book>
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

        $cleanedIsbn = str_replace('-', '', $search);
        $terms = array_filter(preg_split('/\s+/', $search) ?: []);

        return $query->where(function (Builder $q) use ($terms, $cleanedIsbn, $escapeSql) {
            foreach ($terms as $term) {
                $escapedTerm = addcslashes($term, '%_\\');
                $q->where(function (Builder $subQuery) use ($escapedTerm, $cleanedIsbn, $escapeSql) {
                    $subQuery->whereRaw("title LIKE ?{$escapeSql}", ["%{$escapedTerm}%"])
                        ->orWhereRaw("author LIKE ?{$escapeSql}", ["%{$escapedTerm}%"])
                        ->orWhereRaw("publisher LIKE ?{$escapeSql}", ["%{$escapedTerm}%"])
                        ->orWhereRaw("isbn LIKE ?{$escapeSql}", ["%{$escapedTerm}%"]);

                    if ($cleanedIsbn !== '') {
                        $escapedCleanedIsbn = addcslashes($cleanedIsbn, '%_\\');
                        $subQuery->orWhereRaw("REPLACE(isbn, '-', '') LIKE ?{$escapeSql}", ["%{$escapedCleanedIsbn}%"]);
                    }
                });
            }
        });
    }

    /**
     * Scope a query to only include available books with stock > 0.
     *
     * @param  Builder<Book>  $query
     * @return Builder<Book>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    public function borrowingItems(): HasMany
    {
        return $this->hasMany(BorrowingItem::class);
    }
}

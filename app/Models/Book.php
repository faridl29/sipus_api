<?php

namespace App\Models;

use Database\Factories\BookFactory;
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

    public function borrowingItems(): HasMany
    {
        return $this->hasMany(BorrowingItem::class);
    }
}

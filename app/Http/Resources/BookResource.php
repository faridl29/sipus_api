<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $coverUrl = null;
        if ($this->cover) {
            $coverUrl = filter_var($this->cover, FILTER_VALIDATE_URL)
                ? $this->cover
                : Storage::disk('public')->url($this->cover);
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'publisher' => $this->publisher,
            'publication_year' => $this->publication_year,
            'isbn' => $this->isbn,
            'stock' => $this->stock,
            'is_available' => $this->stock > 0,
            'description' => $this->description,
            'cover' => $coverUrl,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

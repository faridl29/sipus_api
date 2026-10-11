<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BorrowingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_code' => $this->transaction_code,
            'user_id' => $this->user_id,
            'borrower_name' => $this->borrower_name ?? $this->user?->name,
            'borrower_phone' => $this->borrower_phone,
            'borrower_identity_number' => $this->borrower_identity_number ?? $this->user?->identity_number,
            'borrower_type' => $this->borrower_type ?? $this->user?->role ?? 'student',
            'user' => new UserResource($this->whenLoaded('user')),
            'status' => $this->status,
            'borrowed_at' => $this->borrowed_at?->toISOString(),
            'due_date' => $this->due_date?->toISOString(),
            'returned_at' => $this->returned_at?->toISOString(),
            'is_overdue' => $this->isOverdue(),
            'notes' => $this->notes,
            'items' => BorrowingItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

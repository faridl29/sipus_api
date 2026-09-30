<?php

namespace App\Http\Requests\Borrowing;

use App\Models\Book;
use App\Models\Borrowing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBorrowingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isStudent() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxDueDate = now()->addDays(Borrowing::MAX_BORROWING_DAYS_STUDENT)->toDateString();

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'integer', 'exists:books,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'due_date' => [
                'nullable',
                'date',
                'after_or_equal:today',
                "before_or_equal:{$maxDueDate}",
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxDays = Borrowing::MAX_BORROWING_DAYS_STUDENT;

        return [
            'due_date.before_or_equal' => "Batas maksimal pengembalian buku adalah {$maxDays} hari dari tanggal pengajuan.",
            'due_date.after_or_equal' => 'Tanggal pengembalian tidak boleh sebelum hari ini.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);

            if (! is_array($items)) {
                return;
            }

            foreach ($items as $index => $item) {
                if (! isset($item['book_id'], $item['quantity'])) {
                    continue;
                }

                $book = Book::find($item['book_id']);
                if ($book) {
                    if ($book->stock < $item['quantity']) {
                        $validator->errors()->add(
                            "items.{$index}.quantity",
                            "Stok buku '{$book->title}' tidak mencukupi (tersedia: {$book->stock})."
                        );
                    }
                }
            }
        });
    }
}

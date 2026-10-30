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
            'items' => ['required', 'array', 'min:1', 'max:'.Borrowing::MAX_BOOKS_PER_BORROWER],
            'items.*.book_id' => ['required', 'integer', 'distinct', 'exists:books,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.Borrowing::MAX_BOOKS_PER_BORROWER],
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
        $maxBooks = Borrowing::MAX_BOOKS_PER_BORROWER;

        return [
            'items.required' => 'Daftar buku yang dipinjam wajib diisi.',
            'items.array' => 'Format daftar buku harus berupa array.',
            'items.min' => 'Minimal meminjam 1 buku.',
            'items.max' => "Maksimal peminjaman adalah {$maxBooks} buku per orang.",
            'items.*.book_id.required' => 'ID buku wajib diisi.',
            'items.*.book_id.distinct' => 'Buku yang sama tidak boleh dipilih lebih dari satu kali.',
            'items.*.book_id.exists' => 'Buku yang dipilih tidak ditemukan.',
            'items.*.quantity.required' => 'Jumlah eksemplar buku wajib diisi.',
            'items.*.quantity.min' => 'Jumlah eksemplar buku minimal 1.',
            'items.*.quantity.max' => "Maksimal peminjaman adalah {$maxBooks} eksemplar per buku.",
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

            $totalRequestedQuantity = 0;
            foreach ($items as $item) {
                if (isset($item['quantity']) && is_numeric($item['quantity'])) {
                    $totalRequestedQuantity += (int) $item['quantity'];
                }
            }

            if ($totalRequestedQuantity > Borrowing::MAX_BOOKS_PER_BORROWER) {
                $validator->errors()->add(
                    'items',
                    sprintf('Maksimal jumlah buku yang dapat dipinjam adalah %d buku.', Borrowing::MAX_BOOKS_PER_BORROWER)
                );
            }

            $user = $this->user();
            if ($user) {
                if (Borrowing::hasOverdueBorrowingsForUser($user->id)) {
                    $validator->errors()->add(
                        'items',
                        'Anda memiliki peminjaman buku yang melewati batas waktu (terlambat). Harap kembalikan buku terlebih dahulu sebelum mengajukan peminjaman baru.'
                    );
                }

                $activeBooksCount = Borrowing::getActiveBooksCountForUser($user->id);
                if (($activeBooksCount + $totalRequestedQuantity) > Borrowing::MAX_BOOKS_PER_BORROWER) {
                    $validator->errors()->add(
                        'items',
                        sprintf(
                            'Pengajuan melebihi batas kuota peminjaman. Anda masih memiliki %d buku yang sedang dipinjam atau dalam proses pengajuan (maksimal %d buku per orang).',
                            $activeBooksCount,
                            Borrowing::MAX_BOOKS_PER_BORROWER
                        )
                    );
                }
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

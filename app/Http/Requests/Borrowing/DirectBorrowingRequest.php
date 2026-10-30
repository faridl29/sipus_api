<?php

namespace App\Http\Requests\Borrowing;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DirectBorrowingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $borrowerType = $this->input('borrower_type');
        $maxDays = Borrowing::getMaxDaysForBorrower($borrowerType);
        $maxDueDate = now()->addDays($maxDays)->toDateString();

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'borrower_name' => ['required_without:user_id', 'nullable', 'string', 'max:255'],
            'borrower_phone' => ['nullable', 'string', 'max:50'],
            'borrower_identity_number' => ['nullable', 'string', 'max:50'],
            'borrower_type' => ['nullable', 'string', 'in:student,teacher,staff,guest'],
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
        $borrowerType = $this->input('borrower_type');
        $maxDays = Borrowing::getMaxDaysForBorrower($borrowerType);
        $maxBooks = Borrowing::MAX_BOOKS_PER_BORROWER;

        return [
            'borrower_name.required_without' => 'Nama peminjam wajib diisi jika peminjam tidak memiliki akun (tamu/umum).',
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
            'due_date.before_or_equal' => "Batas maksimal pengembalian buku adalah {$maxDays} hari dari tanggal peminjaman.",
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

            // Calculate total requested quantity across all items
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

            $userId = $this->input('user_id');
            $identityNumber = $this->input('borrower_identity_number');
            if ($userId || $identityNumber) {
                $parsedUserId = $userId ? (int) $userId : null;
                $parsedIdentityNumber = $identityNumber ? (string) $identityNumber : null;

                if (Borrowing::hasOverdueBorrowingsForBorrower($parsedUserId, $parsedIdentityNumber)) {
                    $validator->errors()->add(
                        'items',
                        'Peminjam memiliki peminjaman buku yang melewati batas waktu (terlambat). Harap selesaikan pengembalian buku terlebih dahulu.'
                    );
                }

                $activeCount = Borrowing::getActiveBooksCountForBorrower(
                    $parsedUserId,
                    $parsedIdentityNumber
                );

                if (($activeCount + $totalRequestedQuantity) > Borrowing::MAX_BOOKS_PER_BORROWER) {
                    $validator->errors()->add(
                        'items',
                        sprintf(
                            'Peminjaman melebihi batas kuota peminjaman. Peminjam masih memiliki %d buku yang sedang aktif dipinjam atau diajukan (maksimal %d buku per orang).',
                            $activeCount,
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

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
        $userId = $this->input('user_id');
        $user = $userId ? User::find($userId) : null;
        $maxDays = Borrowing::getMaxDaysForUser($user);
        $maxDueDate = now()->addDays($maxDays)->toDateString();

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'borrower_name' => ['required_without:user_id', 'nullable', 'string', 'max:255'],
            'borrower_phone' => ['nullable', 'string', 'max:50'],
            'borrower_identity_number' => ['nullable', 'string', 'max:50'],
            'borrower_type' => ['nullable', 'string', 'in:student,teacher,staff,guest'],
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
        $userId = $this->input('user_id');
        $user = $userId ? User::find($userId) : null;
        $maxDays = Borrowing::getMaxDaysForUser($user);

        return [
            'borrower_name.required_without' => 'Nama peminjam wajib diisi jika peminjam tidak memiliki akun (tamu/umum).',
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

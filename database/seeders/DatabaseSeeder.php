<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Purge legacy teacher accounts if any exist
        User::where('role', 'teacher')->orWhere('email', 'teacher@example.com')->delete();

        // Staff user
        User::updateOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Petugas Perpustakaan',
                'identity_number' => 'STF-001',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STAFF,
            ]
        );

        // Student user
        User::updateOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Siswa Perpustakaan',
                'identity_number' => '202410001',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STUDENT,
            ]
        );

        // Second student for ownership testing
        User::updateOrCreate(
            ['email' => 'student2@example.com'],
            [
                'name' => 'Siswa Kedua',
                'identity_number' => '202410002',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STUDENT,
            ]
        );

        // Sample Books
        $books = [
            [
                'title' => 'Laskar Pelangi',
                'author' => 'Andrea Hirata',
                'publisher' => 'Bentang Pustaka',
                'publication_year' => 2005,
                'isbn' => '978-979-3062-79-2',
                'stock' => 10,
                'description' => 'Kisah perjuangan sepuluh anak di Belitung dalam menempuh pendidikan dasar.',
                'cover' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f',
            ],
            [
                'title' => 'Bumi Manusia',
                'author' => 'Pramoedya Ananta Toer',
                'publisher' => 'Hasta Mitra',
                'publication_year' => 1980,
                'isbn' => '978-979-97312-3-4',
                'stock' => 5,
                'description' => 'Roman sejarah pergerakan kebangsaan Indonesia awal abad ke-20.',
                'cover' => 'https://images.unsplash.com/photo-1512820790803-83ca734da794',
            ],
            [
                'title' => 'Negeri 5 Menara',
                'author' => 'Ahmad Fuadi',
                'publisher' => 'Gramedia Pustaka Utama',
                'publication_year' => 2009,
                'isbn' => '978-979-22-4861-6',
                'stock' => 8,
                'description' => 'Kisah persahabatan santri di Pondok Madani dengan filosofi Man Jadda Wajada.',
                'cover' => 'https://images.unsplash.com/photo-1532012164546-f432f2e3777f',
            ],
            [
                'title' => 'Filosofi Teras',
                'author' => 'Henry Manampiring',
                'publisher' => 'Buku Kompas',
                'publication_year' => 2018,
                'isbn' => '978-602-412-518-9',
                'stock' => 12,
                'description' => 'Penerapan filsafat Stoisisme kuno untuk mengatasi emosi negatif masa kini.',
                'cover' => 'https://images.unsplash.com/photo-1495446815901-a7297e633e8d',
            ],
            [
                'title' => 'Atomic Habits',
                'author' => 'James Clear',
                'publisher' => 'Gramedia Pustaka Utama',
                'publication_year' => 2019,
                'isbn' => '978-602-06-3317-6',
                'stock' => 7,
                'description' => 'Perubahan kecil yang memberikan hasil luar biasa dalam pembentukan kebiasaan.',
                'cover' => 'https://images.unsplash.com/photo-1524995997946-a1c2e315a42f',
            ],
            [
                'title' => 'Buku Langka Habis Stok',
                'author' => 'Penulis Langka',
                'publisher' => 'Penerbit Antik',
                'publication_year' => 1999,
                'isbn' => '978-000-0000-00-0',
                'stock' => 0,
                'description' => 'Buku referensi khusus perpustakaan yang saat ini stoknya sedang habis.',
                'cover' => null,
            ],
        ];

        foreach ($books as $bookData) {
            Book::updateOrCreate(
                ['isbn' => $bookData['isbn']],
                $bookData
            );
        }
    }
}

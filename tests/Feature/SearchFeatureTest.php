<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_buku_dengan_spasi_di_awal_dan_akhir(): void
    {
        Book::factory()->create([
            'title' => 'Laravel Mastery',
            'author' => 'Taylor Otwell',
        ]);

        $response = $this->getJson('/api/v1/books?search='.urlencode('  Laravel  '));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Laravel Mastery');
    }

    public function test_search_hanya_spasi_tidak_menghilangkan_semua_buku(): void
    {
        Book::factory()->create(['title' => 'Buku Pertama']);
        Book::factory()->create(['title' => 'Buku Kedua']);

        $response = $this->getJson('/api/v1/books?search='.urlencode('   '));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_search_multi_kata_melintasi_judul_dan_penulis(): void
    {
        Book::factory()->create([
            'title' => 'Laskar Pelangi',
            'author' => 'Andrea Hirata',
        ]);
        Book::factory()->create([
            'title' => 'Bumi Manusia',
            'author' => 'Pramoedya Ananta Toer',
        ]);

        $response = $this->getJson('/api/v1/books?search='.urlencode('Andrea Laskar'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Laskar Pelangi');
    }

    public function test_search_isbn_dengan_atau_tanpa_strip(): void
    {
        Book::factory()->create([
            'title' => 'Refactoring',
            'isbn' => '978-0-201-48567-7',
        ]);

        // Cari tanpa strip (seperti dari barcode scanner)
        $response = $this->getJson('/api/v1/books?search=9780201485677');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Refactoring');
    }

    public function test_search_karakter_wildcard_percent_dan_underscore(): void
    {
        Book::factory()->create(['title' => 'Kiat 100% Lolos Ujian']);
        Book::factory()->create(['title' => 'Buku Biasa']);

        $response = $this->getJson('/api/v1/books?search='.urlencode('%'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Kiat 100% Lolos Ujian');
    }

    public function test_search_buku_berdasarkan_penerbit(): void
    {
        Book::factory()->create([
            'title' => 'Buku A',
            'publisher' => 'Gramedia Pustaka Utama',
        ]);
        Book::factory()->create([
            'title' => 'Buku B',
            'publisher' => 'Mizan',
        ]);

        $response = $this->getJson('/api/v1/books?search='.urlencode('Gramedia'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Buku A');
    }

    public function test_staff_books_mendukung_search_dan_filter_available(): void
    {
        $staff = User::factory()->staff()->create();

        Book::factory()->create(['title' => 'Buku Tersedia Laravel', 'stock' => 5]);
        Book::factory()->create(['title' => 'Buku Habis Laravel', 'stock' => 0]);
        Book::factory()->create(['title' => 'Buku Flutter', 'stock' => 3]);

        $response = $this->actingAs($staff, 'sanctum')->getJson('/api/v1/staff/books?search=Laravel&available_only=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Buku Tersedia Laravel');
    }

    public function test_staff_borrowings_mendukung_search_berdasarkan_kode_transaksi_atau_nama_peminjam(): void
    {
        $staff = User::factory()->staff()->create();
        $student = User::factory()->student()->create([
            'name' => 'Ahmad Dahlan',
            'identity_number' => 'NIS-12345',
        ]);

        $book = Book::factory()->create(['title' => 'Sejarah Indonesia']);

        $borrowing1 = Borrowing::factory()->create([
            'user_id' => $student->id,
            'transaction_code' => 'BOR-20261027-0099',
            'status' => 'PENDING',
        ]);

        BorrowingItem::factory()->create([
            'borrowing_id' => $borrowing1->id,
            'book_id' => $book->id,
            'quantity' => 1,
        ]);

        // Second non-matching borrowing
        Borrowing::factory()->create([
            'transaction_code' => 'BOR-20261027-9999',
            'status' => 'PENDING',
        ]);

        // Search by transaction code
        $res1 = $this->actingAs($staff, 'sanctum')->getJson('/api/v1/staff/borrowings?search=0099');
        $res1->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $borrowing1->id);

        // Search by borrower name
        $res2 = $this->actingAs($staff, 'sanctum')->getJson('/api/v1/staff/borrowings?search=Dahlan');
        $res2->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $borrowing1->id);

        // Search by borrower NIS
        $res3 = $this->actingAs($staff, 'sanctum')->getJson('/api/v1/staff/borrowings?search=12345');
        $res3->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $borrowing1->id);

        // Search by book title
        $res4 = $this->actingAs($staff, 'sanctum')->getJson('/api/v1/staff/borrowings?search=Sejarah');
        $res4->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $borrowing1->id);
    }

    public function test_student_dapat_mencari_riwayat_peminjamannya_sendiri(): void
    {
        $student = User::factory()->student()->create();
        $book1 = Book::factory()->create(['title' => 'Algoritma Pemrograman']);
        $book2 = Book::factory()->create(['title' => 'Basis Data Lanjut']);

        $borrowing1 = Borrowing::factory()->create([
            'user_id' => $student->id,
            'status' => 'BORROWED',
        ]);
        BorrowingItem::factory()->create([
            'borrowing_id' => $borrowing1->id,
            'book_id' => $book1->id,
            'quantity' => 1,
        ]);

        $borrowing2 = Borrowing::factory()->create([
            'user_id' => $student->id,
            'status' => 'BORROWED',
        ]);
        BorrowingItem::factory()->create([
            'borrowing_id' => $borrowing2->id,
            'book_id' => $book2->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($student, 'sanctum')->getJson('/api/v1/borrowings/me?search=Algoritma');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $borrowing1->id);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_atau_student_dapat_melihat_katalog_tanpa_autentikasi(): void
    {
        Book::factory()->create([
            'title' => 'Laskar Pelangi',
            'author' => 'Andrea Hirata',
            'stock' => 5,
        ]);
        Book::factory()->create([
            'title' => 'Bumi Manusia',
            'author' => 'Pramoedya Ananta Toer',
            'stock' => 3,
        ]);

        $response = $this->getJson('/api/v1/books');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Books retrieved successfully',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_guest_dapat_mencari_buku_berdasarkan_judul_atau_penulis(): void
    {
        Book::factory()->create([
            'title' => 'Belajar Laravel API',
            'author' => 'Taylor',
        ]);
        Book::factory()->create([
            'title' => 'Belajar Flutter Mobile',
            'author' => 'Google',
        ]);

        $response = $this->getJson('/api/v1/books?search=Laravel');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Belajar Laravel API');
    }

    public function test_guest_dapat_melihat_detail_buku(): void
    {
        $book = Book::factory()->create([
            'title' => 'Filosofi Teras',
            'author' => 'Henry Manampiring',
            'stock' => 10,
        ]);

        $response = $this->getJson("/api/v1/books/{$book->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $book->id,
                    'title' => 'Filosofi Teras',
                    'author' => 'Henry Manampiring',
                    'stock' => 10,
                    'is_available' => true,
                ],
            ]);
    }

    public function test_melihat_detail_buku_yang_tidak_ada_mengembalikan_404(): void
    {
        $response = $this->getJson('/api/v1/books/99999');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found.',
            ]);
    }
}

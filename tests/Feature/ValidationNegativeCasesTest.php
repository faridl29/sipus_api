<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationNegativeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->staff = User::factory()->staff()->create();
    }

    public function test_login_validasi_gagal_jika_field_kosong(): void
    {
        // Empty body
        $response = $this->postJson('/api/v1/auth/login', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['identity_number', 'password']);
    }

    public function test_staff_tambah_buku_gagal_jika_data_tidak_lengkap_atau_stok_negatif(): void
    {
        // 1. Missing required title and author
        $res1 = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/staff/books', [
                'stock' => 5,
            ]);
        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'author']);

        // 2. Negative stock
        $res2 = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/staff/books', [
                'title' => 'Buku Minus',
                'author' => 'Penulis',
                'stock' => -5,
            ]);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['stock']);
    }

    public function test_staff_update_buku_gagal_jika_stok_negatif(): void
    {
        $book = Book::factory()->create(['stock' => 10]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/v1/staff/books/{$book->id}", [
                'stock' => -1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['stock']);
    }

    public function test_pengajuan_peminjaman_gagal_jika_items_kosong_quantity_nol_atau_buku_tidak_ada(): void
    {
        // 1. Empty items array
        $res1 = $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/v1/borrowings', [
                'items' => [],
            ]);
        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        // 2. Quantity 0
        $book = Book::factory()->create(['stock' => 5]);
        $res2 = $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/v1/borrowings', [
                'items' => [
                    ['book_id' => $book->id, 'quantity' => 0],
                ],
            ]);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);

        // 3. Non-existent book_id
        $res3 = $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/v1/borrowings', [
                'items' => [
                    ['book_id' => 99999, 'quantity' => 1],
                ],
            ]);
        $res3->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.book_id']);
    }

    public function test_staff_tidak_dapat_mengajukan_peminjaman_karena_khusus_siswa(): void
    {
        $book = Book::factory()->create(['stock' => 5]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/borrowings', [
                'items' => [
                    ['book_id' => $book->id, 'quantity' => 1],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_mengakses_transaksi_peminjaman_yang_tidak_ada_mengembalikan_404(): void
    {
        $resStudent = $this->actingAs($this->student, 'sanctum')
            ->getJson('/api/v1/borrowings/99999');
        $resStudent->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found.',
            ]);

        $resStaffApprove = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/staff/borrowings/99999/approve');
        $resStaffApprove->assertNotFound();
    }
}

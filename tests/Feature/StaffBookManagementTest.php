<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffBookManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->staff()->create();
    }

    public function test_staff_dapat_menambah_buku_dengan_upload_file_cover(): void
    {
        Storage::fake('public');

        $coverFile = UploadedFile::fake()->image('cover-test.jpg', 600, 800);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->post('/api/v1/staff/books', [
                'title' => 'Buku Baru Bertema Cover',
                'author' => 'Penulis Cover',
                'stock' => 5,
                'isbn' => '978-602-1234-56-7',
                'cover' => $coverFile,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Book created successfully',
            ]);

        $book = Book::where('isbn', '978-602-1234-56-7')->firstOrFail();
        $this->assertNotNull($book->cover);
        Storage::disk('public')->assertExists($book->cover);

        $response->assertJsonPath('data.cover', Storage::disk('public')->url($book->cover));
    }

    public function test_staff_dapat_mengubah_buku_dan_upload_cover_via_post_method_put(): void
    {
        Storage::fake('public');

        $initialCover = UploadedFile::fake()->image('initial.jpg')->store('covers', 'public');
        $book = Book::factory()->create([
            'cover' => $initialCover,
            'isbn' => '978-602-9999-00-1',
        ]);

        $newCoverFile = UploadedFile::fake()->image('updated-cover.png');

        $response = $this->actingAs($this->staff, 'sanctum')
            ->post("/api/v1/staff/books/{$book->id}", [
                '_method' => 'PUT',
                'title' => 'Judul Buku Telah Diperbarui',
                'cover' => $newCoverFile,
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Book updated successfully',
            ]);

        $book->refresh();
        $this->assertSame('Judul Buku Telah Diperbarui', $book->title);
        $this->assertNotSame($initialCover, $book->cover);

        // File cover lama terhapus dan file cover baru tersimpan
        Storage::disk('public')->assertMissing($initialCover);
        Storage::disk('public')->assertExists($book->cover);
    }

    public function test_staff_gagal_menambah_buku_jika_isbn_duplikat(): void
    {
        Book::factory()->create([
            'isbn' => '978-602-1111-22-3',
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/staff/books', [
                'title' => 'Buku ISBN Sama',
                'author' => 'Penulis Lain',
                'stock' => 3,
                'isbn' => '978-602-1111-22-3',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['isbn']);
    }

    public function test_staff_gagal_mengubah_isbn_menjadi_isbn_buku_lain(): void
    {
        $book1 = Book::factory()->create([
            'isbn' => '978-602-0000-01-1',
        ]);
        $book2 = Book::factory()->create([
            'isbn' => '978-602-0000-02-2',
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/v1/staff/books/{$book2->id}", [
                'isbn' => $book1->isbn,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['isbn']);
    }

    public function test_staff_dapat_mengubah_buku_tanpa_mengubah_isbn_miliknya(): void
    {
        $book = Book::factory()->create([
            'title' => 'Judul Lama',
            'isbn' => '978-602-0000-03-3',
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/v1/staff/books/{$book->id}", [
                'title' => 'Judul Baru',
                'isbn' => $book->isbn,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Judul Baru');
    }
}

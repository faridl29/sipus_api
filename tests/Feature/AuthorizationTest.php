<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $otherStudent;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->otherStudent = User::factory()->student()->create();
        $this->staff = User::factory()->staff()->create();
    }

    public function test_student_tidak_dapat_mengakses_endpoint_staff_dashboard(): void
    {
        $response = $this->actingAs($this->student, 'sanctum')
            ->getJson('/api/v1/staff/dashboard');

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden. You do not have permission to access this resource.',
            ]);
    }

    public function test_student_tidak_dapat_mengakses_endpoint_staff_books_create_update_delete(): void
    {
        // 1. Create book
        $resCreate = $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/v1/staff/books', [
                'title' => 'Buku Ilegal',
                'author' => 'Hacker',
                'stock' => 10,
            ]);
        $resCreate->assertForbidden();

        // 2. Update book
        $book = Book::factory()->create();
        $resUpdate = $this->actingAs($this->student, 'sanctum')
            ->putJson("/api/v1/staff/books/{$book->id}", [
                'title' => 'Buku Diubah',
            ]);
        $resUpdate->assertForbidden();

        // 3. Delete book
        $resDelete = $this->actingAs($this->student, 'sanctum')
            ->deleteJson("/api/v1/staff/books/{$book->id}");
        $resDelete->assertForbidden();
    }

    public function test_student_tidak_dapat_mengakses_endpoint_staff_borrowings(): void
    {
        $resList = $this->actingAs($this->student, 'sanctum')
            ->getJson('/api/v1/staff/borrowings');
        $resList->assertForbidden();

        $borrowing = Borrowing::factory()->create();

        $resApprove = $this->actingAs($this->student, 'sanctum')
            ->postJson("/api/v1/staff/borrowings/{$borrowing->id}/approve");
        $resApprove->assertForbidden();

        $resBorrow = $this->actingAs($this->student, 'sanctum')
            ->postJson("/api/v1/staff/borrowings/{$borrowing->id}/borrow");
        $resBorrow->assertForbidden();

        $resReturn = $this->actingAs($this->student, 'sanctum')
            ->postJson("/api/v1/staff/borrowings/{$borrowing->id}/return");
        $resReturn->assertForbidden();
    }

    public function test_student_tidak_dapat_melihat_borrowing_milik_student_lain(): void
    {
        $borrowingOther = Borrowing::factory()->create([
            'user_id' => $this->otherStudent->id,
        ]);

        $response = $this->actingAs($this->student, 'sanctum')
            ->getJson("/api/v1/borrowings/{$borrowingOther->id}");

        $response->assertForbidden();
    }

    public function test_student_dapat_melihat_borrowing_miliknya_sendiri(): void
    {
        $borrowingOwn = Borrowing::factory()->create([
            'user_id' => $this->student->id,
        ]);

        $response = $this->actingAs($this->student, 'sanctum')
            ->getJson("/api/v1/borrowings/{$borrowingOwn->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $borrowingOwn->id);
    }

    public function test_staff_dapat_melihat_borrowing_milik_siapapun(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->student->id,
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/v1/borrowings/{$borrowing->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $borrowing->id);
    }

    public function test_unauthenticated_request_mengembalikan_401(): void
    {
        $response = $this->getJson('/api/v1/borrowings/me');

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }
}

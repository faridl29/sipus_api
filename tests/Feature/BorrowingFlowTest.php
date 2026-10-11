<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $staff;

    protected Book $book1;

    protected Book $book2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->staff = User::factory()->staff()->create();

        $this->book1 = Book::factory()->create([
            'title' => 'Buku Algoritma',
            'stock' => 5,
        ]);

        $this->book2 = Book::factory()->create([
            'title' => 'Buku Struktur Data',
            'stock' => 3,
        ]);
    }

    public function test_student_dapat_membuat_borrowing_pending(): void
    {
        $response = $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/v1/borrowings', [
                'items' => [
                    ['book_id' => $this->book1->id, 'quantity' => 2],
                    ['book_id' => $this->book2->id, 'quantity' => 1],
                ],
                'notes' => 'Keperluan tugas akhir',
            ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Borrowing request created successfully',
            ])
            ->assertJsonPath('data.status', Borrowing::STATUS_PENDING);

        $this->assertMatchesRegularExpression('/^TRX-\d{8}-\d{4}$/', $response->json('data.transaction_code'));

        $this->assertDatabaseHas('borrowings', [
            'user_id' => $this->student->id,
            'status' => Borrowing::STATUS_PENDING,
            'notes' => 'Keperluan tugas akhir',
        ]);

        // Stock should NOT decrease during PENDING status
        $this->assertEquals(5, $this->book1->fresh()->stock);
        $this->assertEquals(3, $this->book2->fresh()->stock);
    }

    public function test_staff_dapat_melihat_borrowing(): void
    {
        Borrowing::factory()->create([
            'user_id' => $this->student->id,
            'status' => Borrowing::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/staff/borrowings?status=PENDING');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_staff_dapat_approve_pending_dan_approved_tidak_langsung_menjadi_borrowed(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->student->id,
            'status' => Borrowing::STATUS_PENDING,
        ]);
        BorrowingItem::factory()->create([
            'borrowing_id' => $borrowing->id,
            'book_id' => $this->book1->id,
            'quantity' => 2,
        ]);

        // Staff approves
        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/staff/borrowings/{$borrowing->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', Borrowing::STATUS_APPROVED);

        $borrowing->refresh();

        // Check rule 8: APPROVED tidak langsung menjadi BORROWED
        $this->assertEquals(Borrowing::STATUS_APPROVED, $borrowing->status);
        $this->assertNull($borrowing->borrowed_at);

        // Stock must still be unchanged
        $this->assertEquals(5, $this->book1->fresh()->stock);
    }

    public function test_staff_dapat_confirm_borrowing_approved_menjadi_borrowed_dan_stock_berkurang(): void
    {
        $borrowing = Borrowing::factory()->approved()->create([
            'user_id' => $this->student->id,
        ]);
        BorrowingItem::factory()->create([
            'borrowing_id' => $borrowing->id,
            'book_id' => $this->book1->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/staff/borrowings/{$borrowing->id}/borrow");

        $response->assertOk()
            ->assertJsonPath('data.status', Borrowing::STATUS_BORROWED);

        $borrowing->refresh();
        $this->assertEquals(Borrowing::STATUS_BORROWED, $borrowing->status);
        $this->assertNotNull($borrowing->borrowed_at);

        // Check rule 11: Stock berkurang ketika borrowing menjadi BORROWED
        $this->assertEquals(3, $this->book1->fresh()->stock); // 5 - 2 = 3
    }

    public function test_staff_dapat_return_borrowing_dan_stock_bertambah_kembali(): void
    {
        $borrowing = Borrowing::factory()->borrowed()->create([
            'user_id' => $this->student->id,
        ]);
        BorrowingItem::factory()->create([
            'borrowing_id' => $borrowing->id,
            'book_id' => $this->book1->id,
            'quantity' => 2,
        ]);

        // Set initial stock after borrow
        $this->book1->update(['stock' => 3]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/staff/borrowings/{$borrowing->id}/return");

        $response->assertOk()
            ->assertJsonPath('data.status', Borrowing::STATUS_RETURNED);

        $borrowing->refresh();
        $this->assertEquals(Borrowing::STATUS_RETURNED, $borrowing->status);
        $this->assertNotNull($borrowing->returned_at);

        // Check rule 14: Stock bertambah ketika borrowing menjadi RETURNED
        $this->assertEquals(5, $this->book1->fresh()->stock); // 3 + 2 = 5
    }

    public function test_invalid_status_transitions_ditolak(): void
    {
        // 1. PENDING -> BORROWED (tidak boleh langsung confirm tanpa approve)
        $pending = Borrowing::factory()->create([
            'user_id' => $this->student->id,
            'status' => Borrowing::STATUS_PENDING,
        ]);
        $resp1 = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/staff/borrowings/{$pending->id}/borrow");
        $resp1->assertStatus(422)
            ->assertJson(['success' => false]);

        // 2. PENDING -> RETURNED (tidak boleh di-return jika belum borrowed)
        $resp2 = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/staff/borrowings/{$pending->id}/return");
        $resp2->assertStatus(422);

        // 3. REJECTED -> BORROWED (tidak boleh dipinjam)
        $rejected = Borrowing::factory()->rejected()->create([
            'user_id' => $this->student->id,
        ]);
        $resp3 = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/staff/borrowings/{$rejected->id}/borrow");
        $resp3->assertStatus(422);

        // 4. RETURNED -> BORROWED (transaksi yang sudah selesai tidak boleh diulang)
        $returned = Borrowing::factory()->returned()->create([
            'user_id' => $this->student->id,
        ]);
        $resp4 = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/staff/borrowings/{$returned->id}/borrow");
        $resp4->assertStatus(422);

        // 5. RETURNED -> APPROVED
        $resp5 = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/staff/borrowings/{$returned->id}/approve");
        $resp5->assertStatus(422);
    }

    public function test_stock_tidak_boleh_negatif_dan_konfirmasi_peminjaman_gagal_jika_stok_kurang(): void
    {
        // Set book stock to 1
        $this->book1->update(['stock' => 1]);

        $borrowing = Borrowing::factory()->approved()->create([
            'user_id' => $this->student->id,
        ]);
        // Borrowing requests 2 books, but only 1 available
        BorrowingItem::factory()->create([
            'borrowing_id' => $borrowing->id,
            'book_id' => $this->book1->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/staff/borrowings/{$borrowing->id}/borrow");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        // Stock must remain unchanged at 1 and never go below zero
        $this->assertEquals(1, $this->book1->fresh()->stock);
        // Borrowing status must remain APPROVED, not BORROWED
        $this->assertEquals(Borrowing::STATUS_APPROVED, $borrowing->fresh()->status);
    }

    public function test_pengajuan_peminjaman_ditolak_jika_stok_buku_tidak_mencukupi_pada_input(): void
    {
        $outOfStockBook = Book::factory()->outOfStock()->create();

        $response = $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/v1/borrowings', [
                'items' => [
                    ['book_id' => $outOfStockBook->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_staff_dapat_mencatat_peminjaman_langsung_walk_in_dan_stok_langsung_berkurang(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/staff/borrowings/direct', [
                'user_id' => $this->student->id,
                'items' => [
                    ['book_id' => $this->book1->id, 'quantity' => 2],
                ],
                'notes' => 'Peminjaman langsung di tempat',
            ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Direct borrowing created successfully',
            ])
            ->assertJsonPath('data.status', Borrowing::STATUS_BORROWED)
            ->assertJsonPath('data.is_overdue', false);

        // Stock immediately reduced from 5 to 3
        $this->assertEquals(3, $this->book1->fresh()->stock);

        // Verify borrowing in database
        $this->assertDatabaseHas('borrowings', [
            'user_id' => $this->student->id,
            'status' => Borrowing::STATUS_BORROWED,
        ]);

        $borrowing = Borrowing::latest()->first();
        $this->assertNotNull($borrowing->borrowed_at);
        $this->assertNotNull($borrowing->due_date);
    }

    public function test_peminjaman_ditolak_jika_due_date_lebih_dari_3_hari(): void
    {
        $fourDaysLater = now()->addDays(4)->toDateString();

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/staff/borrowings/direct', [
                'user_id' => $this->student->id,
                'due_date' => $fourDaysLater,
                'items' => [
                    ['book_id' => $this->book1->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    }

    public function test_staff_dapat_mencatat_peminjaman_langsung_untuk_tamu_tanpa_akun(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/staff/borrowings/direct', [
                'borrower_name' => 'Budi Santoso',
                'borrower_phone' => '081299998888',
                'borrower_type' => 'guest',
                'items' => [
                    ['book_id' => $this->book1->id, 'quantity' => 1],
                ],
                'notes' => 'Peminjaman tamu perpustakaan',
            ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Direct borrowing created successfully',
            ])
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.borrower_name', 'Budi Santoso')
            ->assertJsonPath('data.borrower_type', 'guest')
            ->assertJsonPath('data.status', Borrowing::STATUS_BORROWED);

        $this->assertEquals(4, $this->book1->fresh()->stock); // 5 - 1 = 4
    }

    public function test_peminjaman_langsung_ditolak_jika_user_id_dan_nama_peminjam_kosong(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/staff/borrowings/direct', [
                'items' => [
                    ['book_id' => $this->book1->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['borrower_name']);
    }

    public function test_staff_dapat_mencatat_peminjaman_langsung_untuk_guru_atau_staff_sekolah(): void
    {
        $teacher = User::factory()->staff()->create(['name' => 'Pak Guru Matematika']);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/staff/borrowings/direct', [
                'user_id' => $teacher->id,
                'items' => [
                    ['book_id' => $this->book1->id, 'quantity' => 1],
                ],
                'notes' => 'Pinjam untuk referensi ajar kelas 10',
            ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.user_id', $teacher->id)
            ->assertJsonPath('data.status', Borrowing::STATUS_BORROWED);
    }

    public function test_transaksi_terdeteksi_overdue_jika_melewati_due_date(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->student->id,
            'status' => Borrowing::STATUS_BORROWED,
            'borrowed_at' => now()->subDays(4),
            'due_date' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->student, 'sanctum')
            ->getJson("/api/v1/borrowings/{$borrowing->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_overdue', true);
    }

    public function test_guru_dapat_meminjam_langsung_walk_in_melalui_petugas_hingga_7_hari(): void
    {
        // 7 days later: allowed for teacher walk-in
        $sevenDaysLater = now()->addDays(7)->toDateString();
        $responseAllowed = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/staff/borrowings/direct', [
                'borrower_name' => 'Ibu Guru Sejarah',
                'borrower_identity_number' => '198501012010011001',
                'borrower_type' => 'teacher',
                'due_date' => $sevenDaysLater,
                'items' => [
                    ['book_id' => $this->book1->id, 'quantity' => 1],
                ],
                'notes' => 'Peminjaman buku pengayaan oleh guru',
            ]);

        $responseAllowed->assertCreated()
            ->assertJsonPath('data.borrower_name', 'Ibu Guru Sejarah')
            ->assertJsonPath('data.borrower_type', 'teacher')
            ->assertJsonPath('data.status', Borrowing::STATUS_BORROWED);

        // 8 days later: rejected because max 7 days for teacher
        $eightDaysLater = now()->addDays(8)->toDateString();
        $responseRejected = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/staff/borrowings/direct', [
                'borrower_name' => 'Ibu Guru Sejarah',
                'borrower_type' => 'teacher',
                'due_date' => $eightDaysLater,
                'items' => [
                    ['book_id' => $this->book1->id, 'quantity' => 1],
                ],
            ]);

        $responseRejected->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    }

    public function test_staff_tidak_bisa_membuat_pengajuan_peminjaman_online_siswa(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/borrowings', [
                'items' => [
                    ['book_id' => $this->book1->id, 'quantity' => 1],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_detail_peminjaman_menampilkan_nomor_identitas_peminjam_dan_guest(): void
    {
        // 1. Registered member with identity_number
        $student = User::factory()->student()->create([
            'identity_number' => '202410999',
        ]);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $student->id,
            'status' => Borrowing::STATUS_PENDING,
        ]);

        $responseStudent = $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/v1/borrowings/{$borrowing->id}");

        $responseStudent->assertOk()
            ->assertJsonPath('data.borrower_identity_number', '202410999')
            ->assertJsonPath('data.user.identity_number', '202410999');

        // 2. Direct walk-in guest with borrower_identity_number
        $responseGuest = $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/v1/staff/borrowings/direct', [
                'borrower_name' => 'Bapak Joko (Wali Murid)',
                'borrower_phone' => '081234567899',
                'borrower_identity_number' => '3201234567890005',
                'borrower_type' => 'guest',
                'items' => [
                    ['book_id' => $this->book1->id, 'quantity' => 1],
                ],
            ]);

        $responseGuest->assertCreated()
            ->assertJsonPath('data.borrower_name', 'Bapak Joko (Wali Murid)')
            ->assertJsonPath('data.borrower_identity_number', '3201234567890005')
            ->assertJsonPath('data.user.identity_number', '3201234567890005');
    }
}

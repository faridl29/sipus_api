<?php

namespace App\Http\Resources;

/**
 * @OA\Schema(
 *     schema="UserResource",
 *     title="User Resource",
 *     description="User data model",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Budi Santoso"),
 *     @OA\Property(property="email", type="string", example="student@example.com"),
 *     @OA\Property(property="identity_number", type="string", nullable=true, example="20241001", description="Nomor Induk Siswa (NIS) / ID Staff"),
 *     @OA\Property(property="role", type="string", enum={"student","staff"}, example="student"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2026-09-29T10:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-09-29T10:00:00.000000Z")
 * )
 *
 * @OA\Schema(
 *     schema="BookResource",
 *     title="Book Resource",
 *     description="Book data model",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Laskar Pelangi"),
 *     @OA\Property(property="author", type="string", example="Andrea Hirata"),
 *     @OA\Property(property="publisher", type="string", example="Bentang Pustaka"),
 *     @OA\Property(property="publication_year", type="integer", example=2005),
 *     @OA\Property(property="isbn", type="string", example="978-979-3062-79-2"),
 *     @OA\Property(property="stock", type="integer", example=10),
 *     @OA\Property(property="is_available", type="boolean", example=true),
 *     @OA\Property(property="description", type="string", example="Novel tentang perjuangan sepuluh anak di Belitung."),
 *     @OA\Property(property="cover", type="string", example="https://example.com/covers/laskar-pelangi.jpg"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2026-09-29T10:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-09-29T10:00:00.000000Z")
 * )
 *
 * @OA\Schema(
 *     schema="BorrowingItemResource",
 *     title="Borrowing Item Resource",
 *     description="Detail item buku dalam peminjaman",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="borrowing_id", type="integer", example=1),
 *     @OA\Property(property="book_id", type="integer", example=1),
 *     @OA\Property(property="quantity", type="integer", example=1),
 *     @OA\Property(property="book", ref="#/components/schemas/BookResource"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2026-09-29T10:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-09-29T10:00:00.000000Z")
 * )
 *
 * @OA\Schema(
 *     schema="BorrowingResource",
 *     title="Borrowing Resource",
 *     description="Borrowing transaction model",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="borrower_name", type="string", example="Siswa Perpustakaan"),
 *     @OA\Property(property="borrower_phone", type="string", nullable=true, example="081234567890"),
 *     @OA\Property(property="borrower_identity_number", type="string", nullable=true, example="20241001", description="Nomor identitas peminjam (NIS/NIP/KTP)"),
 *     @OA\Property(property="borrower_type", type="string", example="student", enum={"student","teacher","staff","guest"}),
 *     @OA\Property(property="user", ref="#/components/schemas/UserResource"),
 *     @OA\Property(property="status", type="string", enum={"PENDING","APPROVED","REJECTED","BORROWED","RETURNED"}, example="PENDING"),
 *     @OA\Property(property="borrowed_at", type="string", format="date-time", nullable=true, example="2026-09-29T10:00:00.000000Z"),
 *     @OA\Property(property="due_date", type="string", format="date-time", nullable=true, example="2026-10-02T10:00:00.000000Z"),
 *     @OA\Property(property="returned_at", type="string", format="date-time", nullable=true, example=null),
 *     @OA\Property(property="is_overdue", type="boolean", example=false),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Untuk keperluan belajar"),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/BorrowingItemResource")),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2026-09-29T10:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-09-29T10:00:00.000000Z")
 * )
 */
class OpenApiSchemas
{
    // Schema definitions for OpenAPI/Swagger
}

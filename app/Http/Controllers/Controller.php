<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Sistem Informasi Perpustakaan Sekolah (SIPUS) API",
 *     description="Dokumentasi REST API Sistem Informasi Perpustakaan Sekolah untuk Aplikasi Siswa dan Dashboard Petugas.",
 *
 *     @OA\Contact(
 *         email="admin@perpustakaan.sch.id"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/",
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Bearer Token",
 *     description="Masukkan Sanctum Token"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="Endpoint autentikasi (login, logout, me)"
 * )
 * @OA\Tag(
 *     name="Books",
 *     description="Katalog buku untuk siswa"
 * )
 * @OA\Tag(
 *     name="Borrowings",
 *     description="Pengajuan dan riwayat peminjaman siswa"
 * )
 * @OA\Tag(
 *     name="Staff - Dashboard",
 *     description="Statistik dashboard petugas"
 * )
 * @OA\Tag(
 *     name="Staff - Books",
 *     description="Manajemen inventaris buku oleh petugas"
 * )
 * @OA\Tag(
 *     name="Staff - Borrowings",
 *     description="Manajemen dan konfirmasi alur peminjaman oleh petugas"
 * )
 */
abstract class Controller
{
    use ApiResponse;
}

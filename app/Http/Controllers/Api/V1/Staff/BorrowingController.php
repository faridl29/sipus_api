<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Borrowing\DirectBorrowingRequest;
use App\Http\Requests\Borrowing\RejectBorrowingRequest;
use App\Http\Resources\BorrowingResource;
use App\Models\Borrowing;
use App\Models\User;
use App\Services\BorrowingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BorrowingController extends Controller
{
    public function __construct(
        protected BorrowingService $borrowingService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/staff/borrowings",
     *     operationId="getStaffBorrowings",
     *     tags={"Staff - Borrowings"},
     *     summary="Daftar pengajuan peminjaman (Petugas)",
     *     description="Menampilkan seluruh data peminjaman buku dengan dukungan filter status (misal: PENDING) dan pagination.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Pencarian kode transaksi, nama peminjam, nomor identitas (NIS/NIP), atau judul buku",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter berdasarkan status (PENDING, APPROVED, REJECTED, BORROWED, RETURNED)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"PENDING","APPROVED","REJECTED","BORROWED","RETURNED"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Jumlah item per halaman",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Nomor halaman",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Daftar peminjaman berhasil diambil",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Borrowings retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BorrowingResource")),
     *             @OA\Property(property="meta", type="object"),
     *             @OA\Property(property="links", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Borrowing::with(['user', 'items.book']);

        if ($request->filled('search')) {
            $query->search($request->query('search'));
        }

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);
        $borrowings = $query->latest()->paginate($perPage);

        $resourceCollection = BorrowingResource::collection($borrowings);
        $paginatedData = $resourceCollection->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => 'Borrowings retrieved successfully',
            'data' => $paginatedData['data'],
            'meta' => $paginatedData['meta'] ?? null,
            'links' => $paginatedData['links'] ?? null,
        ], Response::HTTP_OK);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/staff/borrowings/{id}/approve",
     *     operationId="approveBorrowing",
     *     tags={"Staff - Borrowings"},
     *     summary="Setujui pengajuan peminjaman (PENDING -> APPROVED)",
     *     description="Petugas menyetujui pengajuan peminjaman. Buku belum dianggap dipinjam dan stok belum berkurang sampai dilakukan konfirmasi serah terima.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID Peminjaman",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Pengajuan berhasil disetujui",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Borrowing approved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BorrowingResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Transisi status tidak valid (bukan PENDING)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Perubahan status dari REJECTED ke APPROVED tidak diizinkan.")
     *         )
     *     )
     * )
     */
    public function approve(int $id): JsonResponse
    {
        $borrowing = Borrowing::findOrFail($id);
        $updatedBorrowing = $this->borrowingService->approveBorrowing($borrowing);

        return $this->successResponse(new BorrowingResource($updatedBorrowing), 'Borrowing approved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/staff/borrowings/{id}/reject",
     *     operationId="rejectBorrowing",
     *     tags={"Staff - Borrowings"},
     *     summary="Tolak pengajuan peminjaman (PENDING -> REJECTED)",
     *     description="Petugas menolak pengajuan peminjaman yang berstatus PENDING.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID Peminjaman",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="notes", type="string", example="Buku sedang dalam perbaikan fisik.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Pengajuan berhasil ditolak",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Borrowing rejected successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BorrowingResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Transisi status tidak valid (bukan PENDING)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function reject(RejectBorrowingRequest $request, int $id): JsonResponse
    {
        $borrowing = Borrowing::findOrFail($id);
        $updatedBorrowing = $this->borrowingService->rejectBorrowing($borrowing, $request->input('notes'));

        return $this->successResponse(new BorrowingResource($updatedBorrowing), 'Borrowing rejected successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/staff/borrowings/{id}/borrow",
     *     operationId="confirmBorrowing",
     *     tags={"Staff - Borrowings"},
     *     summary="Konfirmasi penyerahan buku (APPROVED -> BORROWED)",
     *     description="Petugas mengonfirmasi buku telah diserahkan kepada siswa. Transaksi menjadi aktif, borrowed_at dicatat, dan stok buku berkurang.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID Peminjaman",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Peminjaman berhasil dikonfirmasi dan stok berkurang",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Borrowing confirmed successfully. Books handed over to student."),
     *             @OA\Property(property="data", ref="#/components/schemas/BorrowingResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Transisi status tidak valid atau stok buku tidak mencukupi",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function borrow(int $id): JsonResponse
    {
        $borrowing = Borrowing::findOrFail($id);
        $updatedBorrowing = $this->borrowingService->confirmBorrowing($borrowing);

        return $this->successResponse(
            new BorrowingResource($updatedBorrowing),
            'Borrowing confirmed successfully. Books handed over to student.'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/staff/borrowings/{id}/return",
     *     operationId="returnBorrowing",
     *     tags={"Staff - Borrowings"},
     *     summary="Catat pengembalian buku (BORROWED -> RETURNED)",
     *     description="Petugas mencatat bahwa buku telah dikembalikan oleh siswa. Transaksi selesai, returned_at dicatat, dan stok buku bertambah kembali.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID Peminjaman",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Buku berhasil dicatat pengembaliannya dan stok bertambah",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Borrowing returned successfully. Books added back to stock."),
     *             @OA\Property(property="data", ref="#/components/schemas/BorrowingResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Transisi status tidak valid (bukan BORROWED)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function returnBorrowing(int $id): JsonResponse
    {
        $borrowing = Borrowing::findOrFail($id);
        $updatedBorrowing = $this->borrowingService->returnBorrowing($borrowing);

        return $this->successResponse(
            new BorrowingResource($updatedBorrowing),
            'Borrowing returned successfully. Books added back to stock.'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/staff/borrowings/history",
     *     operationId="getStaffBorrowingHistory",
     *     tags={"Staff - Borrowings"},
     *     summary="Riwayat transaksi peminjaman (Petugas)",
     *     description="Melihat seluruh histori transaksi peminjaman (biasanya RETURNED atau REJECTED, atau seluruh status) dengan pencarian dan pagination.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Pencarian kode transaksi, nama peminjam, nomor identitas (NIS/NIP), atau judul buku",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter berdasarkan status tertentu",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Jumlah item per halaman",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Nomor halaman",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Riwayat transaksi berhasil diambil",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transaction history retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BorrowingResource")),
     *             @OA\Property(property="meta", type="object"),
     *             @OA\Property(property="links", type="object")
     *         )
     *     )
     * )
     */
    public function history(Request $request): JsonResponse
    {
        $query = Borrowing::with(['user', 'items.book']);

        if ($request->filled('search')) {
            $query->search($request->query('search'));
        }

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);
        $borrowings = $query->latest()->paginate($perPage);

        $resourceCollection = BorrowingResource::collection($borrowings);
        $paginatedData = $resourceCollection->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => 'Transaction history retrieved successfully',
            'data' => $paginatedData['data'],
            'meta' => $paginatedData['meta'] ?? null,
            'links' => $paginatedData['links'] ?? null,
        ], Response::HTTP_OK);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/staff/borrowings/direct",
     *     operationId="directBorrowingByStaff",
     *     tags={"Staff - Borrowings"},
     *     summary="Peminjaman langsung di tempat (Walk-in / Tamu / Siswa / Guru)",
     *     description="Petugas mencatat peminjaman langsung di tempat untuk siswa terdaftar, guru/staf, maupun tamu umum. Status langsung menjadi BORROWED, stok buku otomatis berkurang, dan batas waktu pengembalian maksimal 3 hari.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"items"},
     *
     *             @OA\Property(property="user_id", type="integer", nullable=true, example=2, description="ID siswa/user terdaftar (opsional jika tamu)"),
     *             @OA\Property(property="borrower_name", type="string", example="Ahmad Pengunjung", description="Nama peminjam (wajib jika user_id kosong)"),
     *             @OA\Property(property="borrower_phone", type="string", example="081234567890", description="Nomor telepon peminjam"),
     *             @OA\Property(property="borrower_identity_number", type="string", example="3201234567890001", description="Nomor identitas peminjam (NIS/NIP/KTP)"),
     *             @OA\Property(property="borrower_type", type="string", example="guest", enum={"student","teacher","staff","guest"}),
     *             @OA\Property(property="due_date", type="string", format="date", example="2026-10-02", description="Batas pengembalian (maksimal 3 hari untuk siswa/tamu, 7 hari untuk guru)"),
     *             @OA\Property(property="notes", type="string", example="Peminjaman langsung di perpustakaan"),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"book_id", "quantity"},
     *
     *                     @OA\Property(property="book_id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Peminjaman langsung berhasil dicatat",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Direct borrowing created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BorrowingResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal atau stok buku tidak mencukupi",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function direct(DirectBorrowingRequest $request): JsonResponse
    {
        $userId = $request->validated('user_id');
        $user = $userId ? User::find($userId) : null;

        $borrowing = $this->borrowingService->createDirectBorrowing(
            user: $user,
            itemsData: $request->validated('items'),
            dueDate: $request->validated('due_date'),
            borrowerName: $request->validated('borrower_name'),
            borrowerPhone: $request->validated('borrower_phone'),
            borrowerIdentityNumber: $request->validated('borrower_identity_number'),
            borrowerType: $request->validated('borrower_type'),
            notes: $request->validated('notes')
        );

        return $this->successResponse(
            new BorrowingResource($borrowing),
            'Direct borrowing created successfully',
            Response::HTTP_CREATED
        );
    }
}

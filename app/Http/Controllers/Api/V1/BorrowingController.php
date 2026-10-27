<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Borrowing\StoreBorrowingRequest;
use App\Http\Resources\BorrowingResource;
use App\Models\Borrowing;
use App\Services\BorrowingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class BorrowingController extends Controller
{
    public function __construct(
        protected BorrowingService $borrowingService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/borrowings",
     *     operationId="storeBorrowing",
     *     tags={"Borrowings"},
     *     summary="Ajukan peminjaman buku (Siswa)",
     *     description="Siswa mengajukan peminjaman satu atau beberapa buku. Status awal otomatis PENDING. Maksimal durasi pinjam 3 hari.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"items"},
     *
     *             @OA\Property(property="due_date", type="string", format="date", example="2026-10-02", description="Batas pengembalian (maks 3 hari)"),
     *             @OA\Property(property="notes", type="string", example="Untuk keperluan belajar ujian"),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     required={"book_id","quantity"},
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
     *         description="Pengajuan peminjaman berhasil dibuat",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Borrowing request created successfully"),
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
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Hanya siswa yang dapat mengajukan peminjaman",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Forbidden. You do not have permission to access this resource.")
     *         )
     *     )
     * )
     */
    public function store(StoreBorrowingRequest $request): JsonResponse
    {
        $borrowing = $this->borrowingService->createBorrowing(
            user: $request->user(),
            itemsData: $request->validated('items'),
            dueDate: $request->validated('due_date'),
            notes: $request->validated('notes')
        );

        return $this->successResponse(
            new BorrowingResource($borrowing),
            'Borrowing request created successfully',
            Response::HTTP_CREATED
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/borrowings/me",
     *     operationId="getMyBorrowings",
     *     tags={"Borrowings"},
     *     summary="Daftar peminjaman saya (Siswa)",
     *     description="Mengambil riwayat transaksi peminjaman milik siswa yang sedang login. Mendukung filter status dan pagination.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Kata kunci pencarian (kode transaksi, judul buku)",
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
    public function myBorrowings(Request $request): JsonResponse
    {
        $query = $request->user()->borrowings()->with(['items.book']);

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
     * @OA\Get(
     *     path="/api/v1/borrowings/{id}",
     *     operationId="getBorrowingDetail",
     *     tags={"Borrowings"},
     *     summary="Detail transaksi peminjaman",
     *     description="Melihat detail transaksi peminjaman. Siswa hanya dapat melihat transaksi miliknya sendiri, petugas dapat melihat seluruh transaksi.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID Transaksi Peminjaman",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Detail peminjaman berhasil diambil",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Borrowing retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BorrowingResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Akses ditolak (bukan pemilik atau bukan petugas)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Forbidden. You do not have permission to access this resource.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Transaksi peminjaman tidak ditemukan",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     )
     * )
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $borrowing = Borrowing::with(['items.book', 'user'])->findOrFail($id);

        Gate::authorize('view', $borrowing);

        return $this->successResponse(new BorrowingResource($borrowing), 'Borrowing retrieved successfully');
    }
}

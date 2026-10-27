<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BookController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/books",
     *     operationId="getPublicBooks",
     *     tags={"Books"},
     *     summary="Katalog buku perpustakaan (Publik / OPAC)",
     *     description="Menampilkan daftar katalog buku dengan pagination dan filter pencarian berdasarkan judul, penulis, penerbit, atau ISBN. Dapat diakses publik tanpa login.",
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Kata kunci pencarian (judul, penulis, penerbit, ISBN)",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="available_only",
     *         in="query",
     *         description="Filter hanya buku yang stoknya tersedia (true/1)",
     *         required=false,
     *
     *         @OA\Schema(type="boolean")
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Jumlah item per halaman (default 10)",
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
     *         description="Daftar buku berhasil diambil",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Books retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/BookResource")
     *             ),
     *
     *             @OA\Property(property="meta", type="object"),
     *             @OA\Property(property="links", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Book::query();

        if ($request->filled('search')) {
            $query->search($request->query('search'));
        }

        if ($request->boolean('available_only') || $request->boolean('available')) {
            $query->available();
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);
        $books = $query->latest()->paginate($perPage);

        $resourceCollection = BookResource::collection($books);
        $paginatedData = $resourceCollection->response()->getData(true);

        return response()->json([
            'success' => true,
            'message' => 'Books retrieved successfully',
            'data' => $paginatedData['data'],
            'meta' => $paginatedData['meta'] ?? null,
            'links' => $paginatedData['links'] ?? null,
        ], Response::HTTP_OK);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/books/{id}",
     *     operationId="getPublicBookDetail",
     *     tags={"Books"},
     *     summary="Detail buku (Publik)",
     *     description="Mengambil detail buku berdasarkan ID beserta ketersediaan stok. Dapat diakses publik tanpa login.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID Buku",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Detail buku berhasil diambil",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BookResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Buku tidak ditemukan",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $book = Book::findOrFail($id);

        return $this->successResponse(new BookResource($book), 'Book retrieved successfully');
    }
}

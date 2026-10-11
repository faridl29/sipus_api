<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Book\StoreBookRequest;
use App\Http\Requests\Book\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class BookController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/staff/books",
     *     operationId="getStaffBooks",
     *     tags={"Staff - Books"},
     *     summary="Daftar buku inventaris (Petugas)",
     *     description="Menampilkan semua buku inventaris dengan filter pencarian dan pagination.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Pencarian judul, penulis, penerbit, atau ISBN",
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
     *     @OA\Response(
     *         response=200,
     *         description="Daftar buku berhasil diambil",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Books retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BookResource")),
     *             @OA\Property(property="meta", type="object"),
     *             @OA\Property(property="links", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Book::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%")
                    ->orWhere('publisher', 'like', "%{$search}%")
                    ->orWhere('isbn', 'like', "%{$search}%");
            });
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
     * @OA\Post(
     *     path="/api/v1/staff/books",
     *     operationId="storeBook",
     *     tags={"Staff - Books"},
     *     summary="Tambah buku baru (Petugas)",
     *     description="Menambahkan buku baru ke sistem perpustakaan.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"title","author","stock"},
     *
     *             @OA\Property(property="title", type="string", example="Laskar Pelangi"),
     *             @OA\Property(property="author", type="string", example="Andrea Hirata"),
     *             @OA\Property(property="publisher", type="string", example="Bentang Pustaka"),
     *             @OA\Property(property="publication_year", type="integer", example=2005),
     *             @OA\Property(property="isbn", type="string", example="978-979-3062-79-2"),
     *             @OA\Property(property="stock", type="integer", example=10),
     *             @OA\Property(property="description", type="string", example="Novel tentang perjuangan sepuluh anak di Belitung."),
     *             @OA\Property(property="cover", type="string", example="https://example.com/covers/laskar-pelangi.jpg")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Buku berhasil ditambahkan",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BookResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('cover')) {
            $data['cover'] = $request->file('cover')->store('covers', 'public');
        }

        $book = Book::create($data);

        return $this->successResponse(
            new BookResource($book),
            'Book created successfully',
            Response::HTTP_CREATED
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/staff/books/{id}",
     *     operationId="getStaffBookDetail",
     *     tags={"Staff - Books"},
     *     summary="Detail buku inventaris (Petugas)",
     *     description="Mengambil detail buku berdasarkan ID.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
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

    /**
     * @OA\Put(
     *     path="/api/v1/staff/books/{id}",
     *     operationId="updateBook",
     *     tags={"Staff - Books"},
     *     summary="Ubah data buku (Petugas)",
     *     description="Memperbarui data dan stok buku.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="title", type="string", example="Laskar Pelangi Edisi Spesial"),
     *             @OA\Property(property="author", type="string", example="Andrea Hirata"),
     *             @OA\Property(property="stock", type="integer", example=15)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Buku berhasil diperbarui",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BookResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed")
     *         )
     *     )
     * )
     */
    public function update(UpdateBookRequest $request, int $id): JsonResponse
    {
        $book = Book::findOrFail($id);
        $data = $request->validated();

        if ($request->hasFile('cover')) {
            if ($book->cover && ! filter_var($book->cover, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($book->cover)) {
                Storage::disk('public')->delete($book->cover);
            }
            $data['cover'] = $request->file('cover')->store('covers', 'public');
        }

        $book->update($data);

        return $this->successResponse(new BookResource($book), 'Book updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/staff/books/{id}",
     *     operationId="deleteBook",
     *     tags={"Staff - Books"},
     *     summary="Hapus buku (Soft delete) (Petugas)",
     *     description="Menonaktifkan / menghapus buku secara soft delete agar riwayat transaksi masa lalu tetap utuh.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Buku berhasil dihapus (soft delete)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book deleted successfully"),
     *             @OA\Property(property="data", type="null", example=null)
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
    public function destroy(int $id): JsonResponse
    {
        $book = Book::findOrFail($id);
        $book->delete();

        return $this->successResponse(null, 'Book deleted successfully');
    }
}

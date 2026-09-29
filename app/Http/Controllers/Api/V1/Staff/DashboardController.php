<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Borrowing;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/staff/dashboard",
     *     operationId="getStaffDashboard",
     *     tags={"Staff - Dashboard"},
     *     summary="Ringkasan statistik dashboard petugas",
     *     description="Menampilkan total buku, pengajuan pending, peminjaman aktif (BORROWED), dan transaksi selesai (RETURNED). Hanya untuk role staff.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Statistik dashboard berhasil diambil",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Dashboard statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_books", type="integer", example=120),
     *                 @OA\Property(property="pending_borrowings", type="integer", example=5),
     *                 @OA\Property(property="active_borrowings", type="integer", example=12),
     *                 @OA\Property(property="completed_borrowings", type="integer", example=45)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Hanya untuk petugas",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Forbidden. You do not have permission to access this resource.")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $stats = [
            'total_books' => Book::count(),
            'pending_borrowings' => Borrowing::where('status', Borrowing::STATUS_PENDING)->count(),
            'active_borrowings' => Borrowing::where('status', Borrowing::STATUS_BORROWED)->count(),
            'completed_borrowings' => Borrowing::where('status', Borrowing::STATUS_RETURNED)->count(),
        ];

        return $this->successResponse($stats, 'Dashboard statistics retrieved successfully');
    }
}

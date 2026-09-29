<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InsufficientStockException extends Exception
{
    public function __construct(string $bookTitle, int $availableStock, int $requestedQuantity)
    {
        parent::__construct("Stok buku '{$bookTitle}' tidak mencukupi untuk peminjaman (tersedia: {$availableStock}, diminta: {$requestedQuantity}).");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

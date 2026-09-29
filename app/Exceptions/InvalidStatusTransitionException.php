<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InvalidStatusTransitionException extends Exception
{
    public function __construct(string $fromStatus, string $toStatus)
    {
        parent::__construct("Perubahan status dari {$fromStatus} ke {$toStatus} tidak diizinkan.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

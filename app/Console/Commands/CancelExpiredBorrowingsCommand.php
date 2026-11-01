<?php

namespace App\Console\Commands;

use App\Services\BorrowingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('borrowings:cancel-expired')]
#[Description('Batalkan pengajuan peminjaman disetujui yang tidak diambil sampai batas waktu 1 hari sekolah dan kembalikan stok buku')]
class CancelExpiredBorrowingsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BorrowingService $borrowingService): int
    {
        $this->info('Memeriksa pengajuan peminjaman yang kedaluwarsa...');

        $cancelledCount = $borrowingService->expireUncollectedBorrowings();

        $this->info("Berhasil membatalkan {$cancelledCount} pengajuan yang tidak diambil dan mengembalikan stok bukunya.");

        return self::SUCCESS;
    }
}

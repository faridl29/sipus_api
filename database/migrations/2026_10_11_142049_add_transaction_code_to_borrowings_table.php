<?php

use App\Models\Borrowing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->string('transaction_code', 50)->nullable()->unique()->after('id');
        });

        // Backfill existing records if any
        $borrowings = Borrowing::whereNull('transaction_code')->get();
        foreach ($borrowings as $borrowing) {
            $date = $borrowing->created_at ? $borrowing->created_at->format('Ymd') : now()->format('Ymd');
            $borrowing->updateQuietly([
                'transaction_code' => sprintf('TRX-%s-%04d', $date, $borrowing->id),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropColumn('transaction_code');
        });
    }
};

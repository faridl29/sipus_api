<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Specific mapping for known sample books
        $mappings = [
            '978-979-3062-79-2' => 'Rak A-01', // Laskar Pelangi
            '978-979-97312-3-4' => 'Rak A-02', // Bumi Manusia
            '978-979-22-4861-6' => 'Rak A-03', // Negeri 5 Menara
            '978-602-412-518-9' => 'Rak B-01', // Filosofi Teras
            '978-602-06-3317-6' => 'Rak B-02', // Atomic Habits
            '978-000-0000-00-0' => 'Rak C-01', // Buku Langka Habis Stok
        ];

        foreach ($mappings as $isbn => $rack) {
            DB::table('books')
                ->where('isbn', $isbn)
                ->where(function ($query) {
                    $query->whereNull('rack_location')->orWhere('rack_location', '');
                })
                ->update(['rack_location' => $rack]);
        }

        // For any other books that still have null/empty rack_location, assign a deterministic rack based on ID
        $emptyBooks = DB::table('books')
            ->whereNull('rack_location')
            ->orWhere('rack_location', '')
            ->select('id')
            ->get();

        foreach ($emptyBooks as $book) {
            $rackLetter = chr(65 + (($book->id - 1) % 4)); // A, B, C, D
            $shelfNumber = str_pad((string) ((($book->id - 1) % 5) + 1), 2, '0', STR_PAD_LEFT);
            DB::table('books')
                ->where('id', $book->id)
                ->update(['rack_location' => "Rak {$rackLetter}-{$shelfNumber}"]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op to preserve rack_location data
    }
};

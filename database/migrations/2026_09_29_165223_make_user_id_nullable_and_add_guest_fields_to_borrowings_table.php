<?php

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
            $table->foreignId('user_id')->nullable()->change();
            $table->string('borrower_name')->nullable()->after('user_id');
            $table->string('borrower_phone')->nullable()->after('borrower_name');
            $table->string('borrower_type', 30)->default('student')->after('borrower_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropColumn(['borrower_name', 'borrower_phone', 'borrower_type']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};

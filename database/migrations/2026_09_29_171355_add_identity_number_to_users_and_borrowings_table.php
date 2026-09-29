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
        Schema::table('users', function (Blueprint $table) {
            $table->string('identity_number')->nullable()->unique()->after('email');
        });

        Schema::table('borrowings', function (Blueprint $table) {
            $table->string('borrower_identity_number')->nullable()->after('borrower_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropColumn('borrower_identity_number');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('identity_number');
        });
    }
};

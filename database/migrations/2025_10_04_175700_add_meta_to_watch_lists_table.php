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
        Schema::table('watch_lists', function (Blueprint $table) {
            // SQLite에서는 json이 TEXT로 매핑됩니다. nullable이면 바로 추가 가능.
            $table->json('meta')->nullable()->after('enabled'); // after는 SQLite에선 무시되어도 무방
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('watch_lists', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};

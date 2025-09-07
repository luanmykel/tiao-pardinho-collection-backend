<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            if (! Schema::hasColumn('suggestions', 'song_id')) {
                $table->foreignId('song_id')
                    ->nullable()
                    ->constrained('songs')
                    ->nullOnDelete()
                    ->index();
            }
            if (! Schema::hasColumn('suggestions', 'removed_at')) {
                $table->timestamp('removed_at')->nullable()->index();
            }
            if (! Schema::hasColumn('suggestions', 'removed_reason')) {
                $table->string('removed_reason', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            if (Schema::hasColumn('suggestions', 'song_id')) {
                $table->dropConstrainedForeignId('song_id');
            }
            if (Schema::hasColumn('suggestions', 'removed_at')) {
                $table->dropColumn('removed_at');
            }
            if (Schema::hasColumn('suggestions', 'removed_reason')) {
                $table->dropColumn('removed_reason');
            }
        });
    }
};

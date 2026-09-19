<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixture_broadcasts', function (Blueprint $table): void {
            $table->string('review_status')->default('pending')->after('needs_review');
            $table->string('publication_status')->default('draft')->after('review_status');
            $table->index(
                ['review_status', 'publication_status'],
                'fixture_broadcasts_review_publication_idx'
            );
        });

        DB::table('fixture_broadcasts')
            ->where('needs_review', false)
            ->update([
                'review_status' => 'approved',
                'publication_status' => 'published',
            ]);
    }

    public function down(): void
    {
        Schema::table('fixture_broadcasts', function (Blueprint $table): void {
            $table->dropIndex('fixture_broadcasts_review_publication_idx');
            $table->dropColumn(['review_status', 'publication_status']);
        });
    }
};

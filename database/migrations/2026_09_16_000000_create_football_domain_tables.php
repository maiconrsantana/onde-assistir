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
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('external_id');
            $table->string('name');
            $table->string('slug');
            $table->string('country_code', 2);
            $table->string('season_name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['provider', 'external_id'], 'competitions_provider_ext_unique');
            $table->unique('slug');
            $table->index(['active', 'country_code']);
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('external_id');
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('slug');
            $table->string('logo_url')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id'], 'teams_provider_ext_unique');
            $table->unique('slug');
        });

        Schema::create('football_fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('external_id');
            $table->foreignId('home_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('round')->nullable();
            $table->timestamp('starts_at');
            $table->string('status');
            $table->string('venue')->nullable();
            $table->string('city')->nullable();
            $table->string('source_url')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('resolution_status')->default('pending');
            $table->string('review_status')->default('pending');
            $table->string('publication_status')->default('draft');
            $table->string('resolution_hash')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('resolution_invalidated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id'], 'fixtures_provider_ext_unique');
            $table->index(['competition_id', 'starts_at'], 'fixtures_competition_starts_idx');
            $table->index(['home_team_id', 'starts_at'], 'fixtures_home_starts_idx');
            $table->index(['away_team_id', 'starts_at'], 'fixtures_away_starts_idx');
            $table->index('status');
            $table->index(['resolution_status', 'review_status', 'publication_status'], 'fixture_resolution_review_publication_idx');
            $table->index('resolution_hash');
        });

        Schema::create('broadcasters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('other');
            $table->timestamps();

            $table->index('type');
        });

        Schema::create('fixture_provider_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('football_fixture_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('external_event_id');
            $table->decimal('match_score', 5, 4)->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_event_id'], 'fixture_mapping_provider_event_unique');
            $table->unique(['football_fixture_id', 'provider', 'external_event_id'], 'fixture_provider_mapping_unique');
            $table->index(['football_fixture_id', 'provider'], 'fixture_mapping_fixture_provider_idx');
        });

        Schema::create('broadcast_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('football_fixture_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('external_event_id')->nullable();
            $table->json('channels')->nullable();
            $table->json('evidence')->nullable();
            $table->text('evidence_summary')->nullable();
            $table->json('raw_response')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->unsignedSmallInteger('web_search_calls')->nullable();
            $table->decimal('provider_confidence', 5, 4)->nullable();
            $table->decimal('calculated_confidence', 5, 4)->nullable();
            $table->string('result_status');
            $table->boolean('selected')->default(false);
            $table->string('query_hash')->nullable();
            $table->timestamp('queried_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'query_hash'], 'broadcast_sources_provider_query_unique');
            $table->index(['football_fixture_id', 'provider', 'result_status'], 'broadcast_sources_fixture_provider_result_idx');
            $table->index(['selected', 'validated_at'], 'broadcast_sources_selected_validated_idx');
        });

        Schema::create('fixture_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('football_fixture_id')->constrained()->cascadeOnDelete();
            $table->foreignId('broadcaster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('broadcast_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('access_type')->default('unknown');
            $table->string('country_code', 2)->default('BR');
            $table->string('source_type');
            $table->string('source_url')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['football_fixture_id', 'broadcaster_id', 'country_code'], 'fixture_broadcast_unique');
            $table->index(['source_type', 'needs_review'], 'fixture_broadcasts_source_review_idx');
            $table->index('verified_at');
        });

        Schema::create('publication_settings', function (Blueprint $table) {
            $table->id();
            $table->string('publication_mode')->default('manual');
            $table->timestamps();
        });

        Schema::create('round_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('season_name');
            $table->string('round');
            $table->string('publication_status')->default('draft');
            $table->string('publication_mode')->default('manual');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('unpublished_at')->nullable();
            $table->json('audit_payload')->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'season_name', 'round'], 'round_publications_competition_season_round_unique');
            $table->index(['publication_status', 'publication_mode'], 'round_publications_status_mode_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_publications');
        Schema::dropIfExists('publication_settings');
        Schema::dropIfExists('fixture_broadcasts');
        Schema::dropIfExists('broadcast_sources');
        Schema::dropIfExists('fixture_provider_mappings');
        Schema::dropIfExists('broadcasters');
        Schema::dropIfExists('football_fixtures');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('competitions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram Reels-Autoposter + Node-scheduler data layer (IG-N0).
 *
 * Node owns ALL Instagram scheduling/posting. This migration adds:
 *   1. claim columns on the existing scheduled-job tables so the Node
 *      scheduler can atomically claim a row before firing (double-send guard).
 *   2. instagram_reposter_settings — per target-account reposter config.
 *      NO login credentials: scraping is done by the yt-dlp binary (no IG
 *      login), posting uses the account's existing official Graph token.
 *   3. instagram_repost_items — the scrape→post queue (dedup by source_id),
 *      mirrors Reels-AutoPilot's `reels` table.
 *
 * Sensitive columns (youtube_api_key) are encrypted-at-rest → TEXT.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Atomic-claim columns on the existing job tables.
        foreach (['instagram_scheduled_posts', 'instagram_broadcasts'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $t) use ($tbl) {
                    if (!Schema::hasColumn($tbl, 'claimed_at'))  $t->timestamp('claimed_at')->nullable()->after('status');
                    if (!Schema::hasColumn($tbl, 'node_job_id')) $t->string('node_job_id', 64)->nullable()->after('claimed_at');
                });
            }
        }

        // 2. Per-account reposter config.
        if (!Schema::hasTable('instagram_reposter_settings')) {
            Schema::create('instagram_reposter_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id')->index();
                $t->unsignedBigInteger('instagram_account_id'); // TARGET account we post to
                $t->boolean('enabled')->default(false);

                // Sources (mirror Reels-AutoPilot config). Scraped via yt-dlp — NO login.
                $t->json('source_ig_accounts')->nullable();    // ["natgeo", ...] public handles/urls
                $t->boolean('youtube_enabled')->default(false);
                $t->text('youtube_api_key')->nullable();        // encrypted
                $t->json('source_yt_channels')->nullable();     // ["https://youtube.com/@x"]

                // Behaviour.
                $t->unsignedSmallInteger('fetch_limit')->default(10);
                $t->unsignedSmallInteger('scraper_interval_min')->default(120);
                $t->unsignedSmallInteger('posting_interval_min')->default(30);
                $t->unsignedSmallInteger('daily_cap')->default(10);   // stay well under Graph ~50/24h
                $t->unsignedSmallInteger('remove_after_min')->default(120);
                $t->boolean('post_to_story')->default(false);
                $t->text('hashtags')->nullable();

                $t->timestamp('last_scrape_at')->nullable();
                $t->timestamp('last_post_at')->nullable();
                $t->timestamps();

                $t->unique('instagram_account_id');
            });
        }

        // 3. Scrape→post queue (dedup by source_id per target account).
        if (!Schema::hasTable('instagram_repost_items')) {
            Schema::create('instagram_repost_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id')->index();
                $t->unsignedBigInteger('instagram_account_id');
                $t->string('source', 16)->default('ig');       // ig | youtube
                $t->string('source_id', 191);                  // dedup key (IG shortcode / YT video id)
                $t->string('source_handle', 191)->nullable();
                $t->text('caption')->nullable();
                $t->string('video_path', 1024)->nullable();    // local public-disk path
                $t->string('public_url', 1024)->nullable();    // HTTPS url Graph fetches
                $t->string('status', 16)->default('queued');   // queued | posted | failed
                $t->timestamp('claimed_at')->nullable();
                $t->string('media_id', 64)->nullable();
                $t->string('last_error', 500)->nullable();
                $t->timestamp('posted_at')->nullable();
                $t->json('meta')->nullable();
                $t->timestamps();

                $t->unique(['instagram_account_id', 'source_id']);
                $t->index(['instagram_account_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_repost_items');
        Schema::dropIfExists('instagram_reposter_settings');
        foreach (['instagram_scheduled_posts', 'instagram_broadcasts'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $t) use ($tbl) {
                    foreach (['claimed_at', 'node_job_id'] as $c) {
                        if (Schema::hasColumn($tbl, $c)) $t->dropColumn($c);
                    }
                });
            }
        }
    }
};

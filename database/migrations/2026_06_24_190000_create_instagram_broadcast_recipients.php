<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-recipient ledger for Instagram bulk DMs — one row per snapshotted IGSID
 * so users see exactly who was sent / failed / skipped, with the error reason.
 * Also adds skipped_window + claimed_at to instagram_broadcasts if missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('instagram_broadcast_recipients')) {
            Schema::create('instagram_broadcast_recipients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('broadcast_id')->index();
                $table->string('igsid', 64);
                $table->string('status', 16)->default('pending'); // pending|sent|failed|skipped_window
                $table->string('mid', 191)->nullable();
                $table->string('error', 500)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['broadcast_id', 'status']);
            });
        }

        Schema::table('instagram_broadcasts', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_broadcasts', 'skipped_window')) {
                $table->unsignedInteger('skipped_window')->default(0)->after('failed');
            }
            if (!Schema::hasColumn('instagram_broadcasts', 'claimed_at')) {
                $table->timestamp('claimed_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_broadcast_recipients');
    }
};

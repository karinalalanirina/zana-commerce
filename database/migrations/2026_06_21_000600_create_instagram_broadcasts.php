<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk DM jobs. Drained by `instagram:run-broadcasts` (CLI — no web
 * timeout). Recipients are resolved at run time to whoever is inside the
 * Meta 24-hour messaging window; capped at the 200-DM/hour limit per run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('instagram_account_id');
            $table->text('body');
            $table->json('recipients')->nullable();                  // snapshot of in-window IGSIDs at creation
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('cursor')->default(0);           // how many drained so far
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->string('status', 16)->default('pending');        // pending | running | done
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_broadcasts');
    }
};

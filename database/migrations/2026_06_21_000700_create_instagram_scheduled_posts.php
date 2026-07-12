<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Publish later" feed posts. Instagram has no native post scheduler for
 * most apps, so we hold the post and publish it at the due time via the
 * same no-cron sweep that drains bulk DMs. Carries the optional comment→DM
 * rule to arm on the post once it exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('instagram_account_id');
            $table->string('image_url', 1024);
            $table->text('caption')->nullable();
            $table->timestamp('scheduled_at')->index();
            $table->string('status', 16)->default('pending');   // pending | published | failed
            $table->string('media_id', 64)->nullable();          // set once published
            $table->string('last_error', 500)->nullable();

            // Optional comment→DM automation to arm on the new post.
            $table->string('auto_keyword', 500)->nullable();
            $table->string('auto_public_reply', 500)->nullable();
            $table->text('auto_dm')->nullable();
            $table->unsignedBigInteger('auto_flow_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_scheduled_posts');
    }
};

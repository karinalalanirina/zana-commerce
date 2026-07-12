<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram automation rules — keyword→DM, comment→DM, story-reply, welcome.
 * A rule can answer with a fixed message OR launch a flow. The webhook
 * controller matches inbound DMs/comments against these.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_automations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('instagram_account_id')->index();

            // dm_keyword | comment_to_dm | story_reply | welcome
            $table->string('type', 24)->default('dm_keyword');
            $table->string('name', 191)->nullable();

            // Trigger
            $table->string('trigger_keyword', 500)->nullable();   // comma-separated keywords
            $table->string('match_mode', 16)->default('contains'); // contains | exact | any
            $table->string('post_id', 64)->nullable();             // comment automations: limit to a post (null = all)

            // Response
            $table->string('public_reply', 500)->nullable();       // comment automations: public reply text
            $table->text('dm_message')->nullable();                // the DM body
            $table->unsignedBigInteger('flow_id')->nullable();     // optional: launch a flow instead

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('fired_count')->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_automations');
    }
};

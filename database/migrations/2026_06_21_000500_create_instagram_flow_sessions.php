<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pauses an in-progress Instagram flow at a Quick-reply / Button node so the
 * NEXT inbound DM (the tap) resumes the walk from the right branch. Keyed by
 * (account, igsid); one live session per conversation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_flow_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('instagram_account_id');
            $table->string('igsid', 64);
            $table->unsignedBigInteger('flow_id');
            $table->string('node_id', 64);            // the node we paused at (a quick/button node)
            $table->json('vars')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['instagram_account_id', 'igsid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_flow_sessions');
    }
};

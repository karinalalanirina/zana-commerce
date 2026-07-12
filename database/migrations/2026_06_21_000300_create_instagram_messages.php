<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram DM transcript — every inbound + outbound message, so the AI
 * agent has conversation memory and the inbox can render threads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('instagram_account_id')->index();
            $table->string('igsid', 64)->index();           // the customer (Instagram-scoped id)
            $table->string('direction', 4);                  // in | out
            $table->text('body')->nullable();
            $table->string('mid', 191)->nullable();
            $table->string('source', 24)->nullable();        // keyword | ai | manual | comment
            $table->timestamps();

            $table->index(['instagram_account_id', 'igsid', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_messages');
    }
};

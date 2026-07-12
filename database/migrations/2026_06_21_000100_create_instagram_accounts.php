<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram automation — foundation. One row per connected Instagram
 * Professional/Creator account (a workspace can connect several). The
 * access token is stored ENCRYPTED via the model cast. Mirrors how WABA
 * stores its per-workspace provider config.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();

            // Instagram-scoped identifiers from the Graph API.
            $table->string('ig_user_id', 64)->index();      // the IG account id
            $table->string('username', 191)->nullable();
            $table->string('name', 191)->nullable();
            $table->string('profile_pic_url', 1024)->nullable();
            $table->string('page_id', 64)->nullable();       // linked FB Page (FB-Login path)
            $table->string('login_type', 24)->default('facebook'); // facebook | instagram

            // Long-lived token (~60d) — encrypted at rest via the model cast.
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();

            $table->string('status', 24)->default('connected'); // connected | expired | error
            $table->unsignedBigInteger('followers_count')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'ig_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_accounts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram Ads support on top of the existing CTWA pipeline.
 *
 *   - ad_type             how the ad is built: ctwa (Click-to-WhatsApp,
 *                         the default + existing behaviour) | link
 *                         (standard traffic/sales/awareness ad to a
 *                         website) | ig_direct (Click-to-Instagram-Direct
 *                         DM ad).
 *   - publisher_platforms which Meta platforms the ad set targets
 *                         (["facebook"], ["instagram"], ["facebook",
 *                         "instagram"], …). NULL = Advantage+ automatic
 *                         placements (current behaviour, incl. Instagram).
 *   - instagram_positions IG surfaces (["stream","story","reels"]); only
 *                         applies when "instagram" is in publisher_platforms.
 *   - instagram_user_id   the IG professional account id used as the ad's
 *                         Instagram identity (object_story_spec.instagram_user_id);
 *                         resolved from the workspace's instagram_accounts row
 *                         (or a Page-Backed Instagram Account) at sync time.
 *
 * Existing rows default to ad_type='ctwa' so CTWA campaigns are byte-identical.
 * Idempotent + guarded for re-runs / older MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meta_campaigns')) {
            return;
        }

        Schema::table('meta_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('meta_campaigns', 'ad_type')) {
                $table->string('ad_type', 24)->default('ctwa')->after('type');
            }
            if (! Schema::hasColumn('meta_campaigns', 'publisher_platforms')) {
                $table->json('publisher_platforms')->nullable()->after('targeting');
            }
            if (! Schema::hasColumn('meta_campaigns', 'instagram_positions')) {
                $table->json('instagram_positions')->nullable()->after('publisher_platforms');
            }
            if (! Schema::hasColumn('meta_campaigns', 'instagram_user_id')) {
                $table->string('instagram_user_id', 64)->nullable()->after('instagram_positions');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meta_campaigns')) {
            return;
        }
        Schema::table('meta_campaigns', function (Blueprint $table) {
            foreach (['ad_type', 'publisher_platforms', 'instagram_positions', 'instagram_user_id'] as $col) {
                if (Schema::hasColumn('meta_campaigns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

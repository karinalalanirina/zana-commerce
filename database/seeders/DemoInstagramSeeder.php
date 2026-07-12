<?php

namespace Database\Seeders;

use App\Models\InstagramAccount;
use App\Models\InstagramAutomation;
use App\Models\InstagramMessage;
use App\Models\InstagramScheduledPost;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo Instagram data so the whole Instaflow UI can be reviewed without a real
 * Meta connection. Idempotent (updateOrCreate). Target workspace resolves to:
 *   env('DEMO_WS')  →  first user's current_workspace_id  →  first workspace.
 *
 *   php artisan db:seed --class=DemoInstagramSeeder
 *   DEMO_WS=13 php artisan db:seed --class=DemoInstagramSeeder
 */
class DemoInstagramSeeder extends Seeder
{
    public function run(): void
    {
        // DEMO_WS=all → every workspace (handy on a shared dev box).
        if (strtolower((string) env('DEMO_WS')) === 'all') {
            foreach (Workspace::pluck('id') as $id) {
                $this->seedWorkspace((int) $id);
            }
            return;
        }

        $wsId = (int) (env('DEMO_WS')
            ?: optional(User::whereNotNull('current_workspace_id')->first())->current_workspace_id
            ?: optional(Workspace::first())->id);

        if (!$wsId) {
            $this->command?->warn('No workspace found — create one first.');
            return;
        }
        $this->seedWorkspace($wsId);
    }

    private function seedWorkspace(int $wsId): void
    {
        $userId = optional(Workspace::find($wsId))->owner_id
            ?? optional(User::first())->id;

        $this->command?->info("Seeding demo Instagram data into workspace #{$wsId}…");

        // ── Accounts ──
        $accounts = [
            ['ig_user_id' => 'demo_bloomly',  'username' => 'bloomly.flowers', 'name' => 'Bloomly Flowers', 'followers_count' => 48200, 'status' => 'connected'],
            ['ig_user_id' => 'demo_roastery', 'username' => 'urban.roastery',  'name' => 'Urban Roastery',  'followers_count' => 22800, 'status' => 'connected'],
            ['ig_user_id' => 'demo_luxe',     'username' => 'luxe.skincare',   'name' => 'Luxe Skincare',   'followers_count' => 61400, 'status' => 'disconnected'],
        ];
        $accModels = [];
        foreach ($accounts as $a) {
            $accModels[$a['ig_user_id']] = InstagramAccount::updateOrCreate(
                ['workspace_id' => $wsId, 'ig_user_id' => $a['ig_user_id']],
                array_merge($a, [
                    'user_id'          => $userId,
                    'login_type'       => 'business_login',
                    'access_token'     => 'DEMO-TOKEN-' . $a['ig_user_id'],
                    'token_expires_at' => now()->addDays(55),
                    'scopes'           => 'instagram_business_basic,instagram_business_manage_messages',
                ])
            );
        }
        $bloomly = $accModels['demo_bloomly'];

        // ── Automations ──
        $autos = [
            ['type' => 'comment_to_dm', 'name' => 'Pricing keyword reply', 'trigger_keyword' => 'price, cost, how much', 'match_mode' => 'contains', 'dm_message' => 'Hi! Our bouquets start at ₹799 🌹 here is the shop:', 'is_active' => true, 'fired_count' => 842],
            ['type' => 'comment_to_dm', 'name' => 'Comment "LINK" → DM', 'trigger_keyword' => 'LINK', 'match_mode' => 'contains', 'public_reply' => 'Sent you a DM! 💌', 'dm_message' => 'Here is the link you asked for:', 'is_active' => true, 'fired_count' => 1408],
            ['type' => 'dm_keyword', 'name' => 'Story reply → catalog', 'trigger_keyword' => '', 'match_mode' => 'any', 'dm_message' => 'Thanks for replying! Browse our catalog here:', 'is_active' => true, 'fired_count' => 312],
            ['type' => 'comment_to_dm', 'name' => 'Welcome new follower', 'trigger_keyword' => 'hi, hello', 'match_mode' => 'contains', 'dm_message' => 'Welcome! 👋 Use code HELLO10 for 10% off.', 'is_active' => false, 'fired_count' => 0],
        ];
        foreach ($autos as $a) {
            InstagramAutomation::updateOrCreate(
                ['workspace_id' => $wsId, 'instagram_account_id' => $bloomly->id, 'name' => $a['name']],
                array_merge($a, ['workspace_id' => $wsId, 'instagram_account_id' => $bloomly->id])
            );
        }

        // ── Scheduled posts (calendar) ──
        $posts = [
            ['media_type' => 'image',    'caption' => "🌹 Mother's Day Collection is LIVE! Comment LINK for the shop 💌", 'image_url' => 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=800', 'scheduled_at' => now()->addDay()->setTime(10, 0), 'status' => 'pending'],
            ['media_type' => 'reels',    'caption' => 'Behind the bouquet — a 15s reel 🎬', 'video_url' => 'https://example.com/reel.mp4', 'scheduled_at' => now()->addDays(2)->setTime(18, 30), 'status' => 'pending'],
            ['media_type' => 'story',    'caption' => 'Peony restock — swipe up!', 'image_url' => 'https://images.unsplash.com/photo-1505944270255-72b8c68c6a70?w=800', 'scheduled_at' => now()->addDays(3)->setTime(9, 0), 'status' => 'pending'],
            ['media_type' => 'carousel', 'caption' => 'Top 5 spring arrangements 🌷', 'media_urls' => ['https://images.unsplash.com/photo-1487530811176-3780de880c2d?w=800', 'https://images.unsplash.com/photo-1469259943454-aa100abba749?w=800'], 'scheduled_at' => now()->addDays(5)->setTime(12, 0), 'status' => 'pending'],
            ['media_type' => 'image',    'caption' => 'Sold out in 2 hours 💐', 'image_url' => 'https://images.unsplash.com/photo-1457089328109-e5d9bd499191?w=800', 'scheduled_at' => now()->subDays(2)->setTime(11, 0), 'status' => 'published', 'media_id' => 'demo_media_1'],
        ];
        foreach ($posts as $i => $p) {
            InstagramScheduledPost::updateOrCreate(
                ['workspace_id' => $wsId, 'instagram_account_id' => $bloomly->id, 'caption' => $p['caption']],
                array_merge($p, ['workspace_id' => $wsId, 'instagram_account_id' => $bloomly->id])
            );
        }

        // ── Inbox messages ──
        $threads = [
            ['igsid' => 'demo_maya', 'msgs' => [['in', 'Hi! Do you deliver same day?'], ['out', 'Yes! Same-day across the city before 6pm 🌹'], ['in', 'Amazing, how much for 12 roses?']]],
            ['igsid' => 'demo_jay',  'msgs' => [['in', 'LINK'], ['out', 'Sent you the shop link in DM! 💌']]],
            ['igsid' => 'demo_anish','msgs' => [['in', 'Loved your story 😍'], ['out', 'Thank you! Use HELLO10 for 10% off your first order.']]],
        ];
        foreach ($threads as $t) {
            foreach ($t['msgs'] as $k => [$dir, $body]) {
                InstagramMessage::updateOrCreate(
                    ['workspace_id' => $wsId, 'instagram_account_id' => $bloomly->id, 'igsid' => $t['igsid'], 'mid' => 'demo_' . $t['igsid'] . '_' . $k],
                    ['direction' => $dir, 'body' => $body, 'source' => 'demo']
                );
            }
        }

        $this->command?->info('Demo Instagram data seeded ✓ (3 accounts, 4 automations, 5 posts, 3 inbox threads).');
    }
}

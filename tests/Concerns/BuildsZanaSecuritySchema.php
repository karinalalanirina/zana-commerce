<?php

namespace Tests\Concerns;

use App\Models\PaymentGateway;
use App\Models\TranslationProvider;
use App\Models\User;
use App\Models\WaProviderConfig;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

trait BuildsZanaSecuritySchema
{
    protected function buildZanaSecuritySchema(): void
    {
        foreach ([
            'audit_logs',
            'currencies',
            'inbox_messages',
            'conversations',
            'contacts',
            'notifications',
            'packages',
            'payment_gateways',
            'permissions',
            'translation_providers',
            'model_has_roles',
            'roles',
            'wa_provider_configs',
            'workspace_user',
            'workspaces',
            'users',
            'system_settings',
            'devices',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->unsignedBigInteger('current_workspace_id')->nullable();
            $table->string('referral_code')->nullable();
            $table->integer('wallet_credits')->default(0);
            $table->integer('wallet_currency_minor')->default(0);
            $table->string('wallet_currency_code')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('currency')->nullable();
            $table->json('plan_overrides')->nullable();
            $table->json('enabled_engines')->nullable();
            $table->string('default_engine')->nullable();
            $table->json('notification_prefs')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 10)->unique();
            $table->string('symbol', 20)->nullable();
            $table->unsignedTinyInteger('precision')->default(2);
            $table->decimal('exchange_rate', 16, 6)->default(1.000000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->longText('credentials')->nullable();
            $table->string('mode')->nullable();
            $table->json('extra_config')->nullable();
            $table->json('supported_currencies')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('translation_providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->longText('credentials')->nullable();
            $table->json('extra_config')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('layer');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('result')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->text('notification_title')->nullable();
            $table->text('notification_msg')->nullable();
            $table->string('category')->nullable();
            $table->string('severity')->nullable();
            $table->string('icon')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('verb')->nullable();
            $table->string('action_url')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->nullable();
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type')->default('string');
            $table->longText('value')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('provider');
            $table->string('status')->nullable();
            $table->longText('credentials_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('display_label')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_health_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('name')->nullable();
            $table->string('mobile')->nullable();
            $table->string('mobile_hash')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->nullable();
            $table->string('country_code')->nullable();
            $table->boolean('is_unsubscribed')->default(false);
            $table->json('contact_group')->nullable();
            $table->json('custom_attributes')->nullable();
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('title')->nullable();
            $table->text('preview')->nullable();
            $table->string('status')->nullable();
            $table->string('provider')->nullable();
            $table->string('origin')->nullable();
            $table->string('channel')->nullable();
            $table->string('raw_jid')->nullable();
            $table->string('alt_jid')->nullable();
            $table->string('inbox_status')->nullable();
            $table->unsignedBigInteger('assignee_user_id')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('provider')->nullable();
            $table->string('direction')->nullable();
            $table->string('to_number')->nullable();
            $table->string('from_number')->nullable();
            $table->longText('body')->nullable();
            $table->string('status')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('device_name')->nullable();
            $table->string('country_code')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('status')->nullable();
            $table->boolean('active')->default(false);
            $table->boolean('activate_after_pairing')->default(true);
            $table->timestamps();
        });

        \DB::table('currencies')->insert([
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'precision' => 2,
            'exchange_rate' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function makeWorkspaceUser(string $role = 'owner', bool $admin = false): User
    {
        $user = User::create([
            'name' => $admin ? 'Admin User' : 'Workspace User',
            'email' => ($admin ? 'admin' : 'user') . uniqid() . '@example.test',
            'password' => Hash::make('password'),
            'role' => $admin ? 'admin' : $role,
        ]);

        $workspace = Workspace::create([
            'name' => ($admin ? 'Admin' : 'User') . ' Workspace',
            'owner_user_id' => $user->id,
        ]);

        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        $workspace->forceFill(['owner_user_id' => $user->id])->save();
        $user->workspaces()->attach($workspace->id, ['role' => $role]);

        return $user->fresh();
    }

    protected function makeStripeGateway(string $secret = 'zana-test-secret-never-expose-7d19f3'): PaymentGateway
    {
        $gateway = PaymentGateway::create([
            'slug' => 'stripe',
            'name' => 'Stripe',
            'description' => 'Cards',
            'is_active' => true,
            'mode' => 'live',
            'supported_currencies' => ['USD', 'KES'],
            'sort_order' => 1,
        ]);

        $gateway->setEncryptedCredentials([
            'publishable_key' => 'pk_test_zana_public',
            'secret_key' => $secret,
            'webhook_secret' => 'zana-webhook-secret-hidden',
        ]);
        $gateway->save();

        return $gateway->fresh();
    }

    protected function makeLibreTranslateProvider(string $secret = 'zana-translation-secret-hidden'): TranslationProvider
    {
        $provider = TranslationProvider::create([
            'slug' => 'libretranslate',
            'name' => 'LibreTranslate',
            'description' => 'Self-hosted',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $provider->setEncryptedCredentials([
            'endpoint' => 'https://translate.zana.test',
            'api_key' => $secret,
        ]);
        $provider->save();

        return $provider->fresh();
    }

    protected function makeWorkspaceContact(User $user, string $name, string $mobile): \App\Models\Contact
    {
        return \App\Models\Contact::create([
            'user_id' => $user->id,
            'workspace_id' => $user->current_workspace_id,
            'name' => $name,
            'mobile' => $mobile,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
            'status' => 'active',
        ]);
    }

    protected function makeWorkspaceConversation(User $user, array $overrides = []): \App\Models\Conversation
    {
        return \App\Models\Conversation::create(array_merge([
            'user_id' => $user->id,
            'workspace_id' => $user->current_workspace_id,
            'title' => 'Customer thread',
            'preview' => 'Latest message',
            'status' => 'open',
            'provider' => 'waba',
            'origin' => 'chat',
            'channel' => 'whatsapp',
            'raw_jid' => '254700000001@s.whatsapp.net',
            'alt_jid' => '254700000001@c.us',
            'inbox_status' => 'open',
            'assignee_user_id' => $user->id,
            'last_message_at' => Carbon::parse('2026-07-12 09:00:00'),
        ], $overrides));
    }

    protected function makeInboxMessage(\App\Models\Conversation $conversation, array $overrides = []): \App\Models\InboxMessage
    {
        return \App\Models\InboxMessage::create(array_merge([
            'conversation_id' => $conversation->id,
            'user_id' => $conversation->user_id,
            'provider' => $conversation->provider ?? 'waba',
            'direction' => 'in',
            'to_number' => '254700000010',
            'from_number' => '254700000001',
            'body' => 'Hello from customer',
            'status' => 'read',
            'sent_at' => Carbon::parse('2026-07-12 09:00:00'),
        ], $overrides));
    }

    protected function makeWabaConfig(User $user, array $overrides = []): WaProviderConfig
    {
        $config = WaProviderConfig::create(array_merge([
            'workspace_id' => $user->current_workspace_id,
            'provider' => 'waba',
            'status' => WaProviderConfig::STATUS_CONNECTED,
            'phone_number' => '+254700000001',
            'display_label' => 'Zana WABA',
            'meta_json' => [
                'waba_id' => 'waba-' . $user->current_workspace_id,
                'phone_number_id' => 'pnid-' . $user->current_workspace_id,
            ],
            'is_primary' => true,
            'connected_at' => now(),
            'last_health_at' => now(),
        ], $overrides));

        $config->setCreds([
            'access_token' => 'zana-access-token-' . $user->current_workspace_id,
            'app_secret' => 'zana-app-secret-' . $user->current_workspace_id,
        ]);
        $config->save();

        return $config->fresh();
    }
}

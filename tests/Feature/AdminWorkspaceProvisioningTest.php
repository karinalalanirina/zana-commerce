<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\WorkspacesController;
use App\Http\Controllers\AuthPagesController;
use App\Models\User;
use App\Models\Workspace;
use App\Services\RecaptchaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsZanaSecuritySchema;
use Tests\TestCase;

class AdminWorkspaceProvisioningTest extends TestCase
{
    use BuildsZanaSecuritySchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildZanaSecuritySchema();
        $this->extendAdminProvisioningSchema();
        $this->app->instance(RecaptchaService::class, new class {
            public function verify(...$args): bool
            {
                return true;
            }
        });
    }

    public function test_admin_created_owner_is_attached_to_workspace_and_skips_self_serve_onboarding(): void
    {
        $actor = $this->makeWorkspaceUser(admin: true);
        $actor->forceFill(['role' => 'super_admin'])->save();

        $workspace = Workspace::create([
            'name' => 'Zuri Beauty',
            'owner_user_id' => $actor->id,
            'status' => true,
        ]);

        $response = app(UsersController::class)->store($this->adminRequest($actor, [
            'name' => 'Zuri Owner',
            'email' => 'zuri.owner@example.com',
            'mobile' => '',
            'role' => 'owner',
            'workspace_id' => $workspace->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'active' => '1',
        ]));

        $this->assertSame(302, $response->getStatusCode());

        $owner = User::where('email', 'zuri.owner@example.com')->firstOrFail();
        $this->assertSame($workspace->id, $owner->current_workspace_id);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $this->assertNotNull(DB::table('workspace_user')->where('workspace_id', $workspace->id)->where('user_id', $owner->id)->value('joined_at'));

        $login = app(AuthPagesController::class)->login($this->loginRequest([
            'email' => 'zuri.owner@example.com',
            'password' => 'password123',
        ]));

        $this->assertSame(302, $login->getStatusCode());
        $this->assertSame(route('user.dashboard'), $login->headers->get('Location'));
    }

    public function test_admin_created_agent_is_attached_to_workspace_and_lands_in_team_inbox(): void
    {
        $actor = $this->makeWorkspaceUser(admin: true);
        $workspace = Workspace::create([
            'name' => 'Nairobi Fashion',
            'owner_user_id' => $actor->id,
            'status' => true,
        ]);

        $response = app(UsersController::class)->store($this->adminRequest($actor, [
            'name' => 'Zuri Agent',
            'email' => 'zuri.agent@example.com',
            'mobile' => '',
            'role' => 'agent',
            'workspace_id' => $workspace->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'active' => '1',
        ]));

        $this->assertSame(302, $response->getStatusCode());

        $agent = User::where('email', 'zuri.agent@example.com')->firstOrFail();
        $this->assertSame($workspace->id, $agent->current_workspace_id);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $agent->id,
            'role' => 'agent',
        ]);

        $login = app(AuthPagesController::class)->login($this->loginRequest([
            'email' => 'zuri.agent@example.com',
            'password' => 'password123',
        ]));

        $this->assertSame(302, $login->getStatusCode());
        $this->assertSame(url('/team-inbox'), $login->headers->get('Location'));
    }

    public function test_admin_created_workspace_attaches_owner_membership(): void
    {
        $actor = $this->makeWorkspaceUser(admin: true);
        $owner = User::create([
            'name' => 'Assigned Owner',
            'email' => 'assigned.owner@example.com',
            'password' => 'password123',
            'role' => 'owner',
        ]);

        $response = app(WorkspacesController::class)->store($this->adminRequest($actor, [
            'name' => 'Kenya Decor',
            'slug' => '',
            'owner_mode' => 'existing',
            'owner_user_id' => $owner->id,
            'timezone' => 'Africa/Nairobi',
            'billing_cycle' => 'monthly',
        ]));

        $this->assertSame(302, $response->getStatusCode());

        $workspace = Workspace::where('name', 'Kenya Decor')->firstOrFail();
        $owner->refresh();

        $this->assertSame($workspace->id, $owner->current_workspace_id);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }

    public function test_self_serve_user_without_workspace_still_goes_to_workspace_step(): void
    {
        User::create([
            'name' => 'Fresh User',
            'email' => 'fresh@example.com',
            'password' => 'password123',
            'role' => 'user',
        ]);

        $login = app(AuthPagesController::class)->login($this->loginRequest([
            'email' => 'fresh@example.com',
            'password' => 'password123',
        ]));

        $this->assertSame(302, $login->getStatusCode());
        $this->assertSame(route('register.workspace'), $login->headers->get('Location'));
    }

    private function adminRequest(User $actor, array $payload, string $method = 'POST'): Request
    {
        $request = Request::create('/admin/test', $method, $payload);
        $request->setUserResolver(fn () => $actor);

        return $request;
    }

    private function loginRequest(array $payload): Request
    {
        $request = Request::create('/login', 'POST', $payload);
        $request->setLaravelSession($this->app['session.store']);

        return $request;
    }

    private function extendAdminProvisioningSchema(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile')->nullable();
            $table->string('country_code')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('site_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zip')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('force_password_change')->default(false);
            $table->timestamp('welcome_email_sent_at')->nullable();
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('custom_domain')->nullable();
            $table->unsignedBigInteger('plan')->nullable();
            $table->string('industry')->nullable();
            $table->string('country', 8)->nullable();
            $table->string('timezone')->nullable();
            $table->string('billing_cycle')->nullable();
            $table->integer('cap_monthly_messages')->nullable();
            $table->integer('cap_daily_messages')->nullable();
            $table->integer('cap_devices')->nullable();
            $table->integer('cap_users')->nullable();
            $table->boolean('skip_onboarding_email')->default(false);
            $table->boolean('bill_to_platform_credit')->default(false);
            $table->boolean('pre_seed_sample_data')->default(false);
            $table->text('admin_note')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamp('last_active_at')->nullable();
        });
    }
}

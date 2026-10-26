<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\OtpCode;
use App\Models\User;
use App\Notifications\AccountStatusChanged;
use App\Notifications\NewUserRegistered;
use App\Notifications\OtpCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_pending_account_and_notifies_admins(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->post('/register', [
            'name' => 'New Person', 'email' => 'new@example.com', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'terms' => 1,
        ])->assertRedirect(route('account.pending'));

        $user = User::where('email', 'new@example.com')->first();
        $this->assertSame('pending', $user->status);
        $this->assertDatabaseHas('user_approval_requests', ['user_id' => $user->id, 'status' => 'pending']);
        Notification::assertSentTo($admin, NewUserRegistered::class);
    }

    public function test_pending_user_cannot_reach_dashboard(): void
    {
        $user = User::factory()->pending()->create();
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('account.pending'));
        $this->actingAs($user)->getJson('/dashboard/data')->assertForbidden();
    }

    public function test_admin_approves_user_and_user_is_notified(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $user = User::factory()->pending()->create();

        $this->actingAs($admin)->postJson(route('users.status', $user), ['status' => 'approved', 'roles' => ['Sales Representative']])->assertOk();

        $user->refresh();
        $this->assertSame('approved', $user->status);
        $this->assertTrue($user->hasRole('Sales Representative'));
        Notification::assertSentTo($user, AccountStatusChanged::class);
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['status' => 'suspended', 'password' => 'Secret123!']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id, 'successful' => false, 'reason' => 'account_suspended']);
    }

    public function test_successful_login_records_history(): void
    {
        $user = $this->userWithRole('Viewer', ['password' => 'Secret123!']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123!'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, LoginHistory::where('user_id', $user->id)->where('successful', true)->count());
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertStatus(429);
    }

    public function test_password_reset_does_not_reveal_accounts(): void
    {
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('success');
    }

    public function test_email_otp_two_factor_challenge(): void
    {
        Notification::fake();
        $user = $this->userWithRole('Viewer', ['password' => 'Secret123!']);
        $user->forceFill(['two_factor_method' => 'email', 'two_factor_confirmed_at' => now()])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123!'])->assertRedirect(route('two-factor.challenge'));
        $this->get('/dashboard')->assertRedirect(route('two-factor.challenge'));

        $code = null;
        Notification::assertSentTo($user, OtpCodeNotification::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });

        $this->post('/two-factor/challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/two-factor/challenge', ['code' => $code])->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk();
        $this->assertNotNull(OtpCode::where('user_id', $user->id)->first()->consumed_at);
    }

    public function test_email_verification_enforced_when_enabled(): void
    {
        settings()->set('verification.email', true);
        $user = $this->userWithRole('Viewer', ['email_verified_at' => null]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
        settings()->set('verification.email', false);
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_create_admin_command_never_uses_default_password(): void
    {
        $this->artisan('autoshop:create-admin', ['--name' => 'Root', '--email' => 'root@example.com', '--generate-password' => true])->assertSuccessful();
        $user = User::where('email', 'root@example.com')->first();
        $this->assertTrue($user->hasRole('Super Administrator'));
        $this->assertSame('approved', $user->status);
    }
}

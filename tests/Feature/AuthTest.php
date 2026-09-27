<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Bạn đọc',
            'email' => 'reader@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ], $overrides);
    }

    public function test_registration_creates_reader_with_hashed_password_and_usable_token(): void
    {
        $response = $this->postJson('/api/register', $this->registration(['email' => ' Reader@Example.com ']));
        $response->assertCreated()->assertJsonPath('user.role', 'reader')->assertJsonPath('user.email', 'reader@example.com')
            ->assertJsonMissingPath('user.password');
        $this->assertTrue(Hash::check('secret-password', User::firstOrFail()->password));
        $token = $response->json('token');
        $this->assertNotSame($token, PersonalAccessToken::firstOrFail()->token);
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('user.id', $response->json('user.id'));
    }

    public function test_registration_cannot_assign_an_admin_role(): void
    {
        $this->postJson('/api/register', $this->registration(['role' => 'admin']))->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_registration_validates_confirmation_and_duplicate_email(): void
    {
        $this->postJson('/api/register', $this->registration(['password_confirmation' => 'different']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        User::factory()->create(['email' => 'reader@example.com']);
        $this->postJson('/api/register', $this->registration(['email' => 'READER@example.com']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_registration_rejects_invalid_fields_and_short_password(): void
    {
        $this->postJson('/api/register', $this->registration([
            'name' => '', 'email' => 'invalid', 'password' => 'short', 'password_confirmation' => 'short',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_returns_actual_role_and_rejects_wrong_credentials(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'password' => 'secret-password']);
        $this->postJson('/api/login', ['email' => strtoupper($user->email), 'password' => 'secret-password'])
            ->assertOk()->assertJsonPath('user.role', 'admin')->assertJsonStructure(['token']);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/login', ['email' => 'missing@example.com', 'password' => 'wrong-password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_invalid_and_expired_tokens_are_rejected(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->withToken('invalid')->getJson('/api/me')->assertUnauthorized();
        $token = User::factory()->create()->createToken('expired', ['*'], now()->subMinute())->plainTextToken;
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('current')->plainTextToken;
        $other = $user->createToken('other')->plainTextToken;
        $this->withToken($token)->postJson('/api/logout')->assertNoContent();
        $this->assertNull(PersonalAccessToken::findToken($token));
        $this->assertNotNull(PersonalAccessToken::findToken($other));
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'missing@example.com', 'password' => 'wrong-password'])->assertUnprocessable();
        }
        $this->postJson('/api/login', ['email' => 'missing@example.com', 'password' => 'wrong-password'])->assertTooManyRequests();
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/register', [])->assertUnprocessable();
        }
        $this->postJson('/api/register', [])->assertTooManyRequests();
    }
}

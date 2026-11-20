<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomerRole(): Role
    {
        return Role::firstOrCreate(
            ['slug' => 'customer'],
            [
                'name' => 'Customer',
                'description' => 'Test customer',
            ]
        );
    }

    private function createUser(array $attributes = []): User
    {
        $role = $this->createCustomerRole();

        $user = new User();

        $user->forceFill(array_merge([
            'name' => 'Test Customer',
            'email' => uniqid() . '@test.com',
            'password' => 'password123',
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));

        $user->save();

        return $user;
    }

    public function test_customer_can_register_successfully(): void
    {
        $this->createCustomerRole();

        $response = $this->postJson('/api/register', [
            'name' => 'New Customer',
            'email' => 'newcustomer@test.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonPath('user.name', 'New Customer')
            ->assertJsonPath('user.role.slug', 'customer')
            ->assertJsonStructure([
                'user',
                'token',
            ]);

        $user = User::where(
            'email',
            'newcustomer@test.com'
        )->firstOrFail();

        $this->assertTrue(
            Hash::check('password123', $user->password)
        );

        $this->assertDatabaseHas('users', [
            'email' => 'newcustomer@test.com',
            'role_id' => $user->role_id,
            'is_active' => true,
        ]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $this->createCustomerRole();

        $this->createUser([
            'email' => 'duplicate@test.com',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Another Customer',
            'email' => 'duplicate@test.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_rejects_invalid_input(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
            ]);
    }

    public function test_active_user_can_login(): void
    {
        $user = $this->createUser([
            'email' => 'login@test.com',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@test.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role.slug', 'customer')
            ->assertJsonStructure(['token']);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->createUser([
            'email' => 'wrongpassword@test.com',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'wrongpassword@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->createUser([
            'email' => 'inactive@test.com',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@test.com',
            'password' => 'password123',
        ]);

        $response->assertForbidden()
            ->assertJsonPath(
                'message',
                'Your account has been deactivated.'
            );
    }

    public function test_authenticated_user_can_access_me_endpoint(): void
    {
        $user = $this->createUser();

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role.slug', 'customer');
    }

    public function test_guest_cannot_access_me_endpoint(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertUnauthorized();
    }

    public function test_logout_revokes_only_current_token(): void
{
    $user = $this->createUser();

    $firstToken = $user->createToken('first-token')->plainTextToken;
    $secondToken = $user->createToken('second-token')->plainTextToken;

    // Ambil ID token pertama dari format Sanctum: ID|TOKEN
    $firstTokenId = explode('|', $firstToken, 2)[0];

    $logoutResponse = $this->withToken($firstToken)
        ->postJson('/api/logout');

    $logoutResponse->assertOk()
        ->assertJsonPath('message', 'Logout successful');

    // Pastikan token pertama benar-benar terhapus.
    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $firstTokenId,
    ]);

    // Reset guard agar request berikutnya melakukan autentikasi ulang.
    $this->app['auth']->forgetGuards();

    $this->withToken($firstToken)
        ->getJson('/api/me')
        ->assertUnauthorized();

    $this->app['auth']->forgetGuards();

    // Token kedua seharusnya masih berlaku.
    $this->withToken($secondToken)
        ->getJson('/api/me')
        ->assertOk();
}
  
    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $this->createUser([
            'email' => 'ratelimit@test.com',
        ]);

        $credentials = [
            'email' => 'ratelimit@test.com',
            'password' => 'wrong-password',
        ];

        // Lima percobaan gagal masih mendapat respons validasi.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', $credentials)
                ->assertUnprocessable();
        }

        // Percobaan keenam ditolak oleh rate limiter.
        $this->postJson('/api/login', $credentials)
            ->assertStatus(429);
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        $this->createUser([
            'email' => 'resetlimit@test.com',
        ]);

        $wrongCredentials = [
            'email' => 'resetlimit@test.com',
            'password' => 'wrong-password',
        ];

        // Empat percobaan gagal.
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/login', $wrongCredentials)
                ->assertUnprocessable();
        }

        // Login berhasil harus mereset penghitung.
        $this->postJson('/api/login', [
            'email' => 'resetlimit@test.com',
            'password' => 'password123',
        ])->assertOk();

        // Lima kegagalan baru masih diperbolehkan.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', $wrongCredentials)
                ->assertUnprocessable();
        }

        // Kegagalan keenam setelah reset ditolak.
        $this->postJson('/api/login', $wrongCredentials)
            ->assertStatus(429);
    }

}

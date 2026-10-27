<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ApiTestCase;

class UserUpdateTest extends ApiTestCase
{
    public function testOwnerCanChangeNameWithoutSupplyingPassword(): void
    {
        $user = $this->user();
        $password = $user->password;

        $this->actingAs($user, 'api')->patchJson("/api/users/{$user->id}", ['name' => 'Updated Name'])
            ->assertStatus(202)->assertJsonPath('data.name', 'Updated Name');

        $this->assertSame($password, $user->fresh()->password);
    }

    public function testPasswordChangeIsHashedAndRevokesAllTokens(): void
    {
        $user = $this->user();
        $this->issueToken($user, 'old-token');
        $this->issueToken($user, 'other-device-token');

        $this->actingAs($user, 'api')->patchJson("/api/users/{$user->id}", [
            'current_password' => 'secret', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertStatus(202);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertDatabaseMissing('api_tokens', ['user_id' => $user->id]);
    }

    public static function credentialChangesWithoutCurrentPassword(): array
    {
        return [
            'email, missing' => [['email' => 'changed@example.com']],
            'password, missing' => [['password' => 'new-password', 'password_confirmation' => 'new-password']],
            'password, wrong' => [[
                'current_password' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password',
            ]],
        ];
    }

    #[DataProvider('credentialChangesWithoutCurrentPassword')]
    public function testCredentialChangeRequiresCurrentPassword(array $changes): void
    {
        $user = $this->user();
        $this->issueToken($user, 'stolen-token');

        $this->withToken('stolen-token')->patchJson("/api/users/{$user->id}", $changes)
            ->assertUnprocessable()->assertJsonStructure(['error' => ['current_password']]);

        $this->assertTrue(Hash::check('secret', $user->fresh()->password));
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertDatabaseHas('api_tokens', ['user_id' => $user->id]);
    }

    public function testEmailChangeResetsVerificationAndToken(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'api')->patchJson("/api/users/{$user->id}", [
            'current_password' => 'secret', 'email' => 'new@example.com',
        ])->assertStatus(202);

        $this->assertSame(User::UNVERIFIED_USER, $user->fresh()->verified);
        $this->assertNotEmpty($user->fresh()->verification_token);
    }

    public function testUserCannotUpdateAnotherAccount(): void
    {
        $user = $this->user();
        $other = $this->user();

        $this->actingAs($user, 'api')->patchJson("/api/users/{$other->id}", ['name' => 'Changed'])
            ->assertForbidden();
        $this->assertNotSame('Changed', $other->fresh()->name);
    }

    public function testRegularUserCannotPromoteThemself(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'api')->patchJson("/api/users/{$user->id}", ['admin' => User::ADMIN_USER])
            ->assertForbidden();
        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function testAdminCanChangeVerifiedUsersRole(): void
    {
        $admin = $this->user(['admin' => User::ADMIN_USER]);
        $user = $this->user();

        $this->actingAs($admin, 'api')->patchJson("/api/users/{$user->id}", ['admin' => User::ADMIN_USER])
            ->assertStatus(202);
        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function testEmptyUpdateIsRejected(): void
    {
        $user = $this->user();
        $this->actingAs($user, 'api')->patchJson("/api/users/{$user->id}", [])->assertUnprocessable();
    }

    public function testPasswordConfirmationIsRequiredWhenChangingPassword(): void
    {
        $user = $this->user();
        $this->actingAs($user, 'api')->patchJson("/api/users/{$user->id}", [
            'current_password' => 'secret', 'password' => 'new-password',
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['password']]);
    }

    public static function credentialChanges(): array
    {
        return [
            [['current_password' => 'secret', 'email' => 'changed@example.com']],
            [['current_password' => 'secret', 'password' => 'new-password', 'password_confirmation' => 'new-password']],
        ];
    }

    #[DataProvider('credentialChanges')]
    public function testCredentialChangeRevokesBearerToken(array $changes): void
    {
        $user = $this->user();
        $this->issueToken($user, 'existing-token');
        $this->withToken('existing-token')->patchJson("/api/users/{$user->id}", $changes)->assertStatus(202);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/users/{$user->id}")->assertUnauthorized();
    }
}

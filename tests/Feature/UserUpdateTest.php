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

    public function testPasswordChangeIsHashedAndRevokesExistingToken(): void
    {
        $user = $this->user();
        $user->forceFill(['api_token' => hash('sha256', 'old-token')])->save();

        $this->actingAs($user, 'api')->patchJson("/api/users/{$user->id}", [
            'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertStatus(202);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertNull($user->fresh()->api_token);
    }

    public function testEmailChangeResetsVerificationAndToken(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'api')->patchJson("/api/users/{$user->id}", ['email' => 'new@example.com'])
            ->assertStatus(202);

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
        $this->actingAs($user, 'api')->patchJson("/api/users/{$user->id}", ['password' => 'new-password'])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['password']]);
    }

    public static function credentialChanges(): array
    {
        return [
            [['email' => 'changed@example.com']],
            [['password' => 'new-password', 'password_confirmation' => 'new-password']],
        ];
    }

    #[DataProvider('credentialChanges')]
    public function testCredentialChangeRevokesBearerToken(array $changes): void
    {
        $user = $this->user();
        $user->forceFill(['api_token' => hash('sha256', 'existing-token')])->save();
        $this->withToken('existing-token')->patchJson("/api/users/{$user->id}", $changes)->assertStatus(202);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/users/{$user->id}")->assertUnauthorized();
    }
}

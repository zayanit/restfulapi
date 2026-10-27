<?php

namespace Tests\Feature;

use App\Category;
use App\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ApiTestCase;

class ApiAuthenticationTest extends ApiTestCase
{
    public static function protectedEndpoints(): array
    {
        return [
            ['GET', '/api/users'], ['GET', '/api/users/1'],
            ['PATCH', '/api/users/1'], ['DELETE', '/api/users/1'],
            ['POST', '/api/categories'], ['PATCH', '/api/categories/1'], ['DELETE', '/api/categories/1'],
            ['POST', '/api/sellers/1/products'], ['PATCH', '/api/sellers/1/products/1'],
            ['DELETE', '/api/sellers/1/products/1'], ['PUT', '/api/products/1/categories/1'],
            ['DELETE', '/api/products/1/categories/1'], ['POST', '/api/products/1/buyers/1/transactions'],
            ['POST', '/api/logout'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function testProtectedEndpointsRequireAuthentication(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized()->assertJsonPath('code', 401);
    }

    public function testCatalogRemainsPublic(): void
    {
        $this->getJson('/api/products')->assertOk()->assertExactJson(['data' => []]);
    }

    public function testRegistrationCannotSetPrivilegesOrExposeTokens(): void
    {
        $this->postJson('/api/users', [
            'name' => 'New User', 'email' => 'new@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'admin' => User::ADMIN_USER, 'verified' => User::VERIFIED_USER, 'api_token' => 'injected',
        ])->assertCreated()->assertJsonPath('data.admin', User::REGULAR_USER)
            ->assertJsonPath('data.verified', User::UNVERIFIED_USER)
            ->assertJsonMissingPath('data.api_token')->assertJsonMissingPath('data.password');
    }

    public function testLoginIssuesHashedTokenThatAuthenticatesRequests(): void
    {
        $user = $this->user();
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret'])
            ->assertOk()->assertJsonPath('data.token_type', 'Bearer');
        $token = $response->json('data.token');
        $this->assertSame(hash('sha256', $token), $user->fresh()->api_token);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson("/api/users/{$user->id}")->assertOk()
            ->assertJsonMissingPath('data.api_token');
    }

    public function testInvalidCredentialsAreRejected(): void
    {
        $user = $this->user();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnauthorized();
    }

    public function testUnknownAccountCannotLogin(): void
    {
        $this->postJson('/api/login', ['email' => 'missing@example.com', 'password' => 'wrong'])
            ->assertUnauthorized()->assertJsonPath('error', 'Invalid credentials');
    }

    public function testRepeatedLoginFailuresAreRateLimited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'missing@example.com', 'password' => 'wrong'])
                ->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => 'missing@example.com', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('Retry-After');
    }

    public function testNewLoginReplacesPreviousToken(): void
    {
        $user = $this->user();
        $user->forceFill(['api_token' => hash('sha256', 'previous-token')])->save();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret'])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken('previous-token')->getJson("/api/users/{$user->id}")->assertUnauthorized();
    }

    public function testInvalidTokenIsRejected(): void
    {
        $user = $this->user();
        $this->withToken('invalid-token')->getJson("/api/users/{$user->id}")->assertUnauthorized();
    }

    public function testDeletedAccountCannotUseToken(): void
    {
        $user = $this->user();
        $user->forceFill(['api_token' => hash('sha256', 'deleted-token')])->save();
        $user->delete();
        $this->withToken('deleted-token')->getJson('/api/users')->assertUnauthorized();
    }

    public function testAuthenticationFailureIsJsonWithoutAcceptHeader(): void
    {
        $this->get('/api/users')->assertUnauthorized()->assertJsonPath('code', 401);
    }

    public function testLogoutRevokesToken(): void
    {
        $user = $this->user();
        $user->forceFill(['api_token' => hash('sha256', 'logout-token')])->save();

        $this->withToken('logout-token')->postJson('/api/logout')->assertOk();
        $this->assertNull($user->fresh()->api_token);
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/users/{$user->id}")->assertUnauthorized();
    }

    public function testRegularUserCannotListAccounts(): void
    {
        $this->actingAs($this->user(), 'api')->getJson('/api/users')->assertForbidden();
    }

    public function testRegularUserCannotManageCategories(): void
    {
        $this->actingAs($this->user(), 'api')->postJson('/api/categories', [
            'name' => 'Category', 'description' => 'Description',
        ])->assertForbidden();
        $this->assertDatabaseCount('categories', 0);
    }

    public function testAdminCanManageCategories(): void
    {
        $category = Category::factory()->create();
        $this->actingAs($this->user(['admin' => User::ADMIN_USER]), 'api')
            ->patchJson("/api/categories/{$category->id}", ['name' => 'Updated'])
            ->assertOk()->assertJsonPath('data.name', 'Updated');
    }
}

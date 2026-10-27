<?php

namespace Tests\Unit;

use App\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testRegularUserIsNotAnAdmin(): void
    {
        $this->assertFalse((new User(['admin' => User::REGULAR_USER]))->isAdmin());
    }

    public function testAdminFlagGrantsAdminRole(): void
    {
        $this->assertTrue((new User(['admin' => User::ADMIN_USER]))->isAdmin());
    }

    public function testVerificationFlagIsSeparateFromRole(): void
    {
        $user = new User(['verified' => User::UNVERIFIED_USER, 'admin' => User::ADMIN_USER]);
        $this->assertFalse($user->isVerified());
    }
}

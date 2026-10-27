<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\ApiTestCase;

class ApiTokenMigrationTest extends ApiTestCase
{
    public function testTokenMigrationCanBeRolledBackAndReapplied(): void
    {
        $migration = require database_path('migrations/2026_10_27_000000_add_api_token_to_users_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'api_token'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'api_token'));
    }
}

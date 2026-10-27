<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\ApiTestCase;

class ApiTokensMigrationTest extends ApiTestCase
{
    public function testTokenMigrationCanBeRolledBackAndReapplied(): void
    {
        $migration = require database_path('migrations/2026_10_27_000000_create_api_tokens_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('api_tokens'));

        $migration->up();
        $this->assertTrue(Schema::hasColumns('api_tokens', ['user_id', 'token']));
    }
}

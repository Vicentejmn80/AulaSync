<?php

namespace Tests\Unit;

use App\Support\DatabaseBoolean;
use Tests\TestCase;

class DatabaseBooleanTest extends TestCase
{
    public function test_pgsql_compares_boolean_not_integer(): void
    {
        $this->assertSame('generated_by_ai = true', DatabaseBoolean::equals('generated_by_ai', true, 'pgsql'));
        $this->assertSame('generated_by_ai = false', DatabaseBoolean::equals('generated_by_ai', false, 'pgsql'));
        $this->assertSame('generated_by_ai = 1', DatabaseBoolean::equals('generated_by_ai', true, 'sqlite'));
    }

    public function test_pgsql_bind_uses_sql_boolean_literals(): void
    {
        $true = DatabaseBoolean::bind(true, 'pgsql');
        $false = DatabaseBoolean::bind(false, 'pgsql');

        $this->assertInstanceOf(\Illuminate\Database\Query\Expression::class, $true);
        $this->assertInstanceOf(\Illuminate\Database\Query\Expression::class, $false);
        $this->assertTrue(DatabaseBoolean::bind(true, 'sqlite'));
        $this->assertFalse(DatabaseBoolean::bind(false, 'sqlite'));
    }
}

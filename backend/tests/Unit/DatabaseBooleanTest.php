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
}

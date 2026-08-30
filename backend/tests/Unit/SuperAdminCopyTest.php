<?php

namespace Tests\Unit;

use App\Support\SuperAdminCopy;
use Tests\TestCase;

class SuperAdminCopyTest extends TestCase
{
    public function test_maps_internal_codes_to_spanish(): void
    {
        $this->assertSame('Chat del director (consultas)', SuperAdminCopy::source('director_data_agent'));
        $this->assertSame('Consultó alumnos en riesgo', SuperAdminCopy::action('get_at_risk_students'));
        $this->assertSame('Falló al consultar los datos', SuperAdminCopy::error('tool_failed'));
        $this->assertSame('Llegó al límite diario de consultas', SuperAdminCopy::error('daily_limit_exceeded'));
        $this->assertSame('Sin respuesta clara', SuperAdminCopy::status('unresolved'));
        $this->assertSame('Docente', SuperAdminCopy::role('profesor'));
        $this->assertSame('Plantel y matrícula', SuperAdminCopy::category('roster'));
        $this->assertSame('Estable', SuperAdminCopy::status('estable'));
        $this->assertSame('Asignar docente a un curso', SuperAdminCopy::action('assign_teacher'));
        $this->assertSame('Cargar varios alumnos', SuperAdminCopy::action('create_students_batch'));
        $this->assertSame('Esperando confirmación', SuperAdminCopy::status('pending_confirmation'));
        $this->assertSame('Hecho', SuperAdminCopy::status('verified'));
        $this->assertSame('25/08/2026', SuperAdminCopy::day('2026-08-25'));
    }
}

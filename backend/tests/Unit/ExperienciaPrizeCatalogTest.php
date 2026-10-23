<?php

namespace Tests\Unit;

use App\Services\ExperienciaPrizeCatalog;
use Tests\TestCase;

class ExperienciaPrizeCatalogTest extends TestCase
{
    public function test_digital_weights_land_on_the_configured_prize(): void
    {
        $catalog = app(ExperienciaPrizeCatalog::class);
        $pool = array_values(array_filter(
            config('experiencia.prizes'),
            fn (array $prize): bool => $prize['kind'] === 'digital'
        ));

        $this->assertSame('kit-ia-docentes', $catalog->pickFrom($pool, 1)['id']);
        $this->assertSame('kit-ia-docentes', $catalog->pickFrom($pool, 30)['id']);
        $this->assertSame('kit-plantillas', $catalog->pickFrom($pool, 31)['id']);
        $this->assertSame('kit-plantillas', $catalog->pickFrom($pool, 55)['id']);
        $this->assertSame('guia-ia-colegios', $catalog->pickFrom($pool, 56)['id']);
        $this->assertSame('guia-ia-colegios', $catalog->pickFrom($pool, 75)['id']);
    }

    public function test_full_weights_include_exclusive_prizes_at_their_boundaries(): void
    {
        $catalog = app(ExperienciaPrizeCatalog::class);
        $pool = config('experiencia.prizes');

        $this->assertSame(100, $catalog->totalWeight($pool));
        $this->assertSame('taller-grupal', $catalog->pickFrom($pool, 76)['id']);
        $this->assertSame('acceso-anticipado', $catalog->pickFrom($pool, 88)['id']);
        $this->assertSame('auditoria-administrativa', $catalog->pickFrom($pool, 96)['id']);
        $this->assertSame('sesion-directivos', $catalog->pickFrom($pool, 99)['id']);
        $this->assertSame('sesion-directivos', $catalog->pickFrom($pool, 100)['id']);
    }

    public function test_exclusive_prizes_stay_out_of_the_wheel_until_enabled(): void
    {
        config(['experiencia.exclusive_prizes' => false]);
        $ids = array_column(app(ExperienciaPrizeCatalog::class)->publicCatalog(), 'id');

        $this->assertSame(['kit-ia-docentes', 'kit-plantillas', 'guia-ia-colegios'], $ids);
    }
}

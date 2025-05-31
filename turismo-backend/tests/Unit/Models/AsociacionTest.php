<?php

namespace Tests\Unit\Models;

use App\Models\Asociacion;
use App\Models\Emprendedor;
use App\Models\Municipalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AsociacionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function puede_acceder_a_su_municipalidad()
    {
        $municipalidad = Municipalidad::factory()->create();
        $asociacion = Asociacion::factory()->create([
            'municipalidad_id' => $municipalidad->id
        ]);

        $this->assertInstanceOf(Municipalidad::class, $asociacion->municipalidad);
        $this->assertEquals($municipalidad->id, $asociacion->municipalidad->id);
    }

    /** @test */
    public function puede_tener_varios_emprendedores()
    {
        $asociacion = Asociacion::factory()->create();
        Emprendedor::factory()->count(3)->create([
            'asociacion_id' => $asociacion->id
        ]);

        $this->assertCount(3, $asociacion->emprendedores);
        $this->assertInstanceOf(Emprendedor::class, $asociacion->emprendedores->first());
    }

    /** @test */
    public function devuelve_null_si_no_tiene_imagen()
    {
        $asociacion = Asociacion::factory()->create(['imagen' => null]);

        $this->assertNull($asociacion->imagen_url);
    }

    /** @test */
    public function devuelve_url_completa_si_imagen_es_una_url()
    {
        $url = 'https://example.com/imagen.jpg';
        $asociacion = Asociacion::factory()->create(['imagen' => $url]);

        $this->assertEquals($url, $asociacion->imagen_url);
    }

    /** @test */
    public function genera_url_correcta_si_imagen_es_ruta_local()
    {
        Storage::fake('public');
        $ruta = 'asociaciones/imagen.jpg';
        $asociacion = Asociacion::factory()->create(['imagen' => $ruta]);

        $this->assertEquals(url(Storage::url($ruta)), $asociacion->imagen_url);
    }
}

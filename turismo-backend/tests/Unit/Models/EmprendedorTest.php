<?php

namespace Tests\Unit\Models;

use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\User;
use App\Models\Servicio;
use App\Models\Reserva;
use App\Models\Slider;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmprendedorTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function puede_acceder_a_su_asociacion()
    {
        $asociacion = Asociacion::factory()->create();
        $emprendedor = Emprendedor::factory()->create([
            'asociacion_id' => $asociacion->id
        ]);

        $this->assertInstanceOf(Asociacion::class, $emprendedor->asociacion);
        $this->assertEquals($asociacion->id, $emprendedor->asociacion->id);
    }

    /** @test */
    public function puede_tener_varios_administradores()
    {
        $emprendedor = Emprendedor::factory()->create();
        $usuarios = User::factory()->count(3)->create();

        foreach ($usuarios as $usuario) {
            $emprendedor->administradores()->attach($usuario->id, [
                'es_principal' => false,
                'rol' => 'administrador'
            ]);
        }

        $this->assertCount(3, $emprendedor->administradores);
        $this->assertInstanceOf(User::class, $emprendedor->administradores->first());
    }

    /** @test */
    public function puede_obtener_administrador_principal()
    {
        $emprendedor = Emprendedor::factory()->create();
        $usuarioPrincipal = User::factory()->create();
        $usuarioSecundario = User::factory()->create();

        $emprendedor->administradores()->attach($usuarioPrincipal->id, [
            'es_principal' => true,
            'rol' => 'principal'
        ]);

        $emprendedor->administradores()->attach($usuarioSecundario->id, [
            'es_principal' => false,
            'rol' => 'administrador'
        ]);

        $administradorPrincipal = $emprendedor->administradorPrincipal();

        $this->assertInstanceOf(User::class, $administradorPrincipal);
        $this->assertEquals($usuarioPrincipal->id, $administradorPrincipal->id);
    }

    /** @test */
    public function devuelve_null_si_no_hay_administrador_principal()
    {
        $emprendedor = Emprendedor::factory()->create();
        $usuario = User::factory()->create();

        $emprendedor->administradores()->attach($usuario->id, [
            'es_principal' => false,
            'rol' => 'administrador'
        ]);

        $administradorPrincipal = $emprendedor->administradorPrincipal();

        $this->assertNull($administradorPrincipal);
    }

    /** @test */
    public function puede_tener_varios_servicios()
    {
        $emprendedor = Emprendedor::factory()->create();
        Servicio::factory()->count(4)->create([
            'emprendedor_id' => $emprendedor->id
        ]);

        $this->assertCount(4, $emprendedor->servicios);
        $this->assertInstanceOf(Servicio::class, $emprendedor->servicios->first());
    }

    /** @test */
    public function puede_tener_varias_reservas()
    {
        $emprendedor = Emprendedor::factory()->create();
        $reservas = Reserva::factory()->count(2)->create();

        foreach ($reservas as $reserva) {
            $emprendedor->reservas()->attach($reserva->id, [
                'descripcion' => 'Servicio de prueba',
                'cantidad' => 1
            ]);
        }

        $this->assertCount(2, $emprendedor->reservas);
        $this->assertInstanceOf(Reserva::class, $emprendedor->reservas->first());
    }

    /** @test */
    public function puede_tener_varios_sliders()
    {
        $emprendedor = Emprendedor::factory()->create();
        Slider::factory()->count(3)->create([
            'entidad_id' => $emprendedor->id,
            'tipo_entidad' => 'emprendedor'
        ]);

        $this->assertCount(3, $emprendedor->sliders);
        $this->assertInstanceOf(Slider::class, $emprendedor->sliders->first());
    }

    /** @test */
    public function puede_obtener_sliders_principales()
    {
        $emprendedor = Emprendedor::factory()->create();

        // Crear sliders principales
        Slider::factory()->count(2)->create([
            'entidad_id' => $emprendedor->id,
            'tipo_entidad' => 'emprendedor',
            'es_principal' => true
        ]);

        // Crear sliders secundarios
        Slider::factory()->count(3)->create([
            'entidad_id' => $emprendedor->id,
            'tipo_entidad' => 'emprendedor',
            'es_principal' => false
        ]);

        $this->assertCount(2, $emprendedor->slidersPrincipales);
    }

    /** @test */
    public function puede_obtener_sliders_secundarios()
    {
        $emprendedor = Emprendedor::factory()->create();

        // Crear sliders principales
        Slider::factory()->count(1)->create([
            'entidad_id' => $emprendedor->id,
            'tipo_entidad' => 'emprendedor',
            'es_principal' => true
        ]);

        // Crear sliders secundarios
        Slider::factory()->count(3)->create([
            'entidad_id' => $emprendedor->id,
            'tipo_entidad' => 'emprendedor',
            'es_principal' => false
        ]);

        $this->assertCount(3, $emprendedor->slidersSecundarios);
    }

    /** @test */
    public function puede_tener_varios_eventos()
    {
        $emprendedor = Emprendedor::factory()->create();
        Evento::factory()->count(2)->create([
            'id_emprendedor' => $emprendedor->id
        ]);

        $this->assertCount(2, $emprendedor->eventos);
        $this->assertInstanceOf(Evento::class, $emprendedor->eventos->first());
    }

    /** @test */
    public function cast_metodos_pago_como_array()
    {
        $metodosPago = ['efectivo', 'tarjeta', 'transferencia'];
        $emprendedor = Emprendedor::factory()->create([
            'metodos_pago' => $metodosPago
        ]);

        $this->assertIsArray($emprendedor->metodos_pago);
        $this->assertEquals($metodosPago, $emprendedor->metodos_pago);
    }

    /** @test */
    public function cast_imagenes_como_array()
    {
        $imagenes = ['imagen1.jpg', 'imagen2.jpg', 'imagen3.jpg'];
        $emprendedor = Emprendedor::factory()->create([
            'imagenes' => $imagenes
        ]);

        $this->assertIsArray($emprendedor->imagenes);
        $this->assertEquals($imagenes, $emprendedor->imagenes);
    }

    /** @test */
    public function cast_certificaciones_como_array()
    {
        $certificaciones = ['ISO 9001', 'Certificación turística'];
        $emprendedor = Emprendedor::factory()->create([
            'certificaciones' => $certificaciones
        ]);

        $this->assertIsArray($emprendedor->certificaciones);
        $this->assertEquals($certificaciones, $emprendedor->certificaciones);
    }

    /** @test */
    public function cast_idiomas_hablados_como_array()
    {
        $idiomas = ['Español', 'Inglés', 'Quechua'];
        $emprendedor = Emprendedor::factory()->create([
            'idiomas_hablados' => $idiomas
        ]);

        $this->assertIsArray($emprendedor->idiomas_hablados);
        $this->assertEquals($idiomas, $emprendedor->idiomas_hablados);
    }

    /** @test */
    public function cast_opciones_acceso_como_array()
    {
        $opcionesAcceso = ['rampa', 'ascensor', 'estacionamiento'];
        $emprendedor = Emprendedor::factory()->create([
            'opciones_acceso' => $opcionesAcceso
        ]);

        $this->assertIsArray($emprendedor->opciones_acceso);
        $this->assertEquals($opcionesAcceso, $emprendedor->opciones_acceso);
    }

    /** @test */
    public function cast_facilidades_discapacidad_como_boolean()
    {
        $emprendedor = Emprendedor::factory()->create([
            'facilidades_discapacidad' => 1
        ]);

        $this->assertIsBool($emprendedor->facilidades_discapacidad);
        $this->assertTrue($emprendedor->facilidades_discapacidad);

        $emprendedor = Emprendedor::factory()->create([
            'facilidades_discapacidad' => 0
        ]);

        $this->assertIsBool($emprendedor->facilidades_discapacidad);
        $this->assertFalse($emprendedor->facilidades_discapacidad);
    }

    /** @test */
    public function cast_estado_como_boolean()
    {
        $emprendedor = Emprendedor::factory()->create(['estado' => 1]);

        $this->assertIsBool($emprendedor->estado);
        $this->assertTrue($emprendedor->estado);

        $emprendedor = Emprendedor::factory()->create(['estado' => 0]);

        $this->assertIsBool($emprendedor->estado);
        $this->assertFalse($emprendedor->estado);
    }

    /** @test */
    public function usa_tabla_emprendedores()
    {
        $emprendedor = new Emprendedor();

        $this->assertEquals('emprendedores', $emprendedor->getTable());
    }
}

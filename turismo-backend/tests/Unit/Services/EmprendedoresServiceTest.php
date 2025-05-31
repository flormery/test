<?php

namespace Tests\Unit\Services;

use App\Models\Emprendedor;
use App\Models\User;
use App\Models\Slider;
use App\Repository\SliderRepository;
use App\Services\EmprendedoresService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class EmprendedoresServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EmprendedoresService $service;
    protected $sliderRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sliderRepositoryMock = Mockery::mock(SliderRepository::class);
        $this->service = new EmprendedoresService($this->sliderRepositoryMock);
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function getAll_retorna_paginacion_correcta()
    {
        Emprendedor::factory()->count(30)->create();

        $result = $this->service->getAll(10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(30, $result->total());
        $this->assertCount(10, $result->items());
    }

    /** @test */
    public function getAll_filtra_por_usuario_actual_cuando_soloDelUsuarioActual_es_true()
    {
        // Preparar usuario autenticado
        $user = User::factory()->create();
        Auth::login($user);

        $emprendedor = Emprendedor::factory()->create();
        $emprendedor->administradores()->attach($user->id, ['es_principal' => true, 'rol' => 'principal']);

        // Otro emprendedor sin relacion con usuario
        Emprendedor::factory()->create();

        $result = $this->service->getAll(10, true);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(1, $result->items());
        $this->assertEquals($emprendedor->id, $result->items()[0]->id);
    }

    /** @test */
    public function getById_retorna_emprendedor_con_relaciones_o_null()
    {
        $emprendedor = Emprendedor::factory()->create();

        $found = $this->service->getById($emprendedor->id);
        $notFound = $this->service->getById(999999);

        $this->assertInstanceOf(Emprendedor::class, $found);
        $this->assertEquals($emprendedor->id, $found->id);
        $this->assertNull($notFound);
    }

    /** @test */
    public function create_guarda_emprendedor_y_crea_sliders()
    {
        $data = Emprendedor::factory()->make()->toArray();

        $slidersPrincipales = [
            ['titulo' => 'Slider 1', 'descripcion' => 'Desc 1', 'imagen' => 'img1.jpg'],
            ['titulo' => 'Slider 2', 'descripcion' => 'Desc 2', 'imagen' => 'img2.jpg'],
        ];
        $slidersSecundarios = [
            ['titulo' => 'Slider 3', 'descripcion' => 'Desc 3', 'imagen' => 'img3.jpg'],
        ];

        $data['sliders_principales'] = $slidersPrincipales;
        $data['sliders_secundarios'] = $slidersSecundarios;

        // Esperar llamadas a repository
        $this->sliderRepositoryMock
            ->shouldReceive('createMultiple')
            ->once()
            ->with('emprendedor', Mockery::type('int'), Mockery::on(function ($param) use ($slidersPrincipales) {
                // Validar que se seteo 'es_principal' en true en todos los sliders principales
                foreach ($param as $slider) {
                    if (!$slider['es_principal']) return false;
                }
                return true;
            }));

        $this->sliderRepositoryMock
            ->shouldReceive('createMultiple')
            ->once()
            ->with('emprendedor', Mockery::type('int'), Mockery::on(function ($param) use ($slidersSecundarios) {
                foreach ($param as $slider) {
                    if ($slider['es_principal']) return false;
                }
                return true;
            }));

        $result = $this->service->create($data);

        $this->assertInstanceOf(Emprendedor::class, $result);
        $this->assertEquals($data['nombre'], $result->nombre);
    }

    /** @test */
    public function update_actualiza_emprendedor_y_sliders()
    {
        $emprendedor = Emprendedor::factory()->create();

        $data = [
            'nombre' => 'Nombre actualizado',
            'sliders_principales' => [
                ['id' => 1, 'titulo' => 'Principal Modificado'],
            ],
            'sliders_secundarios' => [
                ['id' => 2, 'titulo' => 'Secundario Modificado'],
            ],
            'deleted_sliders' => [3]
        ];

        $this->sliderRepositoryMock
            ->shouldReceive('delete')
            ->once()
            ->with(3);

        $this->sliderRepositoryMock
            ->shouldReceive('updateEntitySliders')
            ->once()
            ->with('emprendedor', $emprendedor->id, Mockery::on(function ($param) {
                foreach ($param as $slider) {
                    if (!$slider['es_principal']) return false;
                }
                return true;
            }));

        $this->sliderRepositoryMock
            ->shouldReceive('updateEntitySliders')
            ->once()
            ->with('emprendedor', $emprendedor->id, Mockery::on(function ($param) {
                foreach ($param as $slider) {
                    if ($slider['es_principal']) return false;
                }
                return true;
            }));

        $result = $this->service->update($emprendedor->id, $data);

        $this->assertInstanceOf(Emprendedor::class, $result);
        $this->assertEquals('Nombre actualizado', $result->nombre);
    }

    /** @test */
    public function update_retorna_null_si_no_existe_emprendedor()
    {
        $result = $this->service->update(999999, ['nombre' => 'Test']);
        $this->assertNull($result);
    }

    /** @test */
    public function delete_elimina_emprendedor_y_sliders_y_detaches_administradores()
    {
        $emprendedor = Emprendedor::factory()->create();
        $slider = Slider::factory()->create(['entidad_id' => $emprendedor->id, 'tipo_entidad' => 'emprendedor']);
        $emprendedor->sliders()->save($slider);

        $emprendedor->administradores()->attach(User::factory()->create()->id);

        $this->sliderRepositoryMock
            ->shouldReceive('delete')
            ->once()
            ->with($slider->id);

        $result = $this->service->delete($emprendedor->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('emprendedores', ['id' => $emprendedor->id]);
    }

    /** @test */
    public function delete_retorna_false_si_no_existe_emprendedor()
    {
        $result = $this->service->delete(999999);
        $this->assertFalse($result);
    }

    /** @test */
    public function findByCategory_retorna_coleccion()
    {
        $categoria = 'categoria_test';
        Emprendedor::factory()->count(2)->create(['categoria' => $categoria]);
        Emprendedor::factory()->count(1)->create(['categoria' => 'otra_categoria']);

        $result = $this->service->findByCategory($categoria);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    /** @test */
    public function findByAsociacion_retorna_coleccion()
    {
        $asociacionId = 1;
        Emprendedor::factory()->count(2)->create(['asociacion_id' => $asociacionId]);
        Emprendedor::factory()->count(1)->create(['asociacion_id' => 9999]);

        $result = $this->service->findByAsociacion($asociacionId);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    /** @test */
    public function search_retorna_coleccion()
    {
        $nombre = 'EmprendedorTest';
        Emprendedor::factory()->create(['nombre' => $nombre]);
        Emprendedor::factory()->create(['descripcion' => $nombre]);
        Emprendedor::factory()->create(['nombre' => 'Otro nombre']);

        $result = $this->service->search('EmprendedorTest');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(2, $result->count());
    }

    /** @test */
    public function getWithRelations_retorna_emprendedor_con_relaciones()
    {
        $emprendedor = Emprendedor::factory()->create();

        $result = $this->service->getWithRelations($emprendedor->id);

        $this->assertInstanceOf(Emprendedor::class, $result);
        $this->assertTrue($result->relationLoaded('asociacion'));
        $this->assertTrue($result->relationLoaded('servicios'));
        $this->assertTrue($result->relationLoaded('slidersPrincipales'));
        $this->assertTrue($result->relationLoaded('slidersSecundarios'));
        $this->assertTrue($result->relationLoaded('administradores'));
        $this->assertTrue($result->relationLoaded('reservas'));
    }

    /** @test */
    public function getByUserId_retorna_emprendedores_de_usuario()
    {
        $user = User::factory()->create();
        $emprendedor = Emprendedor::factory()->create();
        $emprendedor->administradores()->attach($user->id, ['rol' => 'administrador', 'es_principal' => false]);

        $result = $this->service->getByUserId($user->id);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /** @test */
    public function esAdministrador_retorna_true_o_false()
    {
        $user = User::factory()->create();
        $emprendedor = Emprendedor::factory()->create();
        $emprendedor->administradores()->attach($user->id);

        $this->assertTrue($this->service->esAdministrador($emprendedor->id, $user->id));
        $this->assertFalse($this->service->esAdministrador($emprendedor->id, 999999));
    }

    /** @test */
    public function esAdministradorPrincipal_retorna_true_o_false()
    {
        $user = User::factory()->create();
        $emprendedor = Emprendedor::factory()->create();
        $emprendedor->administradores()->attach($user->id, ['es_principal' => true]);

        $this->assertTrue($this->service->esAdministradorPrincipal($emprendedor->id, $user->id));
        $this->assertFalse($this->service->esAdministradorPrincipal($emprendedor->id, 999999));
    }
}

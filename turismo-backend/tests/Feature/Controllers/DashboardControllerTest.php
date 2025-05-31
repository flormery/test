<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear permisos y roles
        $this->createPermissionsAndRoles();

        // Crear usuario administrador y asignar rol
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
    }

    private function createPermissionsAndRoles(): void
    {
        // Crear algunos permisos dummy para que el Dashboard tenga datos
        $permissions = ['manage_users', 'view_dashboard', 'edit_settings', 'user_read'];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Crear roles y asignar permisos
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo($permissions);

        Role::firstOrCreate(['name' => 'user']);
    }

    #[Test]
    public function puede_obtener_el_resumen_del_dashboard()
    {
        // Autenticar al usuario admin
        Sanctum::actingAs($this->adminUser, ['*']);

        // Crear más usuarios con diferentes estados y roles
        $user1 = User::factory()->create(['active' => true]);
        $user1->assignRole('user');

        $user2 = User::factory()->create(['active' => false]);
        $user2->assignRole('user');

        // Llamar a la ruta
        $response = $this->getJson('/api/dashboard/summary');

        // Verificar estado HTTP y estructura básica
        $response->assertStatus(Response::HTTP_OK)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total_users',
                         'active_users',
                         'inactive_users',
                         'users_by_role' => [
                             '*' => ['role', 'count']
                         ],
                         'total_roles',
                         'total_permissions',
                         'recent_users' => [
                             '*' => [
                                 'id', 'name', 'email', 'roles'
                             ]
                         ]
                     ]
                 ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(3, $response->json('data.total_users')); // admin + user1 + user2
    }
}

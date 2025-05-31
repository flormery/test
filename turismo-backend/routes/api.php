<?php

use App\Http\Controllers\API\AccessControl\PermissionController;
use App\Http\Controllers\API\AccessControl\RoleController;
use App\Http\Controllers\API\AccessControl\UserController;
use App\Http\Controllers\API\Asociaciones\AsociacionController;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Auth\GoogleAuthController;
use App\Http\Controllers\API\Dashboard\DashboardController;
use App\Http\Controllers\API\Emprendedores\EmprendedorController;
use App\Http\Controllers\API\Emprendedores\MisEmprendimientosController;
use App\Http\Controllers\API\Evento\EventController;
use App\Http\Controllers\API\PageGeneral\MunicipalidadController;
use App\Http\Controllers\API\PageGeneral\SliderController;
use App\Http\Controllers\API\Reservas\ReservaController;
use App\Http\Controllers\API\Reservas\ReservaServicioController;
use App\Http\Controllers\API\Servicios\CategoriaController;
use App\Http\Controllers\API\Servicios\ServicioController;
use App\Http\Controllers\API\Planes\PlanController;
use App\Http\Controllers\API\Planes\PlanInscripcionController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\API\Reservas\CarritoReservaController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí se registran las rutas de la API del sistema
|
*/

// ===== RUTAS PÚBLICAS =====

// Autenticación
// Rutas públicas de autenticación
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/', fn() => response()->json(['status' => 'ok']));

// Rutas para autenticación con Google
Route::prefix('auth/google')->group(function () {
    Route::get('/', [GoogleAuthController::class, 'redirectToGoogle']);
    Route::get('/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
    Route::post('/verify-token', [GoogleAuthController::class, 'verifyGoogleToken']);
});

// Rutas para verificación de correo
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');

// Rutas para recuperación de contraseña
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
// Rutas para Google Authentication


// ===== RUTAS PÚBLICAS DEL SISTEMA DE TURISMO =====

// Municipalidades
Route::prefix('municipalidad')->group(function () {
    Route::get('/', [MunicipalidadController::class, 'index']);
    Route::get('/{id}', [MunicipalidadController::class, 'show']);
    Route::get('/{id}/relaciones', [MunicipalidadController::class, 'getWithRelations']);
    Route::get('/{id}/asociaciones', [MunicipalidadController::class, 'getWithAsociaciones']);
    Route::get('/{id}/asociaciones/emprendedores', [MunicipalidadController::class, 'getWithAsociacionesAndEmprendedores']);
});

// Sliders
Route::prefix('sliders')->group(function () {
    Route::get('/', [SliderController::class, 'index']);
    Route::get('/{id}', [SliderController::class, 'show']);
    Route::get('/entidad/{tipo}/{id}', [SliderController::class, 'getByEntidad']);
    Route::get('/municipalidad/{municipalidadId}', [SliderController::class, 'getByMunicipalidadId']);
    Route::get('/{id}/with-descripciones', [SliderController::class, 'getWithDescripciones']);
    Route::get('/sliders/{id}/image', [SliderController::class, 'getImage']);
});

// Asociaciones
Route::prefix('asociaciones')->group(function () {
    Route::get('/', [AsociacionController::class, 'index']);
    Route::get('/{id}', [AsociacionController::class, 'show']);
    Route::get('/{id}/emprendedores', [AsociacionController::class, 'getEmprendedores']);
    Route::get('/municipalidad/{municipalidadId}', [AsociacionController::class, 'getByMunicipalidad']);
    Route::get('/ubicacion/buscar', [AsociacionController::class, 'getByUbicacion']);
});

// Emprendedores (rutas públicas)
Route::prefix('emprendedores')->group(function () {
    Route::get('/', [EmprendedorController::class, 'index']);
    Route::get('/{id}', [EmprendedorController::class, 'show']);
    Route::get('/categoria/{categoria}', [EmprendedorController::class, 'byCategory']);
    Route::get('/asociacion/{asociacionId}', [EmprendedorController::class, 'byAsociacion']);
    Route::get('/search', [EmprendedorController::class, 'search']);
    Route::get('/{id}/servicios', [EmprendedorController::class, 'getServicios']);
    Route::get('/{id}/relaciones', [EmprendedorController::class, 'getWithRelations']);
});

// Servicios
Route::prefix('servicios')->group(function () {
    Route::get('/', [ServicioController::class, 'index']);
    Route::get('/{id}', [ServicioController::class, 'show']);
    Route::get('/emprendedor/{emprendedorId}', [ServicioController::class, 'byEmprendedor']);
    Route::get('/categoria/{categoriaId}', [ServicioController::class, 'byCategoria']);
    // Nueva ruta para verificar disponibilidad (no requiere autenticación)
    Route::get('/verificar-disponibilidad', [ServicioController::class, 'verificarDisponibilidad']);
    // Nueva ruta para obtener servicios por ubicación
    Route::get('/ubicacion', [ServicioController::class, 'byUbicacion']);
});

// Categorías
Route::prefix('categorias')->group(function () {
    Route::get('/', [CategoriaController::class, 'index']);
    Route::get('/{id}', [CategoriaController::class, 'show']);
});
Route::get('/users/search', [UserController::class, 'search']);
// Evento

Route::prefix('eventos')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::get('/{id}', [EventController::class, 'show']);
        Route::get('/emprendedor/{emprendedorId}', [EventController::class, 'byEmprendedor']);
        Route::get('/activos', [EventController::class, 'eventosActivos']);
        Route::get('/proximos', [EventController::class, 'proximosEventos']);
        
        // Crear, actualizar y eliminar (requieren autenticación)
        Route::post('/', [EventController::class, 'store']);
        Route::put('/{id}', [EventController::class, 'update']);
        Route::delete('/{id}', [EventController::class, 'destroy']);
    });
Route::prefix('planes')->group(function () {
    Route::get('/publicos', [PlanController::class, 'getPublicPlanes']);
    Route::get('/{id}', [PlanController::class, 'show']);
});
// ===== RUTAS PROTEGIDAS =====
Route::middleware('auth:sanctum')->group(function () {
    // Perfil de usuario
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    // Reenviar correo de verificación
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail']);
    
    // Menú dinámico
    Route::get('/menu', [MenuController::class, 'getMenu']);
    
    // Mis Emprendimientos (para usuarios emprendedores)
    Route::prefix('mis-emprendimientos')->group(function () {
        Route::get('/', [MisEmprendimientosController::class, 'index']);
        Route::get('/{id}', [MisEmprendimientosController::class, 'show']);
        Route::get('/{id}/servicios', [MisEmprendimientosController::class, 'getServicios']);
        Route::get('/{id}/reservas', [MisEmprendimientosController::class, 'getReservas']);
        Route::post('/{id}/administradores', [MisEmprendimientosController::class, 'agregarAdministrador']);
        Route::delete('/{id}/administradores/{userId}', [MisEmprendimientosController::class, 'eliminarAdministrador']);
    });
    
    // Municipalidades (rutas protegidas)
    Route::prefix('municipalidad')->group(function () {
        Route::post('/', [MunicipalidadController::class, 'store'])->middleware('permission:municipalidad_update');
        Route::put('/{id}', [MunicipalidadController::class, 'update'])->middleware('permission:municipalidad_update');
        Route::delete('/{id}', [MunicipalidadController::class, 'destroy'])->middleware('permission:municipalidad_update');
    });
    
    // Sliders (rutas protegidas)
    Route::prefix('sliders')->group(function () {
        Route::post('/', [SliderController::class, 'store']);
        Route::post('/multiple', [SliderController::class, 'storeMultiple']);
        Route::put('/{id}', [SliderController::class, 'update']);
        Route::delete('/{id}', [SliderController::class, 'destroy']);
    });
    
    // Asociaciones (rutas protegidas)
    Route::prefix('asociaciones')->group(function () {
        Route::post('/', [AsociacionController::class, 'store'])->middleware('permission:asociacion_create');
        Route::put('/{id}', [AsociacionController::class, 'update'])->middleware('permission:asociacion_update');
        Route::delete('/{id}', [AsociacionController::class, 'destroy'])->middleware('permission:asociacion_delete');
        Route::get('/{id}/emprendedores/detallado', [AsociacionController::class, 'getEmprendedores'])
            ->middleware('permission:asociacion_read');
            
        // Obtener asociaciones por municipalidad (versión protegida)
        Route::get('/municipalidad/{municipalidadId}/admin', [AsociacionController::class, 'getByMunicipalidad'])
            ->middleware('permission:asociacion_read');
            
        // Búsqueda por ubicación (versión protegida con más opciones)
        Route::post('/ubicacion/buscar-avanzada', [AsociacionController::class, 'getByUbicacion'])
            ->middleware('permission:asociacion_read');
    });
    
    // Emprendedores (rutas protegidas)
    Route::prefix('emprendedores')->group(function () {
        Route::post('/', [EmprendedorController::class, 'store'])->middleware('permission:emprendedor_create');
        Route::put('/{id}', [EmprendedorController::class, 'update']);
        Route::delete('/{id}', [EmprendedorController::class, 'destroy']);
        Route::get('/{id}/reservas', [EmprendedorController::class, 'getReservas']);
        
        // Gestión de administradores de emprendimientos
        Route::post('/{id}/administradores', [EmprendedorController::class, 'agregarAdministrador']);
        Route::delete('/{id}/administradores/{userId}', [EmprendedorController::class, 'eliminarAdministrador']);
    });
    
    // Servicios (rutas protegidas)
    Route::prefix('servicios')->group(function () {
        Route::post('/', [ServicioController::class, 'store']);
        Route::put('/{id}', [ServicioController::class, 'update']);
        Route::delete('/{id}', [ServicioController::class, 'destroy']);
    });
    
    // Categorías (rutas protegidas)
    Route::prefix('categorias')->group(function () {
        Route::post('/', [CategoriaController::class, 'store']);
        Route::put('/{id}', [CategoriaController::class, 'update']);
        Route::delete('/{id}', [CategoriaController::class, 'destroy']);
    });
    
    // Reservas (nuevas rutas)
    Route::prefix('reservas')->group(function () {
        Route::get('/mis-reservas', [ReservaController::class, 'misReservas']);
        Route::post('/mis-reservas', [ReservaController::class, 'createUserReservation']);
        Route::get('/', [ReservaController::class, 'index']);
        Route::get('/{id}', [ReservaController::class, 'show']);
        Route::post('/', [ReservaController::class, 'store']);
        Route::put('/{id}', [ReservaController::class, 'update']);
        Route::delete('/{id}', [ReservaController::class, 'destroy']);
        
        // Cambiar estado de la reserva
        Route::put('/{id}/estado', [ReservaController::class, 'cambiarEstado']);
        
        // Obtener reservas por emprendedor
        Route::get('/emprendedor/{emprendedorId}', [ReservaController::class, 'byEmprendedor']);
        
        // Obtener reservas por servicio
        Route::get('/servicio/{servicioId}', [ReservaController::class, 'byServicio']);
    });
    Route::get('reservas/carrito', [CarritoReservaController::class, 'obtenerCarrito']);
    Route::post('reservas/carrito/agregar', [CarritoReservaController::class, 'agregarAlCarrito']);
    Route::delete('reservas/carrito/servicio/{id}', [CarritoReservaController::class, 'eliminarDelCarrito']);
    Route::post('reservas/carrito/confirmar', [CarritoReservaController::class, 'confirmarCarrito']);
    Route::delete('reservas/carrito/vaciar', [CarritoReservaController::class, 'vaciarCarrito']);
    // Reserva Servicios (nuevas rutas)
    Route::prefix('reserva-servicios')->group(function () {
        // Obtener servicios por reserva
        Route::get('/reserva/{reservaId}', [ReservaServicioController::class, 'byReserva']);
        
        // Cambiar estado de un servicio reservado
        Route::put('/{id}/estado', [ReservaServicioController::class, 'cambiarEstado']);
        
        // Obtener servicios para calendario
        Route::get('/calendario', [ReservaServicioController::class, 'calendario']);
        
        // Verificar disponibilidad de un servicio
        Route::get('/verificar-disponibilidad', [ReservaServicioController::class, 'verificarDisponibilidad']);
    });
    
    // ===== RUTAS DE ADMINISTRACIÓN (CON PERMISOS) =====
    
    // Roles
    Route::prefix('roles')->middleware('permission:role_read')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:role_create');
        Route::put('/{id}', [RoleController::class, 'update'])->middleware('permission:role_update');
        Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('permission:role_delete');
    });
    Route::get('/users/{id}/permissions', [PermissionController::class, 'getUserPermissions']);
    // Permisos
    Route::prefix('permissions')->middleware('permission:permission_read')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::post('/assign-to-user', [PermissionController::class, 'assignPermissionsToUser'])->middleware('permission:permission_assign');
        Route::post('/assign-to-role', [PermissionController::class, 'assignPermissionsToRole'])->middleware('permission:permission_assign');
        
    });
    
    // Gestión de Usuarios
    Route::prefix('users')->middleware('can:user_read')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
        
        // Creación de usuario (requiere permiso user_create)
        Route::post('/', [UserController::class, 'store'])->middleware('can:user_create');
        
        // Actualización de usuario (requiere permiso user_update)
        Route::middleware('can:user_update')->group(function () {
            Route::put('/{id}', [UserController::class, 'update']);
            Route::patch('/{id}', [UserController::class, 'update']);
            Route::post('/{id}/activate', [UserController::class, 'activate']);
            Route::post('/{id}/deactivate', [UserController::class, 'deactivate']);
            Route::post('/{id}/roles', [UserController::class, 'assignRoles']);
            
            // Gestión de foto de perfil
            Route::post('/{id}/profile-photo', [UserController::class, 'updateProfilePhoto']);
            Route::delete('/{id}/profile-photo', [UserController::class, 'deleteProfilePhoto']);
        });
        
        // Eliminación de usuario (requiere permiso user_delete)
        Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('can:user_delete');
    });
    Route::prefix('planes')->group(function () {
        Route::get('/', [PlanController::class, 'index']);
        Route::post('/', [PlanController::class, 'store']);
        Route::put('/{id}', [PlanController::class, 'update']);
        Route::delete('/{id}', [PlanController::class, 'destroy']);
        
        // Inscripciones a planes
        Route::get('/mis-planes', [PlanInscripcionController::class, 'misPlanes']);
        Route::post('/{planId}/inscribirse', [PlanInscripcionController::class, 'inscribirse']);
        Route::put('/inscripciones/{inscripcionId}/cancelar', [PlanInscripcionController::class, 'cancelarInscripcion']);
        Route::put('/inscripciones/{inscripcionId}/confirmar', [PlanInscripcionController::class, 'confirmarInscripcion']);
        Route::post('/inscripciones/{inscripcionId}/generar-reservas', [PlanInscripcionController::class, 'generarReservas']);
    });
    // Dashboard
    Route::prefix('dashboard')->middleware('permission:user_read')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
    });
});
Route::get('/status', function () {
    return response()->json([
        'status' => 'online',
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String()
    ]);
});
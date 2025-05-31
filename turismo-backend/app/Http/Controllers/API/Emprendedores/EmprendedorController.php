<?php

namespace App\Http\Controllers\API\Emprendedores;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmprendedorRequest;
use App\Models\User;
use App\Services\EmprendedoresService;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class EmprendedorController extends Controller
{
    protected $emprendedorService;

    public function __construct(EmprendedoresService $emprendedorService)
    {
        $this->emprendedorService = $emprendedorService;
    }

    /**
     * Mostrar todos los emprendedores
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);
        $emprendedores = $this->emprendedorService->getAll($perPage);
        
        // Cargar sliders para cada emprendedor
        foreach ($emprendedores as $emprendedor) {
            $emprendedor->load(['slidersPrincipales', 'slidersSecundarios']);
        }

        return response()->json([
            'success' => true,
            'data' => $emprendedores
        ]);
    }

    /**
     * Almacenar un nuevo emprendedor
     */
    public function store(EmprendedorRequest $request)
    {
        try{
            $data = $request->validated();
            
            // Crear el emprendedor
            $resultado = $this->emprendedorService->create($data);
            
            // Si hay un usuario autenticado, asignarlo como administrador principal
            if (Auth::check()) {
                $user = Auth::user();
                $resultado->administradores()->attach($user->id, [
                    'es_principal' => true,
                    'rol' => 'administrador'
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Emprendedor creado exitosamente',
                'data' => $resultado
            ], Response::HTTP_CREATED);
        }catch(\Exception $e){
            Log::error('Error al crear emprendedor: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id): JsonResponse
    {
        // Convertir explícitamente a entero
        $id = (int) $id;
        
        $emprendedor = $this->emprendedorService->getById($id);
    
        if (!$emprendedor) {
            return response()->json([
                'success' => false,
                'message' => 'Emprendedor no encontrado'
            ], 404);
        }
        
        // Cargar relaciones
        $emprendedor->load([
            'slidersPrincipales', 
            'slidersSecundarios',
            'servicios.horarios',
            'servicios.sliders',
            'asociacion',
            'administradores' // Cargar los administradores
        ]);

        return response()->json([
            'success' => true,
            'data' => $emprendedor
            
        ]);
    }

    /**
     * Actualizar un emprendedor
     */
    /**
     * Actualizar un emprendedor
     */
    public function update(EmprendedorRequest $request, $id): JsonResponse
    {
        try {
            // Convertir ID a entero
            $id = (int) $id;
            
            // Verificar si el usuario tiene permisos para actualizar este emprendedor
            if (Auth::check() && !Auth::user()->hasPermissionTo('emprendedor_update')) {
                // Si no tiene el permiso general, verificar si es administrador de este emprendimiento
                $user = Auth::user();
                $esAdministrador = $user->emprendimientos()
                    ->where('emprendedores.id', $id)
                    ->exists();
                
                if (!$esAdministrador) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes permisos para actualizar este emprendedor'
                    ], Response::HTTP_FORBIDDEN);
                }
            }
            
            // Los datos ya están validados por el Request
            $datos = $request->validated();
            
            // Usar el servicio para actualizar el registro
            $resultado = $this->emprendedorService->update($id, $datos);
            
            if (!$resultado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendedor no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Emprendedor actualizado exitosamente',
                'data' => $resultado
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error('Error al actualizar emprendedor: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar un emprendedor
     */
    public function destroy($id): JsonResponse
    {
        try {
            // Convertir ID a entero
            $id = (int) $id;
            
            // Verificar si el usuario tiene permisos para eliminar este emprendedor
            if (Auth::check() && !Auth::user()->hasPermissionTo('emprendedor_delete')) {
                // Si no tiene el permiso general, verificar si es administrador principal de este emprendimiento
                $user = Auth::user();
                $esAdministradorPrincipal = $user->emprendimientos()
                    ->where('emprendedores.id', $id)
                    ->wherePivot('es_principal', true)
                    ->exists();
                
                if (!$esAdministradorPrincipal) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes permisos para eliminar este emprendedor'
                    ], Response::HTTP_FORBIDDEN);
                }
            }
            
            $deleted = $this->emprendedorService->delete($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendedor no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Emprendedor eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar emprendedor: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Buscar emprendedores por categoría
     */
    public function byCategory(string $categoria): JsonResponse
    {
        $emprendedores = $this->emprendedorService->findByCategory($categoria);

        return response()->json([
            'success' => true,
            'data' => $emprendedores
        ]);
    }

    /**
     * Buscar emprendedores por asociación
     */
    public function byAsociacion(int $asociacionId): JsonResponse
    {
        $emprendedores = $this->emprendedorService->findByAsociacion($asociacionId);

        return response()->json([
            'success' => true,
            'data' => $emprendedores
        ]);
    }

    /**
     * Buscar emprendedores
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q');

        if (!$query) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetro de búsqueda requerido'
            ], 400);
        }

        $emprendedores = $this->emprendedorService->search($query);

        return response()->json([
            'success' => true,
            'data' => $emprendedores
        ]);
    }

    /**
     * Obtener servicios de un emprendedor
     */
    public function getServicios($id): JsonResponse
    {
        // Convertir ID a entero
        $id = (int) $id;
        
        $emprendedor = $this->emprendedorService->getById($id);
        
        if (!$emprendedor) {
            return response()->json([
                'success' => false,
                'message' => 'Emprendedor no encontrado'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $emprendedor->servicios()->with('categorias')->get()
        ]);
    }

    /**
     * Obtener reservas de un emprendedor
     */
    public function getReservas($id): JsonResponse
    {
        // Convertir ID a entero
        $id = (int) $id;
        
        $emprendedor = $this->emprendedorService->getById($id);
        
        if (!$emprendedor) {
            return response()->json([
                'success' => false,
                'message' => 'Emprendedor no encontrado'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $emprendedor->reservas()->with('user')->get()
        ]);
    }
    
    /**
     * Obtener emprendedor con todas sus relaciones
     */
    public function getWithRelations($id): JsonResponse
    {
        try {
            // Convertir ID a entero
            $id = (int) $id;
            
            $emprendedor = $this->emprendedorService->getWithRelations($id);
            
            if (!$emprendedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendedor no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'data' => $emprendedor
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Error al obtener emprendedor con relaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Agregar un administrador a un emprendimiento
     */
    public function agregarAdministrador(Request $request, $id): JsonResponse
    {
        try {
            // Convertir ID a entero
            $id = (int) $id;
            
            // Verificar si el usuario tiene permisos para agregar administradores
            if (Auth::check() && !Auth::user()->hasPermissionTo('emprendedor_update')) {
                // Si no tiene el permiso general, verificar si es administrador principal
                $user = Auth::user();
                $esAdministradorPrincipal = $user->emprendimientos()
                    ->where('emprendedores.id', $id)
                    ->wherePivot('es_principal', true)
                    ->exists();
                
                if (!$esAdministradorPrincipal) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes permisos para agregar administradores'
                    ], Response::HTTP_FORBIDDEN);
                }
            }
            
            $emprendedor = $this->emprendedorService->getById($id);
            
            if (!$emprendedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendedor no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'rol' => 'required|in:administrador,colaborador',
                'es_principal' => 'boolean',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Obtener el usuario a agregar como administrador
            $nuevoAdmin = User::where('email', $request->email)->first();
            
            // Verificar si ya es administrador de este emprendimiento
            if ($emprendedor->administradores()->where('users.id', $nuevoAdmin->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este usuario ya es administrador de este emprendimiento'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Si se está intentando agregar como principal, verificar que no haya otro principal
            if ($request->es_principal && $emprendedor->administradores()->wherePivot('es_principal', true)->exists()) {
                // Cambiar el actual principal a no principal
                $actualPrincipal = $emprendedor->administradores()->wherePivot('es_principal', true)->first();
                $emprendedor->administradores()->updateExistingPivot(
                    $actualPrincipal->id,
                    ['es_principal' => false]
                );
            }
            
            // Agregar el nuevo administrador
            $emprendedor->administradores()->attach($nuevoAdmin->id, [
                'es_principal' => $request->es_principal ?? false,
                'rol' => $request->rol,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Administrador agregado correctamente',
                'data' => $nuevoAdmin
            ]);
        } catch (\Exception $e) {
            Log::error('Error al agregar administrador: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    
    /**
     * Eliminar un administrador de un emprendimiento
     */
    public function eliminarAdministrador(Request $request, $id, $userId): JsonResponse
    {
        try {
            // Convertir IDs a enteros
            $id = (int) $id;
            $userId = (int) $userId;
            
            // Verificar si el usuario tiene permisos
            if (Auth::check() && !Auth::user()->hasPermissionTo('emprendedor_update')) {
                // Si no tiene el permiso general, verificar si es administrador principal
                $user = Auth::user();
                $esAdministradorPrincipal = $user->emprendimientos()
                    ->where('emprendedores.id', $id)
                    ->wherePivot('es_principal', true)
                    ->exists();
                
                if (!$esAdministradorPrincipal) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes permisos para eliminar administradores'
                    ], Response::HTTP_FORBIDDEN);
                }
            }
            
            $emprendedor = $this->emprendedorService->getById($id);
            
            if (!$emprendedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emprendedor no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            // No permitir eliminar al administrador principal si es el único
            $adminAEliminar = $emprendedor->administradores()->where('users.id', $userId)->first();
            
            if (!$adminAEliminar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado en este emprendimiento'
                ], Response::HTTP_NOT_FOUND);
            }
            
            if ($adminAEliminar->pivot->es_principal && $emprendedor->administradores()->count() === 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes eliminar al único administrador del emprendimiento'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Si es el administrador principal pero hay más administradores, hay que designar otro
            if ($adminAEliminar->pivot->es_principal && $emprendedor->administradores()->count() > 1) {
                // Buscar otro administrador para hacerlo principal
                $otroAdmin = $emprendedor->administradores()
                    ->where('users.id', '!=', $userId)
                    ->first();
                
                if ($otroAdmin) {
                    $emprendedor->administradores()->updateExistingPivot(
                        $otroAdmin->id,
                        ['es_principal' => true]
                    );
                }
            }
            
            // Eliminar el administrador
            $emprendedor->administradores()->detach($userId);
            
            return response()->json([
                'success' => true,
                'message' => 'Administrador eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar administrador: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
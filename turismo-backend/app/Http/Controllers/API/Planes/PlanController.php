<?php

namespace App\Http\Controllers\API\Planes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\Servicio;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    /**
     * Obtener todos los planes (para administradores)
     */
    public function index(): JsonResponse
    {
        // Si es admin, mostrar todos los planes, si no, solo los públicos o creados por el usuario
        $planes = Auth::user()->hasRole('admin') 
                ? Plan::with(['creadoPor', 'services'])->paginate(15)
                : Plan::where('es_publico', true)
                    ->orWhere('creado_por_usuario_id', Auth::id())
                    ->with(['creadoPor', 'services'])
                    ->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $planes
        ]);
    }
    
    /**
     * Obtener planes públicos (para usuarios)
     */
    public function getPublicPlanes(): JsonResponse
    {
        $planes = Plan::where('es_publico', true)
                    ->where('estado', Plan::ESTADO_ACTIVO)
                    ->with(['services'])
                    ->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $planes
        ]);
    }
    
    /**
     * Mostrar detalles de un plan
     */
    public function show(int $id): JsonResponse
    {
        $plan = Plan::with(['creadoPor', 'services', 'inscripciones.usuario'])->find($id);
        
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan no encontrado'
            ], Response::HTTP_NOT_FOUND);
        }
        
        // Verificar permisos - solo el creador o administrador puede ver detalles completos
        $showFullDetails = Auth::user()->hasRole('admin') || $plan->creado_por_usuario_id === Auth::id();
        
        if (!$showFullDetails && !$plan->es_publico) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para ver este plan'
            ], Response::HTTP_FORBIDDEN);
        }
        
        $data = $plan->toArray();
        
        // Filtrar datos sensibles si no es administrador o creador
        if (!$showFullDetails) {
            unset($data['inscripciones']);
            $data['cupos_disponibles'] = $plan->cupos_disponibles;
        }
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
    
    /**
     * Crear un nuevo plan (solo administradores o roles con permiso)
     */
    public function store(Request $request): JsonResponse
    {
        // Validación
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'capacidad' => 'required|integer|min:1',
            'es_publico' => 'boolean',
            'services' => 'required|array|min:1',
            'services.*.service_id' => 'required|exists:servicios,id',
            'services.*.fecha_inicio' => 'required|date_format:Y-m-d',
            'services.*.fecha_fin' => 'nullable|date_format:Y-m-d|after_or_equal:services.*.fecha_inicio',
            'services.*.hora_inicio' => 'required|date_format:H:i:s',
            'services.*.hora_fin' => 'required|date_format:H:i:s|after:services.*.hora_inicio',
            'services.*.duracion_minutos' => 'required|integer|min:1',
            'services.*.notas' => 'nullable|string',
            'services.*.orden' => 'nullable|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        try {
            // Crear el plan
            $plan = Plan::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'capacidad' => $request->capacidad,
                'es_publico' => $request->es_publico ?? true,
                'estado' => Plan::ESTADO_ACTIVO,
                'creado_por_usuario_id' => Auth::id(),
            ]);
            
            // Añadir servicios al plan
            foreach ($request->services as $index => $servicioData) {
                $servicio = Servicio::find($servicioData['service_id']);
                
                if ($servicio) {
                    $plan->services()->attach($servicio->id, [
                        'fecha_inicio' => $servicioData['fecha_inicio'],
                        'fecha_fin' => $servicioData['fecha_fin'] ?? null,
                        'hora_inicio' => $servicioData['hora_inicio'],
                        'hora_fin' => $servicioData['hora_fin'],
                        'duracion_minutos' => $servicioData['duracion_minutos'],
                        'notas' => $servicioData['notas'] ?? null,
                        'orden' => $servicioData['orden'] ?? $index,
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $plan->load('services'),
                'message' => 'Plan creado exitosamente'
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el plan: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Actualizar un plan existente
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $plan = Plan::find($id);
        
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan no encontrado'
            ], Response::HTTP_NOT_FOUND);
        }
        
        // Verificar permisos
        if (!Auth::user()->hasRole('admin') && $plan->creado_por_usuario_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para editar este plan'
            ], Response::HTTP_FORBIDDEN);
        }
        
        // Validación
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'capacidad' => 'sometimes|integer|min:1',
            'es_publico' => 'boolean',
            'estado' => 'sometimes|in:activo,inactivo',
            'services' => 'sometimes|array',
            'services.*.id' => 'nullable|integer',
            'services.*.service_id' => 'required|exists:servicios,id',
            'services.*.fecha_inicio' => 'required|date_format:Y-m-d',
            'services.*.fecha_fin' => 'nullable|date_format:Y-m-d|after_or_equal:services.*.fecha_inicio',
            'services.*.hora_inicio' => 'required|date_format:H:i:s',
            'services.*.hora_fin' => 'required|date_format:H:i:s|after:services.*.hora_inicio',
            'services.*.duracion_minutos' => 'required|integer|min:1',
            'services.*.notas' => 'nullable|string',
            'services.*.orden' => 'nullable|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        try {
            // Actualizar los datos del plan
            $plan->fill($request->only([
                'nombre', 'descripcion', 'capacidad', 'es_publico', 'estado'
            ]));
            
            $plan->save();
            
            // Actualizar los servicios del plan si se proporcionan
            if ($request->has('services')) {
                // Desasociar todos los servicios actuales
                $plan->services()->detach();
                
                // Asociar los nuevos servicios
                foreach ($request->services as $index => $servicioData) {
                    $servicio = Servicio::find($servicioData['service_id']);
                    
                    if ($servicio) {
                        $plan->services()->attach($servicio->id, [
                            'fecha_inicio' => $servicioData['fecha_inicio'],
                            'fecha_fin' => $servicioData['fecha_fin'] ?? null,
                            'hora_inicio' => $servicioData['hora_inicio'],
                            'hora_fin' => $servicioData['hora_fin'],
                            'duracion_minutos' => $servicioData['duracion_minutos'],
                            'notas' => $servicioData['notas'] ?? null,
                            'orden' => $servicioData['orden'] ?? $index,
                        ]);
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $plan->load('services'),
                'message' => 'Plan actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el plan: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Eliminar un plan
     */
    public function destroy(int $id): JsonResponse
    {
        $plan = Plan::find($id);
        
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan no encontrado'
            ], Response::HTTP_NOT_FOUND);
        }
        
        // Verificar permisos
        if (!Auth::user()->hasRole('admin') && $plan->creado_por_usuario_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar este plan'
            ], Response::HTTP_FORBIDDEN);
        }
        
        try {
            // Eliminar plan y sus relaciones
            $plan->services()->detach();
            $plan->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Plan eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el plan: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
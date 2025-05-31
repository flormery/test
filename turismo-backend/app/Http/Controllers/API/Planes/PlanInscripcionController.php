<?php

namespace App\Http\Controllers\API\Planes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\PlanInscripcion;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;

class PlanInscripcionController extends Controller
{
    /**
     * Obtener planes inscritos del usuario autenticado
     */
    public function misPlanes(): JsonResponse
    {
        $inscripciones = Auth::user()->planInscripciones()
                              ->with('plan.services')
                              ->get();
        
        return response()->json([
            'success' => true,
            'data' => $inscripciones
        ]);
    }
    
    /**
     * Inscribirse a un plan
     */
    public function inscribirse(int $planId): JsonResponse
    {
        $plan = Plan::find($planId);
        
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan no encontrado'
            ], Response::HTTP_NOT_FOUND);
        }
        
        // Verificar si el plan está activo y es público
        if ($plan->estado !== Plan::ESTADO_ACTIVO) {
            return response()->json([
                'success' => false,
                'message' => 'El plan no está activo'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        if (!$plan->es_publico && $plan->creado_por_usuario_id !== Auth::id() && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a este plan'
            ], Response::HTTP_FORBIDDEN);
        }
        
        // Verificar si ya está inscrito
        $inscripcionExistente = PlanInscripcion::where('plan_id', $plan->id)
                                              ->where('usuario_id', Auth::id())
                                              ->whereIn('estado', [PlanInscripcion::ESTADO_PENDIENTE, PlanInscripcion::ESTADO_CONFIRMADA])
                                              ->first();
        
        if ($inscripcionExistente) {
            return response()->json([
                'success' => false,
                'message' => 'Ya estás inscrito a este plan'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Verificar disponibilidad de cupos
        if (!$plan->tieneCuposDisponibles()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay cupos disponibles para este plan'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            // Crear inscripción
            $inscripcion = PlanInscripcion::create([
                'plan_id' => $plan->id,
                'usuario_id' => Auth::id(),
                'estado' => PlanInscripcion::ESTADO_PENDIENTE,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $inscripcion->load('plan'),
                'message' => 'Te has inscrito exitosamente al plan'
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al inscribirse al plan: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Cancelar inscripción a un plan
     */
    public function cancelarInscripcion(int $inscripcionId): JsonResponse
    {
        $inscripcion = PlanInscripcion::where('id', $inscripcionId)
                                    ->where('usuario_id', Auth::id())
                                    ->first();
        
        if (!$inscripcion) {
            return response()->json([
                'success' => false,
                'message' => 'Inscripción no encontrada'
            ], Response::HTTP_NOT_FOUND);
        }
        
        try {
            $inscripcion->update(['estado' => PlanInscripcion::ESTADO_CANCELADA]);
            
            return response()->json([
                'success' => true,
                'message' => 'Inscripción cancelada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la inscripción: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Confirmar inscripción (solo para administradores o creadores del plan)
     */
    public function confirmarInscripcion(int $inscripcionId): JsonResponse
    {
        $inscripcion = PlanInscripcion::with('plan')->find($inscripcionId);
        
        if (!$inscripcion) {
            return response()->json([
                'success' => false,
                'message' => 'Inscripción no encontrada'
            ], Response::HTTP_NOT_FOUND);
        }
        
        // Verificar permisos
        if (!Auth::user()->hasRole('admin') && $inscripcion->plan->creado_por_usuario_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para confirmar esta inscripción'
            ], Response::HTTP_FORBIDDEN);
        }
        
        try {
            $inscripcion->update(['estado' => PlanInscripcion::ESTADO_CONFIRMADA]);
            
            return response()->json([
                'success' => true,
                'message' => 'Inscripción confirmada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar la inscripción: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Generar reservas a partir de un plan inscrito (crear las reservas reales)
     */
    public function generarReservas(int $inscripcionId): JsonResponse
    {
        $inscripcion = PlanInscripcion::with(['plan.services', 'usuario'])->find($inscripcionId);
        
        if (!$inscripcion) {
            return response()->json([
                'success' => false,
                'message' => 'Inscripción no encontrada'
            ], Response::HTTP_NOT_FOUND);
        }
        
        // Verificar si la inscripción está confirmada
        if ($inscripcion->estado !== PlanInscripcion::ESTADO_CONFIRMADA) {
            return response()->json([
                'success' => false,
                'message' => 'La inscripción no está confirmada'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            // Crear la reserva para el usuario
            $reservaData = [
                'usuario_id' => $inscripcion->usuario_id,
                'codigo_reserva' => \App\Models\Reserva::generarCodigoReserva(),
                'estado' => \App\Models\Reserva::ESTADO_PENDIENTE,
                'notas' => 'Generado desde plan: ' . $inscripcion->plan->nombre,
            ];
            
            $serviciosData = [];
            
            // Preparar datos de servicios desde el plan
            foreach ($inscripcion->plan->services as $servicio) {
                $serviciosData[] = [
                    'service_id' => $servicio->id,
                    'emprendedor_id' => $servicio->emprendedor_id,
                    'fecha_inicio' => $servicio->pivot->fecha_inicio,
                    'fecha_fin' => $servicio->pivot->fecha_fin,
                    'hora_inicio' => $servicio->pivot->hora_inicio,
                    'hora_fin' => $servicio->pivot->hora_fin,
                    'duracion_minutos' => $servicio->pivot->duracion_minutos,
                    'cantidad' => 1,
                    'precio' => $servicio->precio_referencial,
                    'estado' => \App\Models\ReservaServicio::ESTADO_PENDIENTE,
                    'notas_cliente' => $servicio->pivot->notas,
                ];
            }
            
            // Crear la reserva con sus servicios
            $reserva = app(\App\Repository\ReservaRepository::class)->create($reservaData, $serviciosData);
            
            return response()->json([
                'success' => true,
                'data' => $reserva,
                'message' => 'Reservas generadas exitosamente desde el plan'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reservas: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
<?php

namespace App\Http\Controllers\API\Evento;

use App\Http\Controllers\Controller;
use App\Repository\EventoRepository;
use App\Http\Requests\EventoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    protected $eventoRepository;

    public function __construct(EventoRepository $eventoRepository)
    {
        $this->eventoRepository = $eventoRepository;
    }

    public function index(): JsonResponse
    {
        try {
            $eventos = $this->eventoRepository->getPaginated();
            
            return response()->json([
                'success' => true,
                'data' => $eventos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los eventos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $evento = $this->eventoRepository->getById($id);
            
            if (!$evento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Evento no encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $evento
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el evento: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(EventoRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Procesar los sliders para manejar las imágenes binarias
            if (isset($data['sliders']) && is_array($data['sliders'])) {
                foreach ($data['sliders'] as $key => $slider) {
                    if (isset($slider['imagen']) && $request->hasFile("sliders.{$key}.imagen")) {
                        $path = $request->file("sliders.{$key}.imagen")->store('sliders', 'public');
                        $data['sliders'][$key]['url'] = $path;
                        unset($data['sliders'][$key]['imagen']);
                    }
                    
                    // Asignar valores por defecto a los sliders
                    $data['sliders'][$key]['tipo_entidad'] = 'evento';
                    $data['sliders'][$key]['activo'] = $data['sliders'][$key]['activo'] ?? true;
                    $data['sliders'][$key]['orden'] = $data['sliders'][$key]['orden'] ?? ($key + 1);
                    // Añadir el campo es_principal ya que el SliderRepository lo requiere
                    $data['sliders'][$key]['es_principal'] = true;
                }
            }
            
            $evento = $this->eventoRepository->create($data);
            
            return response()->json([
                'success' => true,
                'data' => $evento,
                'message' => 'Evento creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el evento: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nombre' => 'sometimes|string|max:255',
                'descripcion' => 'sometimes|string',
                'tipo_evento' => 'sometimes|string|max:100',
                'idioma_principal' => 'sometimes|string|max:50',
                'fecha_inicio' => 'sometimes|date',
                'hora_inicio' => 'sometimes',
                'fecha_fin' => 'sometimes|date',
                'hora_fin' => 'sometimes',
                'duracion_horas' => 'sometimes|integer',
                'coordenada_x' => 'sometimes|numeric',
                'coordenada_y' => 'sometimes|numeric',
                'id_emprendedor' => 'sometimes|exists:emprendedores,id',
                'que_llevar' => 'nullable|string',
                'sliders' => 'sometimes|array',
                'sliders.*.id' => 'sometimes|integer|exists:sliders,id',
                'sliders.*.url' => 'sometimes|string',
                'sliders.*.nombre' => 'sometimes|string|max:255',
                'sliders.*.orden' => 'sometimes|integer',
                'sliders.*.activo' => 'sometimes|boolean',
                'sliders.*.es_principal' => 'nullable',
                'sliders.*.imagen' => 'sometimes|file|image',
                'deleted_sliders' => 'sometimes|array',
                'deleted_sliders.*' => 'required|integer|exists:sliders,id',
            ]);
            
            // Procesar los sliders para manejar las imágenes binarias
            if (isset($validated['sliders']) && is_array($validated['sliders'])) {
                foreach ($validated['sliders'] as $key => $slider) {
                    if (isset($slider['imagen']) && $request->hasFile("sliders.{$key}.imagen")) {
                        $path = $request->file("sliders.{$key}.imagen")->store('sliders', 'public');
                        $validated['sliders'][$key]['url'] = $path;
                        unset($validated['sliders'][$key]['imagen']);
                    }
                    
                    // Asignar valores por defecto para los nuevos sliders
                    if (!isset($slider['id'])) {
                        $validated['sliders'][$key]['tipo_entidad'] = 'evento';
                        $validated['sliders'][$key]['entidad_id'] = $id;
                        $validated['sliders'][$key]['activo'] = $validated['sliders'][$key]['activo'] ?? true;
                        $validated['sliders'][$key]['orden'] = $validated['sliders'][$key]['orden'] ?? ($key + 1);
                        // Añadir el campo es_principal ya que el SliderRepository lo requiere
                        $validated['sliders'][$key]['es_principal'] = true;
                    }
                }
            }

            $evento = $this->eventoRepository->update($id, $validated);
            
            return response()->json([
                'success' => true,
                'data' => $evento,
                'message' => 'Evento actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el evento: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->eventoRepository->delete($id);
            
            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Evento no encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Evento eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el evento: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function byEmprendedor(int $emprendedorId): JsonResponse
    {
        try {
            $eventos = $this->eventoRepository->getEventosByEmprendedor($emprendedorId);
            
            return response()->json([
                'success' => true,
                'data' => $eventos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los eventos: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function eventosActivos(): JsonResponse
    {
        try {
            $eventos = $this->eventoRepository->getEventosActivos();
            
            return response()->json([
                'success' => true,
                'data' => $eventos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los eventos activos: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function proximosEventos(Request $request): JsonResponse
    {
        try {
            $limite = $request->query('limite', 5);
            $eventos = $this->eventoRepository->getProximosEventos($limite);
            
            return response()->json([
                'success' => true,
                'data' => $eventos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los próximos eventos: ' . $e->getMessage()
            ], 500);
        }
    }
}
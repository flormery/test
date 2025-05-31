<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class EmprendedorRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Convertir metodos_pago que viene como string JSON a array
        if ($this->has('metodos_pago') && is_string($this->metodos_pago)) {
            if (is_array(json_decode($this->metodos_pago, true))) {
                $this->merge([
                    'metodos_pago' => json_decode($this->metodos_pago, true)
                ]);
            }
        }
        
        // Convertir idiomas_hablados que viene como string a array
        if ($this->has('idiomas_hablados') && is_string($this->idiomas_hablados)) {
            $idiomas = array_map('trim', explode(',', $this->idiomas_hablados));
            $this->merge([
                'idiomas_hablados' => $idiomas
            ]);
        }
        
        // Asegurar que facilidades_discapacidad sea booleano
        if ($this->has('facilidades_discapacidad')) {
            $this->merge([
                'facilidades_discapacidad' => filter_var($this->facilidades_discapacidad, FILTER_VALIDATE_BOOLEAN)
            ]);
        }
        
        // Asegurar que estado sea booleano
        if ($this->has('estado')) {
            $this->merge([
                'estado' => filter_var($this->estado, FILTER_VALIDATE_BOOLEAN)
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Para crear un nuevo emprendedor, el usuario debe tener permiso
        if ($this->isMethod('POST')) {
            return Auth::check() && (
                Auth::user()->hasPermissionTo('emprendedor_create') || 
                Auth::user()->hasRole('admin')
            );
        }
        
        // Para actualizar un emprendedor existente
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            // Si tiene permiso general de actualización, está autorizado
            if (Auth::user()->hasPermissionTo('emprendedor_update') || Auth::user()->hasRole('admin')) {
                return true;
            }
            
            // Si no tiene permiso general, verificar si es administrador de este emprendimiento
            $emprendedorId = $this->route('id');
            return Auth::user()->emprendimientos()
                ->where('emprendedores.id', $emprendedorId)
                ->exists();
        }
        
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'nombre' => 'required|string|max:255',
            'tipo_servicio' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'ubicacion' => 'required|string|max:255',
            'telefono' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'pagina_web' => 'nullable|string|max:255',
            'horario_atencion' => 'nullable|string|max:255',
            'precio_rango' => 'nullable|string|max:100',
            'metodos_pago' => 'nullable|array',
            'capacidad_aforo' => 'nullable|integer|min:0',
            'numero_personas_atiende' => 'nullable|integer|min:0',
            'comentarios_resenas' => 'nullable|string',
            'imagenes' => 'nullable|array',
            'categoria' => 'required|string|max:100',
            'certificaciones' => 'nullable|array',
            'idiomas_hablados' => 'nullable|array',
            'opciones_acceso' => 'nullable|string',
            'facilidades_discapacidad' => 'nullable|boolean',
            'asociacion_id' => 'nullable|exists:asociaciones,id',
            'estado' => 'nullable|boolean',
            
            // Validaciones para sliders
            'sliders_principales' => 'nullable|array',
            'sliders_principales.*.id' => 'nullable|integer|exists:sliders,id',
            'sliders_principales.*.nombre' => 'required|string|max:255',
            'sliders_principales.*.orden' => 'required|integer|min:1',
            'sliders_principales.*.imagen' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:5120',
            
            'sliders_secundarios' => 'nullable|array',
            'sliders_secundarios.*.id' => 'nullable|integer|exists:sliders,id',
            'sliders_secundarios.*.nombre' => 'required|string|max:255',
            'sliders_secundarios.*.orden' => 'required|integer|min:1',
            'sliders_secundarios.*.titulo' => 'required|string|max:255',
            'sliders_secundarios.*.descripcion' => 'nullable|string',
            'sliders_secundarios.*.imagen' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:5120',
            
            'deleted_sliders' => 'nullable|array',
            'deleted_sliders.*' => 'integer|exists:sliders,id',
            
            // Administradores (solo para creación inicial)
            'administradores' => 'nullable|array',
            'administradores.*.user_id' => 'required|exists:users,id',
            'administradores.*.es_principal' => 'nullable|boolean',
            'administradores.*.rol' => 'required|in:administrador,colaborador',
        ];
        
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            foreach ($rules as $field => $rule) {
                // No aplicar 'sometimes' a reglas de arreglos anidados
                if (!strpos($field, '.*')) {
                    $rules[$field] = 'sometimes|' . $rule;
                }
            }
        }
        
        return $rules;
    }
    
    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del emprendimiento es obligatorio',
            'tipo_servicio.required' => 'El tipo de servicio es obligatorio',
            'descripcion.required' => 'La descripción es obligatoria',
            'ubicacion.required' => 'La ubicación es obligatoria',
            'telefono.required' => 'El teléfono es obligatorio',
            'email.required' => 'El correo electrónico es obligatorio',
            'email.email' => 'El correo electrónico debe ser una dirección válida',
            'categoria.required' => 'La categoría es obligatoria',
            'sliders_principales.*.nombre.required' => 'El nombre del slider principal es obligatorio',
            'sliders_principales.*.orden.required' => 'El orden del slider principal es obligatorio',
            'sliders_secundarios.*.nombre.required' => 'El nombre del slider secundario es obligatorio',
            'sliders_secundarios.*.orden.required' => 'El orden del slider secundario es obligatorio',
            'sliders_secundarios.*.titulo.required' => 'El título del slider secundario es obligatorio',
        ];
    }
    
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
    
    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'No estás autorizado para realizar esta acción'
            ], Response::HTTP_FORBIDDEN)
        );
    }
}
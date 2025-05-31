<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plan extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'nombre',
        'descripcion',
        'capacidad',
        'es_publico',
        'estado',
        'creado_por_usuario_id',
    ];
    
    protected $casts = [
        'es_publico' => 'boolean',
    ];
    
    // Estados del plan
    const ESTADO_ACTIVO = 'activo';
    const ESTADO_INACTIVO = 'inactivo';
    
    // El usuario que creó el plan
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_usuario_id');
    }
    
    // Los servicios incluidos en el plan
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'plan_services')
                    ->withPivot(['fecha_inicio', 'fecha_fin', 'hora_inicio', 'hora_fin', 'duracion_minutos', 'notas', 'orden'])
                    ->withTimestamps()
                    ->orderBy('pivot_orden');
    }
    
    // Las inscripciones de usuarios al plan
    public function inscripciones(): HasMany
    {
        return $this->hasMany(PlanInscripcion::class);
    }
    
    // Los usuarios inscritos al plan
    public function usuariosInscritos(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'plan_inscripciones')
                    ->withPivot(['estado', 'notas'])
                    ->withTimestamps();
    }
    
    // Verificar si el plan tiene cupos disponibles
    public function tieneCuposDisponibles(): bool
    {
        $inscripcionesConfirmadas = $this->inscripciones()
                                         ->where('estado', 'confirmada')
                                         ->count();
        
        return $inscripcionesConfirmadas < $this->capacidad;
    }
    
    // Obtener el número de cupos disponibles
    public function getCuposDisponiblesAttribute(): int
    {
        $inscripcionesConfirmadas = $this->inscripciones()
                                         ->where('estado', 'confirmada')
                                         ->count();
        
        return max(0, $this->capacidad - $inscripcionesConfirmadas);
    }
}
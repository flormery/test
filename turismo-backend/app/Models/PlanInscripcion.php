<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanInscripcion extends Model
{
    use HasFactory;
    
    protected $table = 'plan_inscripciones';
    
    protected $fillable = [
        'plan_id',
        'usuario_id',
        'estado',
        'notas',
    ];
    
    // Estados de la inscripción
    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_CONFIRMADA = 'confirmada';
    const ESTADO_CANCELADA = 'cancelada';
    
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
    
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
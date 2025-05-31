<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evento extends Model
{
    protected $table = 'eventos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo_evento',
        'idioma_principal',
        'fecha_inicio',
        'hora_inicio',
        'fecha_fin',
        'hora_fin',
        'duracion_horas',
        'coordenada_x',
        'coordenada_y',
        'id_emprendedor',
        'que_llevar',
    ];

    // Relación con emprendedor
    public function emprendedor()
    {
        return $this->belongsTo(Emprendedor::class, 'id_emprendedor');
    }

        
        

    public function sliders(): HasMany
    {
    return $this->hasMany(Slider::class, 'entidad_id')
                ->where('tipo_entidad', 'evento')
                ->orderBy('orden');
    }

        
}

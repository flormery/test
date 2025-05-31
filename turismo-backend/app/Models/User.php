<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'active',
        'foto_perfil',
        'google_id',
        'avatar',
        'country',
        'birth_date',
        'address',
        'gender',
        'preferred_language',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'active' => 'boolean',
        'birth_date' => 'date', // Cast birth_date to date
        'last_login' => 'datetime', // Cast last_login to datetime
    ];
    
    protected $appends = [
        'foto_perfil_url',
    ];
    
    /**
     * Obtener los emprendimientos administrados por el usuario
     */
    public function emprendimientos()
    {
        return $this->belongsToMany(Emprendedor::class, 'user_emprendedor')
                    ->withPivot('es_principal', 'rol')
                    ->withTimestamps();
    }
    public function emprendedores()
    {
        return $this->emprendimientos();
    }

    /**
     * Verificar si el usuario administra algún emprendimiento
     */
    public function administraEmprendimientos()
    {
        return $this->emprendimientos()->exists();
    }
    
    /**
     * Obtener la URL completa de la foto de perfil
     */
    public function getFotoPerfilUrlAttribute()
    {
        if (!$this->foto_perfil && !$this->avatar) {
            return null;
        }
        
        // Priorizar la foto de perfil cargada sobre el avatar de Google
        if ($this->foto_perfil) {
            if (filter_var($this->foto_perfil, FILTER_VALIDATE_URL)) {
                return $this->foto_perfil;
            }
            
            return url(Storage::url($this->foto_perfil));
        }
        
        // Si no hay foto de perfil pero hay avatar de Google, usar ese
        return $this->avatar;
    }
    
    /**
     * Verificar si el usuario se registró mediante Google
     */
    public function registeredWithGoogle()
    {
        return $this->google_id !== null;
    }
    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification);
    }
}
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'is_super_admin',
        'telegram_user_id',
        'telegram_link_code',
        'telegram_link_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'telegram_link_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'password'                 => 'hashed',
            'is_super_admin'           => 'boolean',
            'telegram_link_expires_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'status', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function groups()
    {
        return $this->hasMany(TelegramGroup::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function borrowers()
    {
        return $this->hasMany(Borrower::class);
    }

    public function botToken()
    {
        return $this->hasOne(BotToken::class);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}

<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class BotToken extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'token',
        'bot_username',
        'bot_name',
        'webhook_set',
        'webhook_url',
        'webhook_token',
    ];

    protected $hidden = ['token'];

    public function setTokenAttribute(string $value): void
    {
        $this->attributes['token'] = encrypt($value);
    }

    public function getTokenAttribute(string $value): string
    {
        return decrypt($value);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}

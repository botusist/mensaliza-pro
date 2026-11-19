<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppIntegration extends Model
{
    protected $table = 'whatsapp_integrations';

    protected $guarded = [];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'enabled' => 'boolean', 'last_tested_at' => 'datetime'];
    }
}

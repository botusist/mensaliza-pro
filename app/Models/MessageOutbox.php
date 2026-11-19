<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageOutbox extends Model
{
    protected $table = 'message_outbox';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class);
    }

    public function rule()
    {
        return $this->belongsTo(ReminderRule::class, 'reminder_rule_id');
    }
}

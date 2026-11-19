<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'competence' => 'date'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getPaidCentsAttribute(): int
    {
        return (int) $this->payments()->where('status', 'confirmed')->sum('amount_cents');
    }

    public function getBalanceCentsAttribute(): int
    {
        if ($this->status === 'cancelled') {
            return 0;
        }

        return max(0, $this->amount_cents - $this->paid_cents);
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'cancelled') {
            return 'Cancelada';
        }
        if ($this->balance_cents === 0) {
            return 'Pago';
        } if ($this->paid_cents > 0) {
            return $this->due_date->isPast() ? 'Parcial e vencido' : 'Pago parcialmente';
        }

        return $this->due_date->isPast() ? 'Vencido' : 'Em aberto';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pagamento extends Model
{
    protected $table = 'pagamentos';

    protected $fillable = [
        'emprestimo_id',
        'multa_id',
        'forma_pagamento',
        'valor_final',
        'status',
        'gateway_transaction_id',
    ];

    protected $casts = [
        'valor_final' => 'decimal:2',
    ];

    public function emprestimo(): BelongsTo
    {
        return $this->belongsTo(Emprestimo::class);
    }

    public function multa(): BelongsTo
    {
        return $this->belongsTo(Multa::class);
    }

    public function notificacoes(): HasMany
    {
        return $this->hasMany(Notificacao::class);
    }

    /**
     * Retorna a notificação mais recente do pagamento.
     */
    public function ultimaNotificacao(): ?Notificacao
    {
        return $this->notificacoes()->latest()->first();
    }

    public function isAprovado(): bool
    {
        return $this->status === 'aprovado';
    }
}
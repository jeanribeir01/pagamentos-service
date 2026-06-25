<?php

namespace App\Services;

use Illuminate\Support\Str;

class GatewayPagamentoService
{
    /**
     * Simula o processamento do pagamento.
     * Aprova 80% das transações, recusa 20%.
     */
    public function processar(float $valor, string $formaPagamento): array
    {
        $aprovado = (rand(1, 10) <= 8);

        return [
            'aprovado'       => $aprovado,
            'transaction_id' => $aprovado ? (string) Str::uuid() : null,
            'mensagem'       => $aprovado
                ? 'Pagamento aprovado pelo gateway.'
                : 'Pagamento recusado pela operadora.',
        ];
    }
}
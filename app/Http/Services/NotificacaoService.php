<?php

namespace App\Services;

use App\Models\Notificacao;
use App\Models\Pagamento;
use Exception;

class NotificacaoService
{
    public function enviarRecibo(Pagamento $pagamento): Notificacao
    {
        $pagamento->loadMissing('emprestimo');
        $usuarioId = $pagamento->emprestimo->usuario_id;

        try {
            $this->simularEnvio();

            return Notificacao::create([
                'pagamento_id' => $pagamento->id,
                'usuario_id'   => $usuarioId,
                'status'       => 'enviado',
            ]);
        } catch (Exception) {
            return Notificacao::create([
                'pagamento_id' => $pagamento->id,
                'usuario_id'   => $usuarioId,
                'status'       => 'falhou',
            ]);
        }
    }

    public function reenviarRecibo(Pagamento $pagamento): Notificacao
    {
        $notificacao = $pagamento->ultimaNotificacao();

        try {
            $this->simularEnvio();
            $notificacao->update(['status' => 'enviado']);
        } catch (Exception) {
            $notificacao->update(['status' => 'falhou']);
        }

        return $notificacao->fresh();
    }

    private function simularEnvio(): void
    {
        if (rand(1, 10) === 1) {
            throw new Exception('Serviço de notificação indisponível');
        }
    }
}

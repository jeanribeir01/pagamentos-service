<?php

namespace App\Http\Controllers;

use App\Models\Emprestimo;
use App\Models\Multa;
use App\Models\Pagamento;
use App\Services\GatewayPagamentoService;
use App\Services\NotificacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PagamentoController extends Controller
{
    public function __construct(
        private GatewayPagamentoService $gateway,
        private NotificacaoService $notificacaoService,
    ) {}

    public function index(): JsonResponse
    {
        $pagamentos = Pagamento::with(['emprestimo.usuario', 'multa', 'notificacoes'])
            ->latest()
            ->get();

        return response()->json($pagamentos);
    }

    public function historico(Request $request): JsonResponse
    {
        $request->validate([
            'usuario_id' => ['required', 'integer', 'exists:usuarios,id'],
        ]);

        $usuarioId = (int) $request->query('usuario_id');

        if (Auth::id() !== $usuarioId) {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        $pagamentos = Pagamento::with(['emprestimo', 'multa', 'notificacoes'])
            ->whereHas('emprestimo', fn($q) => $q->where('usuario_id', $usuarioId))
            ->latest()
            ->get();

        return response()->json($pagamentos);
    }

    public function show(int $id): JsonResponse
    {
        $pagamento = Pagamento::with(['emprestimo.usuario', 'multa', 'notificacoes'])
            ->findOrFail($id);

        if (Auth::id() !== $pagamento->emprestimo->usuario_id) {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        return response()->json($pagamento);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'emprestimo_id'   => ['required', 'integer', 'exists:emprestimos,id'],
            'multa_id'        => ['nullable', 'integer', 'exists:multas,id'],
            'forma_pagamento' => ['required', Rule::in(['dinheiro', 'cartao', 'pix', 'boleto'])],
        ]);

        $emprestimo = Emprestimo::findOrFail($data['emprestimo_id']);

        if (Auth::id() !== $emprestimo->usuario_id) {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        $valor = $this->calcularValor($emprestimo, $data['multa_id'] ?? null);

        if ($valor <= 0) {
            return response()->json(['message' => 'Não há valor a pagar.'], 422);
        }

        $resultado = $this->gateway->processar($valor, $data['forma_pagamento']);

        $pagamento = Pagamento::create([
            'emprestimo_id'          => $emprestimo->id,
            'multa_id'               => $data['multa_id'] ?? null,
            'forma_pagamento'        => $data['forma_pagamento'],
            'valor_final'            => $valor,
            'status'                 => $resultado['aprovado'] ? 'aprovado' : 'recusado',
            'gateway_transaction_id' => $resultado['transaction_id'],
        ]);

        $notificacao = null;
        if ($resultado['aprovado']) {
            if (!empty($data['multa_id'])) {
                Multa::where('id', $data['multa_id'])->update(['status' => 'paga']);
            }

            $notificacao = $this->notificacaoService->enviarRecibo($pagamento);
        }

        $statusHttp = $resultado['aprovado'] ? 201 : 422;

        return response()->json([
            'pagamento'   => $pagamento->load('notificacoes'),
            'notificacao' => $notificacao,
            'mensagem'    => $resultado['mensagem'],
        ], $statusHttp);
    }

    public function reenviarRecibo(int $id): JsonResponse
    {
        $pagamento = Pagamento::with(['emprestimo', 'notificacoes'])->findOrFail($id);

        if (Auth::id() !== $pagamento->emprestimo->usuario_id) {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        if (!$pagamento->isAprovado()) {
            return response()->json(['message' => 'Pagamento não foi aprovado; recibo indisponível.'], 422);
        }

        $ultima = $pagamento->ultimaNotificacao();

        if (!$ultima || !$ultima->falhou()) {
            return response()->json(['message' => 'Notificação já foi enviada com sucesso.'], 422);
        }

        $notificacao = $this->notificacaoService->reenviarRecibo($pagamento);

        return response()->json([
            'notificacao' => $notificacao,
            'mensagem'    => $notificacao->status === 'enviado'
                ? 'Recibo reenviado com sucesso.'
                : 'Falha ao reenviar o recibo. Tente novamente.',
        ]);
    }

    private function calcularValor(Emprestimo $emprestimo, ?int $multaId): float
    {
        $valorTotal = 0.0;

        if ($multaId) {
            $multa = Multa::find($multaId);
            if ($multa && $multa->status === 'pendente') {
                $valorTotal += (float) $multa->valor;
            }
        }

        if ($emprestimo->status === 'aberto' || $emprestimo->status === 'atrasado') {
            $dataAluguel = \Carbon\Carbon::parse($emprestimo->data_aluguel);
            
            $dataBaseDevolucao = $emprestimo->data_devolucao_real 
                ? \Carbon\Carbon::parse($emprestimo->data_devolucao_real) 
                : now();

            $dias = $dataAluguel->diffInDays($dataBaseDevolucao);
            $diasCobrados = $dias > 0 ? $dias : 1;

            $valorTotal += $diasCobrados * (float) $emprestimo->valor_diario;
        }

        return $valorTotal;
    }
}
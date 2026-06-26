<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emprestimo_id')->constrained('emprestimos');
            $table->foreignId('multa_id')->nullable()->constrained('multas');
            $table->enum('forma_pagamento', ['dinheiro', 'cartao', 'pix', 'boleto']);
            $table->decimal('valor_final', 8, 2);
            $table->enum('status', ['aprovado', 'recusado'])->default('recusado');
            $table->string('gateway_transaction_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};

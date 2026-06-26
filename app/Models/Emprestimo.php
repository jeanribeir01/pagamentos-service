<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Emprestimo extends Model
{
    protected $table = 'emprestimos';

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
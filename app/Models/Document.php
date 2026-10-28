<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $guarded = [];

    public const CATEGORIES = ['Nota fiscal', 'Boleto', 'Contrato', 'Alvará / Licença', 'Vigilância sanitária', 'Recibo', 'Folha de pagamento', 'Outros'];

    protected function casts(): array
    {
        return ['due_date' => DateOnly::class, 'amount' => 'float'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

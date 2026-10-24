<?php

namespace App\Events;

use App\Models\ConsultaAgendamento;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgendamentoConfirmado
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ConsultaAgendamento $agendamento) {}
}

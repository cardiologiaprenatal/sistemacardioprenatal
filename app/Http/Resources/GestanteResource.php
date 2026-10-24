<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GestanteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gestante_id' => $this->gestante_id,
            'nome' => $this->nome,
            'nome_exibicao' => $this->nome_exibicao,
            'data_nascimento' => $this->data_nascimento,
            'cpf' => $this->cpf,
            'cpf_formatado' => $this->cpf_formatado,
            'telefone' => $this->telefone,
            'telefone_formatado' => $this->telefone_formatado,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

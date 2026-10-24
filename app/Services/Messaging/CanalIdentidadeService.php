<?php

namespace App\Services\Messaging;

use App\Models\Gestante;
use App\Models\GestanteCanal;
use Illuminate\Support\Facades\Log;

class CanalIdentidadeService
{
    public function identificar(IncomingMessage $mensagem): GestanteCanal
    {
        $identidade = GestanteCanal::query()->firstOrCreate(
            [
                'canal' => $mensagem->canal,
                'external_user_id' => $mensagem->externalUserId,
            ],
            [
                'external_chat_id' => $mensagem->chatId,
                'username' => $mensagem->username,
                'display_name' => $mensagem->displayName,
            ],
        );

        $updates = [];
        if ($mensagem->chatId !== '' && $identidade->external_chat_id !== $mensagem->chatId) {
            $updates['external_chat_id'] = $mensagem->chatId;
        }
        if ($mensagem->username && $identidade->username !== $mensagem->username) {
            $updates['username'] = $mensagem->username;
        }
        if ($mensagem->displayName && $identidade->display_name !== $mensagem->displayName) {
            $updates['display_name'] = $mensagem->displayName;
        }
        if ($updates !== []) {
            $identidade->forceFill($updates)->save();
        }

        $identidade->loadMissing('gestante');

        Log::info('canal.user.identified', [
            'canal' => $identidade->canal,
            'identidade_id' => $identidade->id,
            'gestante_vinculada' => $identidade->gestante_id !== null,
        ]);

        return $identidade;
    }

    public function tentarVincularPorTelefone(GestanteCanal $identidade, string $texto): ?GestanteCanal
    {
        if ($identidade->gestante_id !== null) {
            return $identidade;
        }

        $digits = preg_replace('/\D/', '', $texto) ?: '';
        if (strlen($digits) < 10 || strlen($digits) > 16) {
            return null;
        }

        $gestante = $this->findGestanteByTelefone($digits);
        if ($gestante === null) {
            return null;
        }

        $identidade->forceFill(['gestante_id' => $gestante->id])->save();
        $identidade->setRelation('gestante', $gestante);

        Log::info('canal.user.linked', [
            'canal' => $identidade->canal,
            'identidade_id' => $identidade->id,
            'gestante_id' => $gestante->id,
        ]);

        return $identidade;
    }

    private function findGestanteByTelefone(string $normalized): ?Gestante
    {
        $candidates = [$normalized];

        if (strlen($normalized) > 11) {
            $candidates[] = substr($normalized, -11);
        }

        if (str_starts_with($normalized, '55') && strlen($normalized) >= 12) {
            $candidates[] = substr($normalized, 2);
        }

        if (strlen($normalized) === 11) {
            $candidates[] = '55'.$normalized;
        }

        $candidates = array_values(array_unique(array_filter($candidates)));

        return Gestante::query()->whereIn('telefone', $candidates)->first();
    }
}

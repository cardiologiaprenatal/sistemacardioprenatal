<?php

namespace App\Services;

use App\Exceptions\PythonApiException;
use App\Models\Consulta;
use App\Models\Gestante;
use Illuminate\Database\Eloquent\Builder;

/**
 * Análise ML sobre o cadastro local de gestantes.
 * A API Python só infere (POST /predict); não é a fonte da lista.
 */
class CohortMlService
{
    public function __construct(
        private PythonApiService $pythonApi,
        private PythonPredictPayloadMapper $mapper,
    ) {}

    /**
     * @return array{total: int, offset: int, limit: int, dataset: string|null, gestantes: list<array<string, mixed>>, fonte: string}
     */
    public function listar(int $offset, int $limit, ?string $busca = null): array
    {
        return $this->listarLocal($offset, $limit, $busca);
    }

    /**
     * @return array{gestante: array<string, mixed>|null, fonte: string|null, error: string|null, local: Gestante|null}
     */
    public function detalhe(string $id): array
    {
        $local = $this->encontrarLocal($id);
        if ($local) {
            return [
                'gestante' => $this->toDetalhe($local),
                'fonte' => 'mysql',
                'error' => null,
                'local' => $local,
            ];
        }

        try {
            return [
                'gestante' => $this->pythonApi->getGestante($id),
                'fonte' => 'python',
                'error' => null,
                'local' => null,
            ];
        } catch (PythonApiException $e) {
            return [
                'gestante' => null,
                'fonte' => null,
                'error' => $e->getMessage(),
                'local' => null,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function predizer(string $id): array
    {
        $local = $this->encontrarLocal($id);
        $consulta = $local?->consultas->sortByDesc('id')->first();
        if ($consulta instanceof Consulta) {
            return $this->pythonApi->predict($this->mapper->fromConsulta($consulta, $local));
        }

        if ($local) {
            throw new PythonApiException(
                'Cadastre uma consulta para esta gestante antes de rodar a predição.',
            );
        }

        return $this->pythonApi->predictById($id);
    }

    /**
     * @return array{total: int, offset: int, limit: int, dataset: string, gestantes: list<array<string, mixed>>, fonte: string}
     */
    private function listarLocal(int $offset, int $limit, ?string $busca = null): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(1000, $limit));
        $busca = trim((string) $busca);

        $query = Gestante::query()
            ->with(['consultas' => static fn ($q) => $q->orderByDesc('id')]);

        if ($busca !== '') {
            $this->aplicarBusca($query, $busca);
        }

        $total = (clone $query)->count();
        $gestantes = $query
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (Gestante $g) => $this->toRow($g))
            ->values()
            ->all();

        return [
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'dataset' => 'cadastro local',
            'fonte' => 'mysql',
            'gestantes' => $gestantes,
        ];
    }

    private function aplicarBusca(Builder $query, string $busca): void
    {
        $like = '%'.addcslashes($busca, '%_\\').'%';
        $digitos = preg_replace('/\D/', '', $busca) ?? '';

        $query->where(function ($inner) use ($like, $busca, $digitos) {
            $inner->where('nome', 'like', $like)
                ->orWhere('gestante_id', 'like', $like);

            if (ctype_digit($busca)) {
                $inner->orWhere('id', (int) $busca);
            }

            if ($digitos !== '') {
                $inner->orWhere('cpf', 'like', $digitos.'%')
                    ->orWhere('telefone', 'like', $digitos.'%');
            }
        });
    }

    private function encontrarLocal(string $id): ?Gestante
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $query = Gestante::query()
            ->with(['consultas' => static fn ($q) => $q->orderByDesc('id')])
            ->where('gestante_id', $id);

        if (ctype_digit($id)) {
            $query->orWhere('id', (int) $id);
        }

        return $query->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Gestante $gestante): array
    {
        $consulta = $gestante->consultas->sortByDesc('id')->first();
        $diabetes = $consulta && ($consulta->diabetes_pre_gestacional || $consulta->diabetes_gestacional);
        $hipertensao = $consulta && ($consulta->hipertensao || $consulta->hipertensao_cronica);

        return [
            'id_gestante' => (string) $gestante->gestante_id,
            'nome' => $gestante->nome_exibicao,
            'idade_materna' => $consulta?->idade_materna,
            'etnia' => $consulta?->etnia,
            'imc_pre_gestacional' => $consulta?->imc_pre_gestacional !== null
                ? (float) $consulta->imc_pre_gestacional
                : null,
            'idade_gestacional' => $consulta?->idade_gestacional,
            'diabetes' => $diabetes ? 1 : 0,
            'hipertensao' => $hipertensao ? 1 : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetalhe(Gestante $gestante): array
    {
        $consulta = $gestante->consultas->sortByDesc('id')->first();
        $features = $consulta instanceof Consulta
            ? $this->mapper->fromConsulta($consulta, $gestante)
            : [];

        return [
            'id_gestante' => (string) $gestante->gestante_id,
            'nome' => $gestante->nome_exibicao,
            'dados_clinicos' => $this->dadosClinicos($consulta),
            'features_modelo' => $features,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosClinicos(?Consulta $consulta): array
    {
        if (! $consulta) {
            return [];
        }

        return [
            'idade_materna' => $consulta->idade_materna,
            'etnia' => $consulta->etnia,
            'imc_pre_gestacional' => $consulta->imc_pre_gestacional !== null
                ? (float) $consulta->imc_pre_gestacional
                : null,
            'paridade' => $consulta->paridade,
            'idade_gestacional' => $consulta->idade_gestacional,
            'diabetes_pre_gestacional' => (int) $consulta->diabetes_pre_gestacional,
            'diabetes_gestacional' => (int) $consulta->diabetes_gestacional,
            'hipertensao' => (int) ($consulta->hipertensao || $consulta->hipertensao_cronica),
            'tabagismo' => (int) $consulta->tabagismo,
            'tipo_gestacao' => $consulta->tipo_gestacao,
        ];
    }
}

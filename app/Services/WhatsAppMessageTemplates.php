<?php

namespace App\Services;

use App\Models\Acompanhamento;
use App\Models\ConsultaAgendamento;
use App\Models\Gestante;

/**
 * Templates de texto livre personalizados por gestante (WAHA, sem template Meta).
 */
class WhatsAppMessageTemplates
{
    public const BOAS_VINDAS = 'boas_vindas';

    public const RISCO_ALTO = 'risco_alto';

    public const ECO_RECOMENDADA = 'eco_recomendada';

    public const RETORNO = 'retorno';

    public const MANUAL = 'manual';

    public const LEMBRETE_CONSULTA = 'lembrete_consulta';

    public const LEMBRETE_EXAME = 'lembrete_exame';

    public const CONFIRMACAO_CONTATO = 'confirmacao_contato';

    public const CONFIRMACAO_CONSULTA = 'confirmacao_consulta';

    public const ORIENTACAO_GERAL = 'orientacao_geral';

    public const ATENDENTE_HUMANO = 'atendente_humano';

    public const RETOMAR_ASSISTENTE = 'retomar_assistente';

    public const MENU_INICIAL = 'menu_inicial';

    /**
     * @return array<string, string>
     */
    public function catalog(): array
    {
        return [
            self::BOAS_VINDAS => 'Boas-vindas',
            self::MENU_INICIAL => 'Menu inicial (3 opções)',
            self::RISCO_ALTO => 'Acompanhamento: risco alto',
            self::ECO_RECOMENDADA => 'Acompanhamento: ecocardiograma recomendado',
            self::RETORNO => 'Lembrete de retorno',
            self::LEMBRETE_CONSULTA => 'Lembrete de consulta',
            self::CONFIRMACAO_CONSULTA => 'Confirmação de consulta',
            self::LEMBRETE_EXAME => 'Lembrete de exame',
            self::MANUAL => 'Acompanhamento: a equipe entrará em contato',
            self::CONFIRMACAO_CONTATO => 'Confirmação de contato',
            self::ORIENTACAO_GERAL => 'Orientação geral',
            self::ATENDENTE_HUMANO => 'Falar com atendente (pausa a resposta automática)',
            self::RETOMAR_ASSISTENTE => 'Retomar resposta automática',
        ];
    }

    /**
     * Catálogo ordenado conforme o contexto clínico da gestante (sugeridos primeiro).
     *
     * @return array{sugerido: string, templates: array<string, array{label: string, sugerido: bool}>}
     */
    public function catalogForGestante(Gestante $gestante): array
    {
        $sugerido = $this->suggestTemplateKey($gestante);
        $base = $this->catalog();
        $ordered = [];

        if (isset($base[$sugerido])) {
            $ordered[$sugerido] = [
                'label' => $base[$sugerido].' (sugerido)',
                'sugerido' => true,
            ];
        }

        foreach ($base as $key => $label) {
            if ($key === $sugerido) {
                continue;
            }
            $ordered[$key] = [
                'label' => $label,
                'sugerido' => false,
            ];
        }

        return [
            'sugerido' => $sugerido,
            'templates' => $ordered,
        ];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->catalog());
    }

    /**
     * Escolhe o template mais adequado ao quadro da gestante.
     */
    public function suggestTemplateKey(Gestante $gestante): string
    {
        $ctx = $this->contextFromGestante($gestante);

        if ($ctx['tem_risco_alto']) {
            return self::RISCO_ALTO;
        }

        if ($ctx['recomenda_eco']) {
            return self::ECO_RECOMENDADA;
        }

        if ($ctx['motivo_aberto'] === Acompanhamento::MOTIVO_RETORNO || $ctx['data_agendada']) {
            return self::RETORNO;
        }

        if ($ctx['motivo_aberto'] === Acompanhamento::MOTIVO_MANUAL) {
            return self::MANUAL;
        }

        if ($ctx['total_whatsapp'] === 0) {
            return self::MENU_INICIAL;
        }

        if ($ctx['ultima_consulta_data']) {
            return self::LEMBRETE_CONSULTA;
        }

        return self::ORIENTACAO_GERAL;
    }

    /**
     * Monta o texto do template com dados da gestante.
     */
    public function buildForGestante(string $templateKey, Gestante $gestante, ?string $prioridade = null, ?string $dataAgendada = null): string
    {
        $ctx = $this->contextFromGestante($gestante);

        if ($prioridade !== null && $prioridade !== '') {
            $ctx['prioridade_eco'] = $prioridade;
            $ctx['prioridade_label'] = $this->labelPrioridade($prioridade);
        }

        if ($dataAgendada !== null && $dataAgendada !== '') {
            $ctx['data_agendada'] = $dataAgendada;
        }

        return $this->buildFromContext($templateKey, $ctx);
    }

    public function textoConfirmacaoConsulta(Gestante $gestante, ConsultaAgendamento $agendamento): string
    {
        $ctx = $this->contextFromGestante($gestante);
        $ctx['data_agendada'] = $agendamento->dataFormatada().' às '.$agendamento->horarioFormatado();
        $ctx['profissional'] = $agendamento->profissional_nome;

        return $this->buildFromContext(self::CONFIRMACAO_CONSULTA, $ctx);
    }

    /**
     * Compatível com chamadas antigas (só nome).
     */
    public function build(string $templateKey, string $nome, ?string $prioridade = null, ?string $dataAgendada = null): string
    {
        return $this->buildFromContext($templateKey, [
            'nome' => trim($nome) !== '' ? trim($nome) : 'gestante',
            'primeiro_nome' => $this->primeiroNome($nome),
            'idade_gestacional' => null,
            'data_ultima_consulta' => null,
            'risco' => null,
            'tem_risco_alto' => false,
            'recomenda_eco' => false,
            'prioridade_eco' => $prioridade,
            'prioridade_label' => $this->labelPrioridade($prioridade),
            'data_agendada' => $dataAgendada,
            'motivo_aberto' => null,
            'total_whatsapp' => 0,
            'ultima_consulta_data' => null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function previewForGestante(Gestante $gestante): array
    {
        $out = [];
        foreach ($this->keys() as $key) {
            $out[$key] = $this->buildForGestante($key, $gestante);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function buildFromContext(string $templateKey, array $ctx): string
    {
        $nome = (string) ($ctx['primeiro_nome'] ?: $ctx['nome'] ?: 'gestante');

        return match ($templateKey) {
            self::BOAS_VINDAS => $this->boasVindas($nome, $ctx),
            self::MENU_INICIAL => $this->menuInicial($nome, $ctx),
            self::RISCO_ALTO, Acompanhamento::MOTIVO_RISCO_ALTO => $this->riscoAlto($nome, $ctx),
            self::ECO_RECOMENDADA, Acompanhamento::MOTIVO_ECO_RECOMENDADA => $this->ecoRecomendada($nome, $ctx),
            self::RETORNO, Acompanhamento::MOTIVO_RETORNO => $this->retorno($nome, $ctx),
            self::MANUAL, Acompanhamento::MOTIVO_MANUAL => $this->manual($nome, $ctx),
            self::LEMBRETE_CONSULTA => $this->lembreteConsulta($nome, $ctx),
            self::CONFIRMACAO_CONSULTA => $this->confirmacaoConsulta($nome, $ctx),
            self::LEMBRETE_EXAME => $this->lembreteExame($nome, $ctx),
            self::CONFIRMACAO_CONTATO => $this->confirmacaoContato($nome, $ctx),
            self::ORIENTACAO_GERAL => $this->orientacaoGeral($nome, $ctx),
            self::ATENDENTE_HUMANO => $this->atendenteHumano($nome, $ctx),
            self::RETOMAR_ASSISTENTE => $this->retomarAssistente($nome, $ctx),
            default => $this->orientacaoGeral($nome, $ctx),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function contextFromGestante(Gestante $gestante): array
    {
        $nomeCadastro = trim((string) ($gestante->nome ?? ''));
        $nomeCompleto = $nomeCadastro !== '' ? $nomeCadastro : 'gestante';
        $analise = $gestante->relationLoaded('ultimaAnalise')
            ? $gestante->ultimaAnalise
            : $gestante->ultimaAnalise()->first();

        $consulta = null;
        if ($gestante->relationLoaded('consultas')) {
            $consulta = $gestante->consultas->sortByDesc('consulta_numero')->first()
                ?? $gestante->consultas->sortByDesc('data_consulta')->first();
        } else {
            $consulta = $gestante->consultas()->orderByDesc('consulta_numero')->first();
        }

        $acompAberto = null;
        if ($gestante->relationLoaded('acompanhamentos')) {
            $acompAberto = $gestante->acompanhamentos->first(fn ($a) => $a->estaAberto());
        } else {
            $acompAberto = $gestante->acompanhamentos()->abertos()->orderByDesc('id')->first();
        }

        $risco = $analise?->classificacao_risco;
        $recomendaEco = (bool) ($analise?->recomenda_ecocardiograma);
        $prioridade = $analise?->prioridade_ecocardiograma;
        $ig = $consulta?->idade_gestacional;
        $dataConsulta = $consulta?->data_consulta
            ? $consulta->data_consulta->format('d/m/Y')
            : null;

        $dataAgendada = null;
        if ($acompAberto?->agendado_para) {
            $dataAgendada = $acompAberto->agendado_para
                ->timezone(config('app.timezone'))
                ->format('d/m/Y H:i');
        }

        $temRiscoAlto = $risco === 'alto'
            || ($acompAberto && $acompAberto->motivo === Acompanhamento::MOTIVO_RISCO_ALTO);

        if ($acompAberto && $acompAberto->motivo === Acompanhamento::MOTIVO_ECO_RECOMENDADA) {
            $recomendaEco = true;
        }

        $totalWhatsapp = $gestante->relationLoaded('gestanteWhatsapps')
            ? $gestante->gestanteWhatsapps->count()
            : $gestante->gestanteWhatsapps()->count();

        return [
            'nome' => $nomeCompleto,
            'nome_cadastro' => $nomeCadastro !== '',
            'primeiro_nome' => $nomeCadastro !== '' ? $this->primeiroNome($nomeCadastro) : '',
            'idade_gestacional' => $ig !== null && $ig !== '' ? (string) $ig : null,
            'data_ultima_consulta' => $dataConsulta,
            'pressao_sistolica' => $consulta?->pressao_sistolica,
            'bpm_materno' => $consulta?->bpm_materno,
            'peso' => $consulta?->peso,
            'saturacao' => $consulta?->saturacao,
            'risco' => $risco,
            'tem_risco_alto' => $temRiscoAlto,
            'recomenda_eco' => $recomendaEco,
            'prioridade_eco' => $prioridade,
            'prioridade_label' => $this->labelPrioridade($prioridade),
            'data_agendada' => $dataAgendada,
            'motivo_aberto' => $acompAberto?->motivo,
            'total_whatsapp' => $totalWhatsapp,
            'ultima_consulta_data' => $dataConsulta,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function boasVindas(string $nome, array $ctx = []): string
    {
        $ig = $this->fraseIdadeGestacional($ctx);

        return <<<TXT
Olá {$nome}{$ig}, seja bem-vinda ao Cardioprenatal. Rastreio de cardiopatias congênitas em gestantes diabéticas.

Posso consultar informações cadastradas e encaminhar dúvidas para a equipe.

Escreva por aqui quando precisar.
TXT;
    }

    /**
     * Menu de entrada: IA por padrão + 3 caminhos.
     * Botões nativos do WhatsApp têm no máximo 20 caracteres.
     *
     * @return list<array{id: string, text: string, opcao: string}>
     */
    public function menuBotoes(): array
    {
        return [
            ['id' => 'menu_atendente', 'text' => 'Falar com atendente', 'opcao' => 'atendente'],
            ['id' => 'menu_exames', 'text' => 'Exames e consultas', 'opcao' => 'exames'],
            ['id' => 'menu_medico', 'text' => 'Falar com o médico', 'opcao' => 'medico'],
        ];
    }

    /**
     * Lista nativa do WhatsApp: botão "Selecionar" abre as 3 opções.
     * Título de cada linha: no máximo 24 caracteres.
     *
     * @return array{
     *   title: string,
     *   description: string,
     *   footer: string,
     *   button: string,
     *   sections: list<array{title: string, rows: list<array{title: string, rowId: string, description: string}>}>
     * }
     */
    public function menuLista(string $nome = 'gestante'): array
    {
        $temNome = trim($nome) !== '' && $nome !== 'gestante';
        $descricao = $temNome
            ? "Olá, {$nome}, seja bem-vinda ao Cardioprenatal. Rastreio de cardiopatias congênitas em gestantes diabéticas.\n\nPosso consultar informações cadastradas e encaminhar dúvidas para a equipe."
            : "Olá, seja bem-vinda ao Cardioprenatal. Rastreio de cardiopatias congênitas em gestantes diabéticas.\n\nPosso consultar informações cadastradas e encaminhar dúvidas para a equipe.";

        return [
            'title' => 'Cardioprenatal',
            'description' => $descricao,
            'footer' => 'Cardioprenatal',
            'button' => 'Selecionar',
            'sections' => [
                [
                    'title' => 'Como continuar',
                    'rows' => [
                        [
                            'title' => 'Falar com atendente',
                            'rowId' => 'menu_atendente',
                            'description' => 'Um atendente assume esta conversa',
                        ],
                        [
                            'title' => 'Exames e consultas',
                            'rowId' => 'menu_exames',
                            'description' => 'Horários, exames e agendamentos',
                        ],
                        [
                            'title' => 'Falar com o médico',
                            'rowId' => 'menu_medico',
                            'description' => 'Encaminhamento para o médico',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function menuListaHistorico(string $nome = 'gestante'): string
    {
        $lista = $this->menuLista($nome);

        return $lista['description']."\n\n[Selecionar]\n• Falar com atendente\n• Exames e consultas\n• Falar com o médico";
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function menuInicialSaudacao(string $nome, array $ctx = []): string
    {
        $temNome = ($ctx['nome_cadastro'] ?? true) && trim($nome) !== '' && trim($nome) !== 'gestante';
        $ola = $temNome
            ? "Olá, {$nome}, seja bem-vinda ao Cardioprenatal. Rastreio de cardiopatias congênitas em gestantes diabéticas."
            : 'Olá, seja bem-vinda ao Cardioprenatal. Rastreio de cardiopatias congênitas em gestantes diabéticas.';

        return <<<TXT
{$ola}

Posso consultar informações cadastradas e encaminhar dúvidas para a equipe.
TXT;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function menuInicial(string $nome, array $ctx = []): string
    {
        $saudacao = $this->menuInicialSaudacao($nome, $ctx);
        $opcoes = $this->menuInicialNumerado();

        return $saudacao."\n\n".$opcoes;
    }

    public function menuInicialNumerado(): string
    {
        return <<<'TXT'
Se preferir, envie o número da opção:

1. Falar com atendente
2. Exames e consultas
3. Falar com o médico
TXT;
    }

    /**
     * Interpreta clique de botão, "1"/"2"/"3" ou o texto da opção.
     * Números só contam se a mensagem for curta (evita "1 semana" virar atendente).
     */
    public function matchMenuOpcao(?string $mensagem): ?string
    {
        $t = $this->normalizarTextoMenu($mensagem);

        if ($t === '') {
            return null;
        }

        $hits = 0;
        foreach ($this->menuBotoes() as $botao) {
            if (str_contains($t, $this->normalizarTextoMenu($botao['text']))) {
                $hits++;
            }
        }
        if ($hits > 1) {
            return null;
        }

        foreach ($this->menuBotoes() as $botao) {
            $norm = $this->normalizarTextoMenu($botao['text']);
            if ($t === $botao['id'] || $t === $norm) {
                return $botao['opcao'];
            }
        }

        if ($t === 'menu_atendente' || $t === 'atendente' || str_contains($t, 'falar com atendente') || str_contains($t, 'falar com a atendente')) {
            return 'atendente';
        }
        if ($t === 'menu_exames' || $t === 'exames' || str_contains($t, 'exames e consultas') || str_contains($t, 'informacoes sobre exames')) {
            return 'exames';
        }
        if ($t === 'menu_medico' || $t === 'medico' || str_contains($t, 'falar com o medico') || str_contains($t, 'falar com medico')) {
            return 'medico';
        }

        if (preg_match('/^(1|1️⃣|um)([.)\-\s]*)$/u', $t) || preg_match('/^(1|1️⃣)[.)\s]+falar com atendente/', $t)) {
            return 'atendente';
        }
        if (preg_match('/^(2|2️⃣|dois)([.)\-\s]*)$/u', $t) || preg_match('/^(2|2️⃣)[.)\s]+exames/', $t)) {
            return 'exames';
        }
        if (preg_match('/^(3|3️⃣|tres)([.)\-\s]*)$/u', $t) || preg_match('/^(3|3️⃣)[.)\s]+falar com/', $t)) {
            return 'medico';
        }

        return null;
    }

    /**
     * Intenção em linguagem natural (não é clique/número do menu).
     *
     * @return 'exames_dados'|'consultas_dados'|'compromissos'|'sintoma'|'interpretacao'|null
     */
    public function matchIntencaoLivre(?string $mensagem): ?string
    {
        $t = $this->normalizarTextoMenu($mensagem);
        if ($t === '' || $this->matchOpcaoNumero($t, 9) !== null) {
            return null;
        }

        if ($this->textoPedidoInterpretacao($t)) {
            return 'interpretacao';
        }
        if ($this->textoRelatoSintoma($t)) {
            return 'sintoma';
        }
        if ($this->textoConsultaExames($t)) {
            return 'exames_dados';
        }
        if ($this->textoConsultaConsultas($t)) {
            return 'consultas_dados';
        }
        if ($this->textoProximosCompromissos($t)) {
            return 'compromissos';
        }

        return null;
    }

    public function textoRelatoSintoma(string $t): bool
    {
        $termos = [
            'dor',
            'doendo',
            'sintoma',
            'sangramento',
            'sangue',
            'febre',
            'enjoo',
            'nausea',
            'vomito',
            'contracao',
            'mal estar',
            'estou sentindo',
            'to sentindo',
            'falta de ar',
            'tontura',
            'desmaio',
            'desconforto',
            'inchaco',
            'inchada',
            'inchado',
        ];

        foreach ($termos as $termo) {
            if (str_contains($t, $termo)) {
                return true;
            }
        }

        return false;
    }

    public function textoPedidoInterpretacao(string $t): bool
    {
        $termos = [
            'significa',
            'interpret',
            'diagnostico',
            'diagnosticar',
            'medicamento',
            'remedio',
            'tratamento',
            'posso tomar',
            'o que eu tomo',
            'e grave',
            'e normal',
            'exame deu',
        ];

        foreach ($termos as $termo) {
            if (str_contains($t, $termo)) {
                return true;
            }
        }

        return false;
    }

    public function textoConsultaExames(string $t): bool
    {
        if (! str_contains($t, 'exame')) {
            return false;
        }

        $pistas = [
            'ver',
            'quero',
            'resultado',
            'cadastrad',
            'dados',
            'mostrar',
            'consultar',
            'meu exame',
            'meus exames',
        ];

        foreach ($pistas as $pista) {
            if (str_contains($t, $pista)) {
                return true;
            }
        }

        return $t === 'exames';
    }

    public function textoConsultaConsultas(string $t): bool
    {
        return str_contains($t, 'minhas consultas')
            || str_contains($t, 'minha consulta')
            || str_contains($t, 'consultas cadastrad')
            || str_contains($t, 'ver consulta')
            || str_contains($t, 'dados da consulta')
            || str_contains($t, 'historico de consulta');
    }

    public function textoProximosCompromissos(string $t): bool
    {
        return str_contains($t, 'proximo compromisso')
            || str_contains($t, 'proximos compromissos')
            || str_contains($t, 'proxima consulta')
            || str_contains($t, 'proximas consultas')
            || str_contains($t, 'o que tenho agendado');
    }

    /**
     * Número 1..$max em mensagem curta ("1", "1.", "1️⃣").
     */
    public function matchOpcaoNumero(?string $mensagem, int $max = 9): ?int
    {
        $t = $this->normalizarTextoMenu($mensagem);
        if ($t === '') {
            return null;
        }

        $mapa = [
            '1' => 1, '1️⃣' => 1, 'um' => 1,
            '2' => 2, '2️⃣' => 2, 'dois' => 2,
            '3' => 3, '3️⃣' => 3, 'tres' => 3,
            '4' => 4, '4️⃣' => 4, 'quatro' => 4,
            '5' => 5, '5️⃣' => 5, 'cinco' => 5,
            '6' => 6, '6️⃣' => 6, 'seis' => 6,
            '7' => 7, '7️⃣' => 7, 'sete' => 7,
            '8' => 8, '8️⃣' => 8, 'oito' => 8,
            '9' => 9, '9️⃣' => 9, 'nove' => 9,
        ];

        if (preg_match('/^([1-9]|[1-9️⃣]|um|dois|tres|quatro|cinco|seis|sete|oito|nove)([.)\-\s]*)$/u', $t, $m)) {
            $n = $mapa[$m[1]] ?? (is_numeric($m[1]) ? (int) $m[1] : null);
            if ($n !== null && $n >= 1 && $n <= $max) {
                return $n;
            }
        }

        return null;
    }

    public function menuAtendente(): string
    {
        return <<<'TXT'
Entendi. Vou ajudar você a falar com um atendente.

Sobre o que você precisa de ajuda?

1. Dúvida sobre meu pré-natal
2. Exames
3. Consultas
4. Outro assunto

Escolha uma opção ou escreva sua dúvida.

0. Menu inicial
TXT;
    }

    public function menuExamesConsultas(?string $nome): string
    {
        $ola = $nome ? "Claro, {$nome}. O que você deseja consultar?" : 'Claro. O que você deseja consultar?';

        return <<<TXT
{$ola}

1. Meus exames
2. Minhas consultas
3. Próximos compromissos
4. Voltar ao menu inicial

0. Menu inicial
TXT;
    }

    public function atendimentoPersonalizadoNaoLiberado(): string
    {
        return <<<'TXT'
O atendimento personalizado pelo WhatsApp ainda não foi autorizado pelo médico.

Assim que a consulta for aceita, você poderá falar com a equipe por aqui.
TXT;
    }

    public function menuFalarComMedico(): string
    {
        return <<<'TXT'
Claro. Posso registrar sua mensagem para avaliação da equipe médica.

Escreva sua dúvida ou o que você gostaria de informar ao médico.

0. Menu inicial
TXT;
    }

    public function opcaoInvalida(): string
    {
        return 'Não consegui identificar essa opção. Por favor, escolha uma das opções disponíveis.';
    }

    public function solicitacaoAtendenteRegistrada(): string
    {
        return 'Obrigada! Registrei sua solicitação e ela será encaminhada para a equipe responsável.';
    }

    public function solicitacaoMedicoRegistrada(): string
    {
        return <<<'TXT'
Obrigada por enviar sua mensagem. Registrei sua solicitação para avaliação da equipe médica.

O assistente não substitui a avaliação de um profissional de saúde.

Se quiser acrescentar algum detalhe, pode escrever. Envie 0 para o menu inicial.
TXT;
    }

    public function solicitacaoDetalheRecebido(): string
    {
        return <<<'TXT'
Anotei o que você escreveu e mantive o encaminhamento para a equipe responsável.

Se quiser acrescentar mais alguma informação, pode escrever. Envie 0 para o menu inicial.
TXT;
    }

    public function respostaMedicaEspontanea(): string
    {
        return <<<'TXT'
Não posso avaliar sintomas, interpretar exames, indicar medicamentos ou fazer diagnóstico por aqui.

Obrigada! Registrei sua solicitação e ela será encaminhada para a equipe responsável.

O assistente não substitui a avaliação de um profissional de saúde.

Se quiser detalhar o que está sentindo, pode escrever. Envie 0 para o menu inicial.
TXT;
    }

    /**
     * Primeira resposta a um relato de sintoma: acolhe e pergunta, sem recusa defensiva.
     *
     * @param  array<string, mixed>  $ctx
     */
    public function respostaAcolhimentoSintoma(string $mensagem, array $ctx = []): string
    {
        $nome = trim((string) ($ctx['primeiro_nome'] ?? ''));
        $vocativo = $nome !== '' ? ', '.$nome : '';
        $queixa = $this->descreverQueixa($mensagem);

        $contexto = '';
        if (! empty($ctx['idade_gestacional'])) {
            $contexto = 'Vi no seu cadastro que você está com '.$ctx['idade_gestacional']." semanas de gestação.\n\n";
        } elseif (! empty($ctx['data_ultima_consulta'])) {
            $contexto = 'Vi que sua última consulta cadastrada foi em '.$ctx['data_ultima_consulta'].".\n\n";
        }

        $perguntaTempo = str_starts_with($queixa, 'dor') || $queixa === 'tontura' || $queixa === 'enjoo'
            ? "Há quanto tempo essa {$queixa} começou?"
            : 'Há quanto tempo isso começou?';

        return <<<TXT
Sinto muito que você esteja com {$queixa}{$vocativo}. 💙 Vamos entender um pouquinho melhor o que você está sentindo.

{$contexto}{$perguntaTempo} Ela está leve, moderada ou forte?

Pode contar com calma. Se preferir falar com a equipe do pré-natal, é só dizer. Envie 0 para voltar ao menu inicial.
TXT;
    }

    /**
     * Continuação do relato: acolhe o detalhe e segue perguntando, sem diagnóstico.
     *
     * @param  array<string, mixed>  $ctx
     */
    public function respostaContinuacaoSintoma(string $mensagem, array $ctx = []): string
    {
        $nome = trim((string) ($ctx['primeiro_nome'] ?? ''));
        $vocativo = $nome !== '' ? ', '.$nome : '';
        $t = $this->normalizarTextoMenu($mensagem);

        $forte = str_contains($t, 'intensa')
            || str_contains($t, 'intenso')
            || str_contains($t, 'forte')
            || str_contains($t, 'muita')
            || str_contains($t, 'muitas')
            || str_contains($t, 'muito');

        $alerta = str_contains($t, 'sangue')
            || str_contains($t, 'sangramento')
            || str_contains($t, 'falta de ar')
            || str_contains($t, 'desmaio')
            || str_contains($t, 'visao embacada')
            || str_contains($t, 'nao enxergo');

        $tempo = '';
        if (str_contains($t, 'dois dias') || str_contains($t, '2 dias')) {
            $tempo = 'há dois dias';
        } elseif (str_contains($t, 'ontem')) {
            $tempo = 'desde ontem';
        } elseif (preg_match('/\b(\d+)\s*dia/', $t, $m)) {
            $tempo = 'há '.$m[1].' dia'.((int) $m[1] === 1 ? '' : 's');
        } elseif (preg_match('/\b(\d+)\s*hora/', $t, $m)) {
            $tempo = 'há '.$m[1].' hora'.((int) $m[1] === 1 ? '' : 's');
        }

        $linhas = [];
        if ($forte) {
            $linhas[] = "Entendi que a dor está forte{$vocativo}. Obrigada por me contar.";
        } elseif ($tempo !== '') {
            $linhas[] = "Obrigada por contar que isso começou {$tempo}{$vocativo}.";
        } else {
            $linhas[] = "Obrigada por ir me contando{$vocativo}. Estou te ouvindo.";
        }

        if ($tempo !== '' && $forte) {
            $linhas[0] = "Entendi: a dor está forte e começou {$tempo}{$vocativo}.";
        }

        if ($alerta) {
            $linhas[] = 'Com esse relato, o melhor é procurar atendimento na sua unidade ou com a equipe do pré-natal. Já registrei o que você escreveu para a equipe.';
        } else {
            $linhas[] = 'A dor é contínua ou vai e volta? Tem algum outro sintoma junto, como enjoo, tontura, sangramento ou falta de ar?';
            $linhas[] = 'Não consigo fazer diagnóstico por aqui, mas a equipe do pré-natal já foi avisada. Se a dor piorar, procure atendimento.';
        }

        $linhas[] = 'Pode seguir escrevendo. Envie 0 para voltar ao menu inicial.';

        return implode("\n\n", $linhas);
    }

    public function descreverQueixa(string $mensagem): string
    {
        $t = $this->normalizarTextoMenu($mensagem);

        return match (true) {
            str_contains($t, 'dor de cabeca'), str_contains($t, 'dor na cabeca'), str_contains($t, 'enxaqueca') => 'dor de cabeça',
            str_contains($t, 'barriga'), str_contains($t, 'abdomen'), str_contains($t, 'abdominal') => 'dor na barriga',
            str_contains($t, 'falta de ar') => 'falta de ar',
            str_contains($t, 'sangramento'), str_contains($t, 'sangue') => 'esse sangramento',
            str_contains($t, 'nausea'), str_contains($t, 'enjoo') => 'enjoo',
            str_contains($t, 'tontura') => 'tontura',
            str_contains($t, 'contracao') => 'contração',
            str_contains($t, 'dor') => 'dor',
            default => 'esse desconforto',
        };
    }

    public function instrucoesConversaSintoma(): string
    {
        return <<<'TXT'
MODO CONVERSA (relato de sintoma) — VOCÊ responde agora. O backend não envia texto pronto.
- Responda sempre: calorosa, natural, em português, 2 a 5 frases. Não deixe a gestante sem resposta.
- Use o primeiro nome se estiver neste contexto. Use só dados de consulta/prontuário listados abaixo; não invente.
- Primeira mensagem do relato: acolha e faça 1 ou 2 perguntas (há quanto tempo; leve/moderada/forte).
  Exemplo de tom: “Sinto muito que você esteja com dor na barriga, {nome}. 💙 Vamos entender um pouquinho melhor. Há quanto tempo essa dor começou? Ela está leve, moderada ou forte?”
- Mensagens seguintes: NÃO repita o acolhimento inicial. Continue a partir do que ela acabou de dizer (duração, intensidade, se é contínua, outros sintomas).
- Não comece com “não posso avaliar sintomas”. Não diagnostique, não diga se é normal ou grave, não indique medicamento.
- Se houver sinal de alerta (sangramento, falta de ar, dor muito forte, desmaio, visão embaçada), oriente a procurar atendimento e diga que a equipe do pré-natal foi avisada.
- Depois de entender o relato, ofereça encaminhar para a equipe. Enviar 0 volta ao menu inicial.
- Não peça CPF, telefone nem dados que já estejam neste contexto.
TXT;
    }

    /**
     * @param  list<array{nome:?string, data:?string, status:?string, local:?string}>  $exames
     */
    public function listarExames(array $exames): string
    {
        if ($exames === []) {
            return <<<'TXT'
No momento, não encontrei exames cadastrados para você no sistema.

Isso não significa que você não tenha realizado um exame. Para confirmar essa informação, posso encaminhar você para um atendente.

Envie "atendente" para registrar o encaminhamento, ou 0 para o menu inicial.
TXT;
        }

        $linhas = ['Estes são os exames cadastrados no seu pré-natal:', ''];
        foreach ($exames as $exame) {
            $item = $this->formatarCamposPresentes([
                $exame['nome'] ?? null,
                isset($exame['data']) && $exame['data'] ? 'Data: '.$exame['data'] : null,
                isset($exame['status']) && $exame['status'] ? 'Status: '.$exame['status'] : null,
                isset($exame['local']) && $exame['local'] ? 'Local: '.$exame['local'] : null,
            ]);
            if ($item !== '') {
                $linhas[] = '• '.$item;
            }
        }

        $linhas[] = '';
        $linhas[] = 'Essas informações são baseadas nos registros disponíveis no sistema.';
        $linhas[] = '';
        $linhas[] = '0. Menu inicial';

        return implode("\n", $linhas);
    }

    /**
     * @param  list<array{tipo:?string, data:?string, horario:?string, local:?string, profissional:?string, idade_gestacional:?string}>  $consultas
     */
    public function listarConsultas(array $consultas): string
    {
        if ($consultas === []) {
            return <<<'TXT'
Não encontrei consultas cadastradas no sistema no momento.

Posso encaminhar você para um atendente para confirmar essa informação.

Envie "atendente" para registrar o encaminhamento, ou 0 para o menu inicial.
TXT;
        }

        $linhas = ['Estas são as consultas cadastradas no seu pré-natal:', ''];
        foreach ($consultas as $consulta) {
            $item = $this->formatarCamposPresentes([
                $consulta['tipo'] ?? null,
                isset($consulta['data']) && $consulta['data'] ? 'Data: '.$consulta['data'] : null,
                isset($consulta['horario']) && $consulta['horario'] ? 'Horário: '.$consulta['horario'] : null,
                isset($consulta['local']) && $consulta['local'] ? 'Local: '.$consulta['local'] : null,
                isset($consulta['profissional']) && $consulta['profissional'] ? 'Profissional: '.$consulta['profissional'] : null,
                isset($consulta['idade_gestacional']) && $consulta['idade_gestacional'] ? 'Idade gestacional: '.$consulta['idade_gestacional'].' semanas' : null,
            ]);
            if ($item !== '') {
                $linhas[] = '• '.$item;
            }
        }

        $linhas[] = '';
        $linhas[] = 'Essas informações são baseadas nos registros disponíveis no sistema.';
        $linhas[] = '';
        $linhas[] = '0. Menu inicial';

        return implode("\n", $linhas);
    }

    /**
     * @param  list<array{data:string, horario:?string, descricao:string}>  $compromissos
     */
    public function listarProximosCompromissos(array $compromissos): string
    {
        if ($compromissos === []) {
            return "Não encontrei próximos compromissos cadastrados no sistema.\n\n0. Menu inicial";
        }

        $linhas = ['Seus próximos compromissos cadastrados são:', ''];
        foreach ($compromissos as $item) {
            $quando = '📅 '.$item['data'];
            if (! empty($item['horario'])) {
                $horario = $item['horario'];
                if (preg_match('/^(\d{2}):(\d{2})/', $horario, $m)) {
                    $quando .= ', '.$m[1].'h'.$m[2];
                } else {
                    $quando .= ', '.$horario;
                }
            }
            $linhas[] = $quando;
            $linhas[] = $item['descricao'];
            $linhas[] = '';
        }

        $linhas[] = 'Essas informações são baseadas nos registros disponíveis no sistema.';
        $linhas[] = '';
        $linhas[] = '0. Menu inicial';

        return implode("\n", $linhas);
    }

    /**
     * @param  list<string|null>  $campos
     */
    private function formatarCamposPresentes(array $campos): string
    {
        $presentes = [];
        foreach ($campos as $campo) {
            if ($campo !== null && trim((string) $campo) !== '') {
                $presentes[] = trim((string) $campo);
            }
        }

        return implode(', ', $presentes);
    }

    public function menuConfirmacao(string $opcao, string $nome): string
    {
        $nome = trim($nome) !== '' ? trim($nome) : 'gestante';

        return match ($opcao) {
            'atendente' => "Certo, {$nome}! Vou chamar um atendente. Em instantes alguém da equipe responde por aqui.",
            'medico' => "Certo, {$nome}! Vou encaminhar para o médico. A equipe entra em contato por esta conversa.",
            default => '',
        };
    }

    public function normalizarTextoMenu(?string $mensagem): string
    {
        $t = mb_strtolower(trim((string) $mensagem));
        $t = preg_replace('/\s+/', ' ', $t) ?: '';

        return strtr($t, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
    }

    /**
     * Extrai telefone + texto de um evento WAHA (voto de enquete ou clique de botão).
     *
     * @param  array<string, mixed>  $payload
     * @return array{telefone: string, mensagem: string, tipo: string}|null
     */
    public function extractIncomingFromWahaEvent(array $payload): ?array
    {
        $event = (string) ($payload['event'] ?? '');
        $from = (string) (
            data_get($payload, 'payload.from')
            ?: data_get($payload, 'payload.vote.from')
            ?: ''
        );

        $rowId = (string) (
            data_get($payload, 'payload._data.Message.listResponseMessage.singleSelectReply.selectedRowID')
            ?: data_get($payload, 'payload._data.Message.listResponseMessage.singleSelectReply.selectedRowId')
            ?: data_get($payload, 'payload._data.listResponseMessage.singleSelectReply.selectedRowId')
            ?: data_get($payload, 'payload.listResponse.singleSelectReply.selectedRowId')
            ?: data_get($payload, 'payload.selectedRowId')
            ?: data_get($payload, 'payload.replyId')
            ?: ''
        );
        $listTitle = (string) (
            data_get($payload, 'payload._data.Message.listResponseMessage.title')
            ?: data_get($payload, 'payload._data.listResponseMessage.title')
            ?: data_get($payload, 'payload.listResponse.title')
            ?: ''
        );
        $selecao = $listTitle !== '' ? $listTitle : $rowId;
        if ($from !== '' && $selecao !== '' && $this->matchMenuOpcao($selecao) !== null) {
            return ['telefone' => $from, 'mensagem' => $selecao, 'tipo' => 'entrada'];
        }

        if ($event === 'poll.vote') {
            $from = (string) (data_get($payload, 'payload.vote.from') ?: data_get($payload, 'payload.from') ?: '');
            $option = (string) (data_get($payload, 'payload.vote.selectedOptions.0') ?: '');
            if ($from === '' || $option === '') {
                return null;
            }

            return ['telefone' => $from, 'mensagem' => $option, 'tipo' => 'entrada'];
        }

        $buttonId = (string) (data_get($payload, 'payload.selectedButtonId')
            ?: data_get($payload, 'payload._data.selectedButtonId')
            ?: '');
        $buttonText = (string) (data_get($payload, 'payload.body')
            ?: data_get($payload, 'payload.reply')
            ?: data_get($payload, 'payload.buttonOrList.selectedDisplayText')
            ?: '');
        $from = (string) (data_get($payload, 'payload.from') ?: '');
        $clique = $buttonText !== '' ? $buttonText : $buttonId;
        if ($from !== '' && $clique !== '' && ($buttonId !== '' || $event === 'message')) {
            if ($this->matchMenuOpcao($clique) !== null) {
                return ['telefone' => $from, 'mensagem' => $clique, 'tipo' => 'entrada'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function riscoAlto(string $nome, array $ctx = []): string
    {
        $ig = $this->fraseIdadeGestacional($ctx);
        $consulta = $this->fraseUltimaConsulta($ctx);

        return <<<TXT
Olá {$nome}, tudo bem?

Aqui é o GestRisk. Com base na sua avaliação pré-natal{$ig}{$consulta}, nossa equipe identificou que você precisa de um acompanhamento mais próximo neste momento.

Em breve entraremos em contato para orientar os próximos passos. Se tiver qualquer dúvida ou sintoma que te preocupe, pode responder esta mensagem.

Estamos com você.
TXT;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function ecoRecomendada(string $nome, array $ctx = []): string
    {
        $ig = $this->fraseIdadeGestacional($ctx);
        $prioridadeLabel = (string) ($ctx['prioridade_label'] ?? '');
        $extra = $prioridadeLabel !== ''
            ? " A prioridade sugerida para o exame é: {$prioridadeLabel}."
            : '';

        return <<<TXT
Olá {$nome}, tudo bem?

Aqui é o GestRisk. Com base na sua avaliação pré-natal{$ig}, recomendamos a realização de um ecocardiograma fetal.{$extra}

Nossa equipe pode ajudar a orientar o agendamento. Se precisar de informações, responda esta mensagem.

Estamos à disposição.
TXT;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function retorno(string $nome, array $ctx = []): string
    {
        $quando = ! empty($ctx['data_agendada'])
            ? " O retorno está previsto para {$ctx['data_agendada']}."
            : ' Em breve confirmaremos a data do seu retorno.';
        $ig = $this->fraseIdadeGestacional($ctx);

        return <<<TXT
Olá {$nome}, tudo bem?

Aqui é o GestRisk. Gostaríamos de lembrar sobre o seu acompanhamento pré-natal{$ig}.{$quando}

Se precisar remarcar ou tiver dúvidas, responda esta mensagem.

Até logo!
TXT;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function lembreteConsulta(string $nome, array $ctx = []): string
    {
        $ig = $this->fraseIdadeGestacional($ctx);

        if (! empty($ctx['data_agendada'])) {
            $quando = "Sua consulta está prevista para {$ctx['data_agendada']}{$ig}.";
        } elseif (! empty($ctx['data_ultima_consulta'])) {
            $quando = "Registramos sua última consulta em {$ctx['data_ultima_consulta']}{$ig}. Não se esqueça do próximo retorno do pré-natal.";
        } else {
            $quando = "Não se esqueça da sua próxima consulta de pré-natal{$ig}.";
        }

        return <<<TXT
Olá {$nome}, tudo bem?

Aqui é o GestRisk. {$quando}

Se precisar remarcar, responda esta mensagem que nossa equipe ajuda você.

Cuide-se!
TXT;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function lembreteExame(string $nome, array $ctx = []): string
    {
        $ig = $this->fraseIdadeGestacional($ctx);
        $eco = ! empty($ctx['recomenda_eco'])
            ? ' Inclui o ecocardiograma fetal recomendado na sua avaliação.'
            : '';

        $quando = ! empty($ctx['data_agendada'])
            ? "O exame está previsto para {$ctx['data_agendada']}{$ig}."
            : "Lembrete para realizar o exame solicitado no seu pré-natal{$ig}.";

        return <<<TXT
Olá {$nome}, tudo bem?

Aqui é o GestRisk. {$quando}{$eco}

Se tiver dúvidas sobre o exame ou precisar de orientação para o agendamento, responda esta mensagem.

Estamos à disposição.
TXT;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function manual(string $nome, array $ctx = []): string
    {
        $ig = $this->fraseIdadeGestacional($ctx);

        return <<<TXT
Olá {$nome}, tudo bem?

Aqui é o GestRisk. Nossa equipe iniciou um acompanhamento referente ao seu pré-natal{$ig} e entrará em contato em breve com mais orientações.

Se tiver dúvidas, pode responder esta mensagem.

Estamos à disposição.
TXT;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function confirmacaoContato(string $nome, array $ctx = []): string
    {
        return <<<TXT
Olá {$nome}, tudo bem?

Aqui é o GestRisk. Recebemos sua mensagem e nossa equipe vai retornar em breve.

Se for urgente ou houver algum sintoma que te preocupe, responda esta mensagem destacando a urgência.

Obrigada pelo contato!
TXT;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function confirmacaoConsulta(string $nome, array $ctx = []): string
    {
        $quando = ! empty($ctx['data_agendada'])
            ? (string) $ctx['data_agendada']
            : 'data e horário combinados com a equipe';
        $profissional = trim((string) ($ctx['profissional'] ?? ''));
        $linhaProfissional = $profissional !== ''
            ? "Profissional: {$profissional}\n"
            : '';

        return <<<TXT
Olá {$nome}, tudo bem?

Sua consulta no Cardioprenatal foi confirmada.

Data e horário: {$quando}
{$linhaProfissional}
Compareça no horário agendado. Se precisar remarcar, responda esta mensagem ou fale com a equipe.

Equipe Cardioprenatal
TXT;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function orientacaoGeral(string $nome, array $ctx = []): string
    {
        $ig = $this->fraseIdadeGestacional($ctx);

        return <<<TXT
Olá {$nome}, tudo bem?

Aqui é o GestRisk. Estamos acompanhando o seu pré-natal{$ig} e estamos à disposição para orientar sempre que precisar.

Pode responder esta mensagem com sua dúvida.

Estamos com você.
TXT;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function atendenteHumano(string $nome, array $ctx = []): string
    {
        return <<<TXT
Olá {$nome}, tudo bem?

Aqui é a equipe do Cardioprenatal. A partir de agora você fala com um atendente humano. O assistente automático fica em pausa para não misturar as mensagens.

Pode nos contar como podemos ajudar.
TXT;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function retomarAssistente(string $nome, array $ctx = []): string
    {
        return <<<TXT
Olá {$nome}.

A resposta automática do Cardioprenatal voltou a responder nesta conversa. Se quiser falar de novo com um atendente, é só pedir.
TXT;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function fraseIdadeGestacional(array $ctx): string
    {
        $ig = $ctx['idade_gestacional'] ?? null;
        if ($ig === null || $ig === '') {
            return '';
        }

        return ", com cerca de {$ig} semanas";
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function fraseUltimaConsulta(array $ctx): string
    {
        if (empty($ctx['data_ultima_consulta'])) {
            return '';
        }

        return " (última consulta em {$ctx['data_ultima_consulta']})";
    }

    private function primeiroNome(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return 'gestante';
        }

        $partes = preg_split('/\s+/', $nome) ?: [];

        return $partes[0] !== '' ? $partes[0] : 'gestante';
    }

    private function labelPrioridade(?string $prioridade): string
    {
        return match ($prioridade) {
            'urgente' => 'urgente',
            'alta' => 'alta',
            'moderada' => 'moderada',
            'baixa' => 'baixa',
            default => '',
        };
    }
}

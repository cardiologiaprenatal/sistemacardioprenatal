<?php

namespace App\Services;

use App\Contracts\AtendimentoMenuFlow;
use App\Models\Acompanhamento;
use App\Models\Gestante;
use App\Support\WhatsAppFluxoEstado;

/**
 * Roteador determinístico do menu inicial da gestante.
 * Dados estruturados vêm do banco; o LLM não monta exames, consultas ou datas.
 */
class WhatsAppMenuFlowService implements AtendimentoMenuFlow
{
    public function __construct(
        private WhatsAppMessageTemplates $templates,
        private WhatsAppGestanteFatosService $fatos,
        private WhatsAppSolicitacaoAtendimentoService $solicitacoes,
    ) {}

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    public function handle(Gestante $gestante, string $mensagem): array
    {
        $estado = $gestante->whatsapp_fluxo_estado ?: WhatsAppFluxoEstado::MENU_INICIAL;

        if ($this->ehVoltarMenu($mensagem)) {
            return $this->irMenuInicial($gestante);
        }

        if (in_array($estado, [
            WhatsAppFluxoEstado::ATENDENTE,
            WhatsAppFluxoEstado::ATENDENTE_CATEGORIA,
            WhatsAppFluxoEstado::FALAR_COM_MEDICO,
            WhatsAppFluxoEstado::AGUARDANDO_MENSAGEM_MEDICO,
        ], true)) {
            $bloqueio = $this->exigirAtendimentoLiberado($gestante);
            if ($bloqueio !== null) {
                return $bloqueio;
            }
        }

        $resultado = match ($estado) {
            WhatsAppFluxoEstado::ATENDENTE, WhatsAppFluxoEstado::ATENDENTE_CATEGORIA => $this->handleAtendente($gestante, $mensagem),
            WhatsAppFluxoEstado::EXAMES_CONSULTAS,
            WhatsAppFluxoEstado::MEUS_EXAMES,
            WhatsAppFluxoEstado::MINHAS_CONSULTAS,
            WhatsAppFluxoEstado::PROXIMOS_COMPROMISSOS => $this->handleExamesConsultas($gestante, $mensagem),
            WhatsAppFluxoEstado::FALAR_COM_MEDICO,
            WhatsAppFluxoEstado::AGUARDANDO_MENSAGEM_MEDICO => $this->handleMedico($gestante, $mensagem),
            WhatsAppFluxoEstado::CONVERSA_SINTOMA => $this->handleConversaSintoma($gestante, $mensagem),
            default => $this->handleMenuInicial($gestante, $mensagem),
        };

        return $resultado;
    }

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    public function irMenuInicial(Gestante $gestante): array
    {
        $resposta = $this->templates->menuInicial(
            $this->nomeCadastro($gestante) ?? '',
            $this->templates->contextFromGestante($gestante),
        );

        return $this->persistir($gestante, WhatsAppFluxoEstado::MENU_INICIAL, $resposta, 'menu');
    }

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function handleMenuInicial(Gestante $gestante, string $mensagem): array
    {
        $opcao = $this->templates->matchMenuOpcao($mensagem);

        if ($opcao === 'atendente') {
            return $this->irAtendimentoHumano($gestante);
        }

        if ($opcao === 'exames') {
            return $this->persistir(
                $gestante,
                WhatsAppFluxoEstado::EXAMES_CONSULTAS,
                $this->templates->menuExamesConsultas($this->nomeCadastro($gestante)),
                'exames_consultas',
            );
        }

        if ($opcao === 'medico') {
            if (! $gestante->atendimentoPersonalizadoLiberado()) {
                return $this->persistir(
                    $gestante,
                    WhatsAppFluxoEstado::MENU_INICIAL,
                    $this->templates->atendimentoPersonalizadoNaoLiberado(),
                    'atendimento_bloqueado',
                );
            }

            return $this->irFalarComMedico($gestante);
        }

        $livre = $this->resolverIntencaoLivre($gestante, $mensagem);
        if ($livre !== null) {
            return $livre;
        }

        if ($this->templates->matchOpcaoNumero($mensagem, 9) !== null) {
            return $this->persistir(
                $gestante,
                WhatsAppFluxoEstado::MENU_INICIAL,
                $this->templates->opcaoInvalida(),
                'menu',
            );
        }

        return $this->deixarComIa($gestante);
    }

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function handleAtendente(Gestante $gestante, string $mensagem): array
    {
        $opcao = $this->templates->matchOpcaoNumero($mensagem, 4);
        $categorias = [
            1 => 'Dúvida sobre meu pré-natal',
            2 => 'Exames',
            3 => 'Consultas',
            4 => 'Outro assunto',
        ];

        if ($opcao !== null) {
            $categoria = $categorias[$opcao];
            $this->solicitacoes->registrar(
                $gestante,
                WhatsAppSolicitacaoAtendimentoService::ORIGEM_ATENDENTE,
                $categoria,
                $categoria,
            );

            return $this->persistir(
                $gestante,
                WhatsAppFluxoEstado::MENU_INICIAL,
                $this->templates->solicitacaoAtendenteRegistrada(),
                'atendimento_humano',
            );
        }

        if ($this->templates->matchOpcaoNumero($mensagem, 9) !== null) {
            return $this->persistir(
                $gestante,
                WhatsAppFluxoEstado::ATENDENTE,
                $this->templates->opcaoInvalida(),
                'atendimento_humano',
            );
        }

        $texto = trim($mensagem);
        if ($texto === '') {
            return $this->persistir(
                $gestante,
                WhatsAppFluxoEstado::ATENDENTE,
                $this->templates->opcaoInvalida(),
                'atendimento_humano',
            );
        }

        $this->solicitacoes->registrar(
            $gestante,
            WhatsAppSolicitacaoAtendimentoService::ORIGEM_ATENDENTE,
            $texto,
        );

        return $this->persistir(
            $gestante,
            WhatsAppFluxoEstado::MENU_INICIAL,
            $this->templates->solicitacaoAtendenteRegistrada(),
            'atendimento_humano',
        );
    }

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function handleExamesConsultas(Gestante $gestante, string $mensagem): array
    {
        $opcao = $this->templates->matchOpcaoNumero($mensagem, 4);
        $texto = $this->templates->normalizarTextoMenu($mensagem);

        if ($opcao === 4 || $texto === 'voltar' || $texto === 'voltar ao menu inicial') {
            return $this->irMenuInicial($gestante);
        }

        if ($opcao === 1 || $texto === 'meus exames') {
            return $this->responderExames($gestante);
        }

        if ($opcao === 2 || $texto === 'minhas consultas') {
            return $this->responderConsultas($gestante);
        }

        if ($opcao === 3 || $texto === 'proximos compromissos') {
            return $this->responderCompromissos($gestante);
        }

        $atalhoMenu = $this->templates->matchMenuOpcao($mensagem);
        if ($atalhoMenu === 'atendente') {
            return $this->irAtendimentoHumano($gestante);
        }
        if ($atalhoMenu === 'medico') {
            return $this->irFalarComMedico($gestante);
        }

        $livre = $this->resolverIntencaoLivre($gestante, $mensagem);
        if ($livre !== null) {
            return $livre;
        }

        if ($this->templates->matchOpcaoNumero($mensagem, 9) !== null) {
            return $this->persistir(
                $gestante,
                WhatsAppFluxoEstado::EXAMES_CONSULTAS,
                $this->templates->opcaoInvalida(),
                'exames_consultas',
            );
        }

        return $this->deixarComIa($gestante);
    }

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function handleMedico(Gestante $gestante, string $mensagem): array
    {
        $texto = trim($mensagem);
        if ($texto === '') {
            return $this->persistir(
                $gestante,
                WhatsAppFluxoEstado::AGUARDANDO_MENSAGEM_MEDICO,
                $this->templates->opcaoInvalida(),
                'falar_medico',
            );
        }

        if ($this->templates->matchOpcaoNumero($texto, 9) !== null && mb_strlen($texto) <= 3) {
            return $this->persistir(
                $gestante,
                WhatsAppFluxoEstado::AGUARDANDO_MENSAGEM_MEDICO,
                $this->templates->opcaoInvalida(),
                'falar_medico',
            );
        }

        if ($this->templates->matchIntencaoLivre($mensagem) === 'sintoma') {
            return $this->iniciarConversaSintoma($gestante, $mensagem);
        }

        $jaRegistrado = Acompanhamento::query()
            ->where('gestante_id', $gestante->id)
            ->where('motivo', Acompanhamento::MOTIVO_MANUAL)
            ->where('titulo', 'WhatsApp — Falar com o médico')
            ->abertos()
            ->exists();

        $this->solicitacoes->registrar(
            $gestante,
            WhatsAppSolicitacaoAtendimentoService::ORIGEM_MEDICO,
            $texto,
        );

        return $this->persistir(
            $gestante,
            WhatsAppFluxoEstado::AGUARDANDO_MENSAGEM_MEDICO,
            $jaRegistrado
                ? $this->templates->solicitacaoDetalheRecebido()
                : $this->templates->solicitacaoMedicoRegistrada(),
            'falar_medico',
        );
    }

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}|null
     */
    private function resolverIntencaoLivre(Gestante $gestante, string $mensagem): ?array
    {
        $intencao = $this->templates->matchIntencaoLivre($mensagem);

        return match ($intencao) {
            'exames_dados' => $this->responderExames($gestante),
            'consultas_dados' => $this->responderConsultas($gestante),
            'compromissos' => $this->responderCompromissos($gestante),
            'sintoma' => $this->iniciarConversaSintoma($gestante, $mensagem),
            'interpretacao' => $this->registrarRelatoClinico(
                $gestante,
                $mensagem,
                'Pedido de interpretação clínica',
                $this->templates->respostaMedicaEspontanea(),
            ),
            default => null,
        };
    }

    /**
     * Relato de sintoma: acolhe e segue a conversa no Laravel (o n8n não chama o Gemini neste fluxo).
     *
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function iniciarConversaSintoma(Gestante $gestante, string $mensagem): array
    {
        $this->solicitacoes->registrar(
            $gestante,
            WhatsAppSolicitacaoAtendimentoService::ORIGEM_MEDICO,
            $mensagem,
            'Relato de sintoma',
        );

        $ctx = $this->templates->contextFromGestante($gestante);

        return $this->persistir(
            $gestante,
            WhatsAppFluxoEstado::CONVERSA_SINTOMA,
            $this->templates->respostaAcolhimentoSintoma($mensagem, $ctx),
            'conversa_sintoma',
        );
    }

    /**
     * Continuação do relato: anota para a equipe e a IA segue o diálogo.
     *
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function handleConversaSintoma(Gestante $gestante, string $mensagem): array
    {
        $opcao = $this->templates->matchMenuOpcao($mensagem);
        if ($opcao === 'atendente') {
            return $this->irAtendimentoHumano($gestante);
        }
        if ($opcao === 'exames') {
            return $this->persistir(
                $gestante,
                WhatsAppFluxoEstado::EXAMES_CONSULTAS,
                $this->templates->menuExamesConsultas($this->nomeCadastro($gestante)),
                'exames_consultas',
            );
        }

        $intencao = $this->templates->matchIntencaoLivre($mensagem);
        if ($intencao === 'exames_dados') {
            return $this->responderExames($gestante);
        }
        if ($intencao === 'consultas_dados') {
            return $this->responderConsultas($gestante);
        }
        if ($intencao === 'compromissos') {
            return $this->responderCompromissos($gestante);
        }
        if ($intencao === 'interpretacao') {
            return $this->registrarRelatoClinico(
                $gestante,
                $mensagem,
                'Pedido de interpretação clínica',
                $this->templates->respostaMedicaEspontanea(),
            );
        }

        $texto = trim($mensagem);
        if ($texto !== '') {
            $this->solicitacoes->registrar(
                $gestante,
                WhatsAppSolicitacaoAtendimentoService::ORIGEM_MEDICO,
                $texto,
                'Relato de sintoma',
            );
        }

        $ctx = $this->templates->contextFromGestante($gestante);

        return $this->persistir(
            $gestante,
            WhatsAppFluxoEstado::CONVERSA_SINTOMA,
            $this->templates->respostaContinuacaoSintoma($mensagem, $ctx),
            'conversa_sintoma',
        );
    }

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function registrarRelatoClinico(
        Gestante $gestante,
        string $mensagem,
        string $categoria,
        string $resposta,
    ): array {
        $this->solicitacoes->registrar(
            $gestante,
            WhatsAppSolicitacaoAtendimentoService::ORIGEM_MEDICO,
            $mensagem,
            $categoria,
        );

        return $this->persistir(
            $gestante,
            WhatsAppFluxoEstado::AGUARDANDO_MENSAGEM_MEDICO,
            $resposta,
            'falar_medico',
        );
    }

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function responderExames(Gestante $gestante): array
    {
        return $this->persistir(
            $gestante,
            WhatsAppFluxoEstado::EXAMES_CONSULTAS,
            $this->templates->listarExames($this->fatos->exames($gestante)),
            'exames_consultas',
        );
    }

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function responderConsultas(Gestante $gestante): array
    {
        return $this->persistir(
            $gestante,
            WhatsAppFluxoEstado::EXAMES_CONSULTAS,
            $this->templates->listarConsultas($this->fatos->consultas($gestante)),
            'exames_consultas',
        );
    }

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function responderCompromissos(Gestante $gestante): array
    {
        return $this->persistir(
            $gestante,
            WhatsAppFluxoEstado::EXAMES_CONSULTAS,
            $this->templates->listarProximosCompromissos($this->fatos->proximosCompromissos($gestante)),
            'exames_consultas',
        );
    }

    /**
     * Mensagem livre: o n8n/Gemini responde com RAG. O Laravel não envia "opção inválida".
     *
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function deixarComIa(
        Gestante $gestante,
        string $estado = WhatsAppFluxoEstado::MENU_INICIAL,
        string $intencao = 'conversa_livre',
    ): array {
        $gestante->forceFill([
            'whatsapp_fluxo_estado' => $estado,
            'whatsapp_menu_opcao' => WhatsAppFluxoEstado::menuOpcao($estado),
        ])->save();

        return [
            'estado' => $estado,
            'resposta' => '',
            'respondido' => false,
            'pular_ia' => false,
            'intencao' => $intencao,
        ];
    }

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function persistir(Gestante $gestante, string $estado, string $resposta, string $intencao = 'menu'): array
    {
        $gestante->forceFill([
            'whatsapp_fluxo_estado' => $estado,
            'whatsapp_menu_opcao' => WhatsAppFluxoEstado::menuOpcao($estado),
        ])->save();

        return [
            'estado' => $estado,
            'resposta' => $resposta,
            'respondido' => true,
            'pular_ia' => true,
            'intencao' => $intencao,
        ];
    }

    private function exigirAtendimentoLiberado(Gestante $gestante): ?array
    {
        if ($gestante->atendimentoPersonalizadoLiberado()) {
            return null;
        }

        return $this->persistir(
            $gestante,
            WhatsAppFluxoEstado::MENU_INICIAL,
            $this->templates->atendimentoPersonalizadoNaoLiberado(),
            'atendimento_bloqueado',
        );
    }

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function irAtendimentoHumano(Gestante $gestante): array
    {
        $bloqueio = $this->exigirAtendimentoLiberado($gestante);
        if ($bloqueio !== null) {
            return $bloqueio;
        }

        return $this->persistir(
            $gestante,
            WhatsAppFluxoEstado::ATENDENTE,
            $this->templates->menuAtendente(),
            'atendimento_humano',
        );
    }

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    private function irFalarComMedico(Gestante $gestante): array
    {
        $bloqueio = $this->exigirAtendimentoLiberado($gestante);
        if ($bloqueio !== null) {
            return $bloqueio;
        }

        return $this->persistir(
            $gestante,
            WhatsAppFluxoEstado::AGUARDANDO_MENSAGEM_MEDICO,
            $this->templates->menuFalarComMedico(),
            'falar_medico',
        );
    }

    private function ehVoltarMenu(string $mensagem): bool
    {
        if (preg_match('/^\s*0+\s*[.)]?\s*$/', $mensagem)) {
            return true;
        }

        $t = $this->templates->normalizarTextoMenu($mensagem);

        return $t === '0'
            || $t === 'menu'
            || $t === 'menu inicial'
            || $t === 'voltar ao menu'
            || $t === 'voltar ao menu inicial';
    }

    private function nomeCadastro(Gestante $gestante): ?string
    {
        $nome = trim((string) ($gestante->nome ?? ''));
        if ($nome === '') {
            return null;
        }

        $partes = preg_split('/\s+/', $nome) ?: [];

        return $partes[0] !== '' ? $partes[0] : null;
    }
}

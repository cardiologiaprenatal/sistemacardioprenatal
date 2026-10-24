# API Laravel — Histórico WhatsApp (integração n8n + WAHA)

Este documento descreve os endpoints HTTP usados para **gravar mensagens recebidas e enviadas** na tabela `gestante_whatsapp`, para consumo pelo **n8n** (e fluxos ligados ao **WAHA**).

**Base URL (desenvolvimento):** `http://localhost:8000` (ou a URL do seu `APP_URL` + prefixo `/api`).

**Prefixo das rotas API:** todas as rotas abaixo ficam sob **`/api`**.

---

## Autenticação opcional

Se no `.env` do Laravel existir **`WAHA_WEBHOOK_SECRET`** preenchido, cada requisição deve enviar o mesmo valor em um destes formatos:

| Onde | Valor |
|------|--------|
| Header `X-Webhook-Secret` | token literal |
| Header `Authorization` | `Bearer <token>` |

Se **`WAHA_WEBHOOK_SECRET`** estiver vazio, o endpoint fica **aberto** (apenas para rede local / dev — não recomendado em produção sem outra camada de proteção).

---

## 1) Ingestão com telefone no **corpo** (legado / webhook)

**`POST /api/gestante-whatsapp`**

**Headers**

- `Content-Type: application/json`
- `Accept: application/json`
- `X-Webhook-Secret` ou `Authorization: Bearer ...` (se configurado)

**Body (JSON)**

```json
{
  "telefone": "5521999999999",
  "mensagem": "Texto da mensagem",
  "tipo": "entrada"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `telefone` | string | sim | Número com DDD; pode vir com máscara — o backend normaliza para dígitos. |
| `mensagem` | string | sim | Conteúdo da mensagem. |
| `tipo` | string | sim | **`entrada`**: paciente → sistema (recebida). **`saida`**: sistema → paciente (enviada). |

**Resposta `201 Created`**

```json
{
  "id": 42,
  "gestante_id": 3,
  "tempo_atendimento": 120,
  "tempo_atendimento_formatado": "2m 0s"
}
```

- **`gestante_id`**: ID interno em `gestantes` quando o telefone bate com um cadastro; caso contrário pode ser `null` (mensagem ainda é salva para auditoria).
- **`tempo_atendimento`**: segundos desde a **última** mensagem dessa gestante; `null` se for a primeira mensagem vinculada a ela.

**Erros comuns:** `422` (validação), `401` (secret inválido), `429` (throttle).

---

## 2) Ingestão com telefone na **URL** (recomendado para n8n)

**`POST /api/v1/n8n/whatsapp/mensagens/{telefone}`**

- **`{telefone}`**: **somente dígitos**, **10 a 16 caracteres** (ex.: `5521999999999`).
- Não use espaços, traços ou `+` no path; normalize no n8n antes (Expression: só dígitos).

**Headers** — iguais ao endpoint anterior.

**Body (JSON)** — telefone **não** vai no JSON (já está na URL):

```json
{
  "mensagem": "Texto da mensagem",
  "tipo": "saida"
}
```

**Resposta** — igual ao endpoint `POST /api/gestante-whatsapp` (`201` + mesmo JSON).

**Exemplo cURL**

```bash
curl -s -X POST "http://localhost:8000/api/v1/n8n/whatsapp/mensagens/5521999999999" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"mensagem":"Olá!","tipo":"entrada"}'
```

---

## n8n — HTTP Request (sugestão)

1. **Method:** POST  
2. **URL:** `{{ $env.APP_URL }}/api/v1/n8n/whatsapp/mensagens/{{ $json.telefoneDigits }}`  
   - Montar `telefoneDigits` com função que remove tudo que não for dígito (WAHA costuma mandar `5511...@c.us` — extrair só o número antes do `@`).
3. **Body:** JSON  
   - `mensagem`: texto vindo do WAHA / seu fluxo  
   - `tipo`: `entrada` se for mensagem **recebida** do usuário; `saida` se for **enviada** pela sessão/linha  

4. Se usar secret: header `X-Webhook-Secret` = valor de `WAHA_WEBHOOK_SECRET` no Laravel.

**Throttle:** até **120 requisições por minuto** por rota (middleware `throttle:120,1`). Em carga maior, ajuste no Laravel ou use fila no n8n.

---

## WAHA — relação com esta API

- O **WAHA** envia/recebe mensagens via HTTP próprio (`POST .../api/sendText`, webhooks de eventos, etc.).
- Este endpoint Laravel **não substitui** o WAHA: ele **persiste** no banco do GestRisk/Cardioprenatal o que o n8n decidir enviar (por exemplo, após um Webhook que recebe payload do WAHA).
- Fluxo típico: **WAHA → Webhook n8n → HTTP Request → Laravel** (`/api/v1/n8n/...`).

---

## Tabela no banco

- **Tabela:** `gestante_whatsapp`  
- **Campos relevantes:** `gestante_id` (FK opcional), `mensagem`, `tipo` (`entrada` | `saida`), `tempo_atendimento`, `created_at`

---

## Referência rápida

| Método | Caminho | Telefone |
|--------|---------|----------|
| POST | `/api/gestante-whatsapp` | campo JSON `telefone` |
| POST | `/api/v1/n8n/whatsapp/mensagens/{telefone}` | segmento de URL (só dígitos) |
| GET/POST | `/api/v1/n8n/whatsapp/rag/{telefone}` | segmento de URL (só dígitos) |

Os endpoints de mensagem gravam o mesmo registro e retornam o mesmo formato em sucesso.

---

## 3) RAG — contexto clínico / documentos (WhatsApp)

**`GET|POST /api/v1/n8n/whatsapp/rag/{telefone}`**

- Mesmo middleware `whatsapp.webhook` + throttle `60,1`.
- Body/query opcional: `mensagem` (para ranquear trechos), `top_k` (1–12, default 6).
- Fontes: consultas, última análise, interpretação, documentos indexados (`gestante_documentos`), últimas msgs WhatsApp.
- Indexar texto: `php artisan whatsapp:rag-index {gestante_id} --titulo="..." --tipo=exame --texto="..."`.

Usado pelo nó **Laravel_RAG** do fluxo n8n antes do Gemini. Docs detalhados no repo `apiwhatsapp`: `docs/RAG-WHATSAPP.md`.

### Pausar a IA quando um atendente assume

A resposta do RAG inclui:

```json
{
  "pular_ia": true,
  "atendimento_humano": true,
  "ia_pausada_ate": "2026-08-13T19:30:00-03:00"
}
```

No n8n, **depois** do nó Laravel_RAG e **antes** do Gemini / `sendText`:

1. **Grave a mensagem de entrada primeiro** (`POST .../mensagens/{telefone}`), para o Laravel reconhecer o clique do menu.
2. IF `{{ $json.pular_ia }}` é `true`
3. **Não** chamar o Gemini e **não** enviar resposta automática
4. Encerrar o fluxo (o atendente ou o médico respondem pela ficha no Cardioprenatal)

### Menu inicial (3 opções)

No cadastro (e no template **Menu inicial**), a gestante recebe:

1. Falar com atendente → `menu_opcao=atendente` (IA continua até a equipe assumir na ficha)
2. Exames e consultas → `menu_opcao=exames`, `intencao=exames_consultas`
3. Falar com o médico → `menu_opcao=medico` (IA continua até a equipe assumir na ficha)

`pular_ia` só fica `true` quando a **equipe** envia pelo chat da ficha (ou o template **Falar com atendente**). Escolher 1/2/3 **não** pausa a IA.

Se ela **escrever livremente** sem escolher botão, `intencao=conversa_livre` e a IA responde normalmente.

Relato de sintoma (ex.: “estou com dor na barriga”) também fica `pular_ia=false` e `intencao=conversa_livre`. O Laravel **não** envia texto pronto: grava o caso, monta o contexto clínico no RAG e o **Gemini continua a conversa**. Não pule o Gemini nesse fluxo.

Campos extras do RAG:

```json
{
  "pular_ia": false,
  "atendimento_humano": false,
  "menu_opcao": "exames",
  "intencao": "exames_consultas"
}
```

A pausa também começa quando a equipe envia qualquer mensagem pelo chat da ficha (ou o template **Falar com atendente**). O template **Retomar assistente automático** (ou reenviar o **Menu inicial**) zera a pausa e a opção do menu. Duração padrão da pausa: 240 minutos (`WHATSAPP_IA_PAUSA_MINUTOS`).

O menu inicial tenta enviar uma **lista para selecionar** (`POST /api/sendList`): a gestante toca em **Selecionar** e escolhe uma das 3 opções. Isso exige engine **NOWEB** ou **GOWS** (WAHA Plus). O **WEBJS** não envia lista nem botões nativos.

Quando ela seleciona, o WhatsApp manda `list_response` (título da linha ou `selectedRowId`: `menu_atendente`, `menu_exames`, `menu_medico`). Grave isso como mensagem de entrada **antes** do RAG. O Laravel também aceita o payload bruto do WAHA em `POST /api/gestante-whatsapp`.

Se a lista falhar, o Laravel envia o menu numerado em texto (a gestante responde 1, 2 ou 3).

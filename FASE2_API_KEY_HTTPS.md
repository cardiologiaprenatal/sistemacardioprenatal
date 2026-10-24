# Fase 2 — Laravel: API Key + HTTPS

## Feito no código

1. `config/services.php` — `python_api.api_key`, `verify_ssl`; `ccf_api` alinhado
2. `PythonApiService` — todas as requests enviam `X-API-Key`
3. `CcfApiClient` — idem (incluindo gráficos / análise descritiva)
4. `AppServiceProvider` — `FORCE_HTTPS=true` força scheme https
5. `bootstrap/app.php` — `trustProxies(at: '*')` para proxy TLS
6. `.env.example` / `.env.docker.example` documentados

## O que você precisa fazer agora (1 minuto)

No `.env` do **Laravel**, coloque **a mesma** chave do `API_KEY` do AnalisePython:

```env
PYTHON_API_KEY=cole_aqui_a_mesma_chave_do_python
CCF_API_KEY="${PYTHON_API_KEY}"
```

No AnalisePython, a chave está em:

```env
API_KEY=...
```

## Dev vs produção

| Ambiente | URLs | HTTPS |
|----------|------|--------|
| Local / Docker | `http://host.docker.internal:8010` ou `http://ml:8010` | `FORCE_HTTPS=false` |
| Produção | `APP_URL=https://...` e preferir ML só na rede interna | `FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true` |

Se a API ML estiver em URL `https://...` com certificado válido, mantenha `PYTHON_API_VERIFY_SSL=true`.

## Teste rápido

```bash
# No Laravel (após preencher a chave):
php artisan tinker
>>> app(\App\Services\PythonApiService::class)->health();
>>> app(\App\Services\PythonApiService::class)->modelInfo();
```

Sem chave (API Python com `API_KEY` definida) → HTTP 401 nas rotas clínicas.

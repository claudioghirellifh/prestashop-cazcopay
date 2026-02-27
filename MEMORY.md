# MEMORY

Contexto vivo do modulo `cazcopay` (PrestaShop).

## Identidade

- Modulo: `modules/cazcopay`
- Workspace atual: `cazcoio`
- Loja local: `https://store.localhost`
- Documentacao API: `https://cazcopay.readme.io/reference/introducao`

## Estado atual (2026-02-27)

- Modulo principal em `cazcopay.php` (versao declarada `0.1.0`).
- Hooks principais registrados:
  - `paymentOptions`
  - `paymentReturn`
  - `displayOrderDetail`
  - `moduleRoutes`
- Metodos exibidos no checkout: PIX, Boleto e Cartao.
- Fluxo de criacao real de transacao implementado no front controller para PIX e Boleto.
- Webhook implementado em `controllers/front/webhook.php` com validacao de token e log persistente.
- Boleto no FO exibe imagem escaneavel (barras preta/branca) e linha digitavel no retorno e no detalhe do pedido.

## Estrutura tecnica

- Modulo principal: `cazcopay.php`
- Controllers:
  - `controllers/front/payment.php`
  - `controllers/front/webhook.php`
  - `controllers/front/barcode.php`
- Classes:
  - `classes/CazcoPayConfig.php`
  - `classes/CazcoPayApiClient.php`
  - `classes/CazcoPayLogger.php`
- Templates:
  - `views/templates/hook/option_pix.tpl`
  - `views/templates/hook/option_boleto.tpl`
  - `views/templates/hook/option_card.tpl`
  - `views/templates/front/payment_pix.tpl`
  - `views/templates/front/payment_boleto.tpl`
  - `views/templates/front/payment_card.tpl`
  - `views/templates/hook/order_detail_pix.tpl`
  - `views/templates/hook/order_detail_boleto.tpl`
  - `views/templates/hook/payment_return.tpl`

## Banco de dados do modulo

- Tabela `ps_cazcopay_order`
  - Guarda dados da transacao por pedido (metodo, transaction_id, QR code, url, expiracao, payload).
- Tabela `ps_cazcopay_webhook_log`
  - Guarda logs de postback (metodo HTTP, URI, IP, token valido, status HTTP, transacao, pedido, erro, payload).

## Configuracoes relevantes

- Ambiente e chaves:
  - `CAZCO_ENV` (`sandbox` ou `production`)
  - `CAZCO_SANDBOX_SK`, `CAZCO_SANDBOX_PK`
  - `CAZCO_PROD_SK`, `CAZCO_PROD_PK`
- Toggle de metodos:
  - `CAZCO_ENABLE_PIX`
  - `CAZCO_ENABLE_BOLETO`
  - `CAZCO_ENABLE_CARD`
- Parcelamento:
  - `CAZCO_INSTALLMENTS_MAX`
  - `CAZCO_INSTALLMENT_<N>_INTEREST` (1..12)
  - `CAZCO_INSTALLMENT_<N>_MIN` (1..12)
- Webhook:
  - `CAZCO_WEBHOOK_SECRET`
- Estado de pedido PIX:
  - `CAZCO_OS_PIX`
- Estado de pedido Boleto:
  - `CAZCO_OS_BOLETO`

## Fluxo de pagamento atual

- PIX:
  - `controllers/front/payment.php` monta payload e chama `CazcoPayApiClient::createTransaction()` em `/transactions`.
  - Ao sucesso, cria pedido em estado "Aguardando pagamento PIX" e salva dados em `ps_cazcopay_order`.
  - Redireciona para `order-confirmation`.
- Boleto:
  - `controllers/front/payment.php` monta payload e chama `CazcoPayApiClient::createTransaction()` em `/transactions` com `paymentMethod=boleto`.
  - Ao sucesso, cria pedido em estado "Aguardando pagamento Boleto" e salva linha digitavel/link/expiracao em `ps_cazcopay_order`.
  - Redireciona para `order-confirmation`.
  - Codigo de barras prioriza `payload.boleto.barcode`; fallback converte linha digitavel (47 digitos) para formato de barras (44 digitos).
  - A imagem de barras e gerada por `controllers/front/barcode.php` com `TCPDFBarcode` no tipo `I25`.
  - Em cenarios de sandbox com codigo curto, a imagem e gerada em fallback (`C128`) para manter leitura por camera.
  - Bloco numerico duplicado de codigo de barras foi removido do FO (retorno/detalhe), mantendo layout mais limpo.
- Cartao:
  - Opcoes e templates existem; integracao completa com criacao real de transacao ainda precisa evoluir conforme backlog.

## Fluxo de webhook atual

- Rotas aceitas:
  - Amigavel: `/cazcopay/webhook/{token}`
  - Fallback: `index.php?fc=module&module=cazcopay&controller=webhook&token=...`
- Regras:
  - So aceita `POST`.
  - Token invalido retorna `404`.
  - Metodo invalido retorna `405`.
- Efeito de status:
  - Se `status=paid`, sincroniza pedido para `PS_OS_PAYMENT`.
- Persistencia:
  - Sempre tenta registrar log em `ps_cazcopay_webhook_log`.
  - Token em URI/query e mascarado no log persistido.
  - Atualizacao de payload no pedido preserva dados ja salvos (linha/link/expiracao) quando o postback nao traz esses campos.

## Operacao diaria

- Rodar comandos a partir de `/home/claudio/Workspace/DockeZ`.
- Limpar cache apos alterar PHP/TPL:
  - `docker compose --project-name cazcoio -f docker-compose.yml -f workspaces/cazcoio/compose.yml exec cazcoio-php bash -lc 'rm -rf /var/www/html/sites/store.localhost/public/var/cache/*'`
- Ver logs PHP:
  - `docker compose --project-name cazcoio -f docker-compose.yml -f workspaces/cazcoio/compose.yml logs --tail=200 cazcoio-php`
- Smoke test de checkout:
  - abrir `https://store.localhost/index.php?controller=order`

## Acordos para futuras features

- Manter `CazcoPayLogger::log` nos pontos criticos.
- Ao concluir feature:
  - atualizar `AGENTS.md` do modulo.
  - atualizar `dev_notes.md` (secoes de status e proximos passos).

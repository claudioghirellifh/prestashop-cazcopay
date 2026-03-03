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
- Fluxo de criacao real de transacao implementado no front controller para PIX, Boleto e Cartao.
- Webhook implementado em `controllers/front/webhook.php` com validacao de token e log persistente.
- Boleto no FO exibe imagem escaneavel (barras preta/branca) e linha digitavel no retorno e no detalhe do pedido.
- Cartao no checkout teve melhoria de UX/alinhamento em `views/templates/hook/option_card.tpl`.
- Cartao no checkout possui campo `CPF do titular` com mascara no front e envio normalizado para API.
- `views/templates/front/payment_card.tpl` atua como fallback de erro e orientacao quando necessario.
- BO do modulo possui abas `Configuracoes`, `Logs postback`, `Transacoes` e `Status`.

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
- Mapeamento de status:
  - `CAZCO_STATUS_MAP` (JSON)
- Estado de pedido PIX:
  - `CAZCO_OS_PIX`
- Estado de pedido Boleto:
  - `CAZCO_OS_BOLETO`
- Estado de pedido Cartao:
  - `CAZCO_OS_CARD`

## Fluxo de pagamento atual

- Status de transacao pode aplicar o de-para configurado na aba `Status` antes do fallback por metodo.
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
  - `controllers/front/payment.php` cria transacao real com `paymentMethod=credit_card`.
  - Ao sucesso, cria pedido em `PS_OS_PAYMENT` (quando API retorna `paid`) ou em estado "Aguardando pagamento Cartao" (`CAZCO_OS_CARD`).
  - Salva `transaction_id` e payload em `ps_cazcopay_order` e redireciona para `order-confirmation`.
  - Formulario do checkout foi refinado visualmente e inclui CPF do titular com mascara.
  - CPF do titular e obrigatorio para cartao e enviado no padrao da API:
    - `customer.document.number` (somente digitos)
    - `customer.document.type = cpf`
  - Campos do cartao sao sincronizados para o formulario real de confirmacao do PrestaShop via hidden inputs no submit.
  - `payment_card.tpl` mostra mensagem de erro amigavel (com detalhe tecnico) apenas quando houver falha no processamento.

## Fluxo de webhook atual

- Rotas aceitas:
  - Amigavel: `/cazcopay/webhook/{token}`
  - Fallback: `index.php?fc=module&module=cazcopay&controller=webhook&token=...`
- Regras:
  - So aceita `POST`.
  - Token invalido retorna `404`.
  - Metodo invalido retorna `405`.
- Efeito de status:
  - Status recebidos sao avaliados no de-para configurado na aba `Status`.
  - Sem mapeamento, nao altera o pedido.
- Persistencia:
  - Sempre tenta registrar log em `ps_cazcopay_webhook_log`.
  - Token em URI/query e mascarado no log persistido.
  - Atualizacao de payload no pedido preserva dados ja salvos (linha/link/expiracao) quando o postback nao traz esses campos.

## BO - Transacoes

- Aba `Transacoes` lista dados de `ps_cazcopay_order` com paginacao.
- Colunas principais:
  - Data
  - Pedido
  - Metodo
  - Transacao
  - Valor
  - Status do pedido
  - Payload
- O payload abre em modal (botao `Ver`) para nao quebrar layout da tabela.

## BO - Status

- Aba `Status` define o de-para entre status Cazco Pay e estados do PrestaShop.
- Aplicado no retorno de transacao e no postback.

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

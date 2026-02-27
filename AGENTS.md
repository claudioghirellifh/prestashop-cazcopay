# AGENTS.md

## Visao geral
- Modulo de pagamento CazcoPay para PrestaShop.
- Documentacao oficial (API v1.0): https://cazcopay.readme.io/reference/introducao

## Setup rapido
- Modulo: `modules/cazcopay`
- Checkout local: `http://local.presta/index.php?controller=order`
- Limpar cache apos alterar PHP/Templates:
  - `docker compose exec ps_php rm -rf /var/www/presta/var/cache/*`

## Observabilidade e logs
- PHP-FPM: `docker compose logs --tail 200 ps_php`
- Nginx: `tail -n 200 nginx/logs/sites/presta/error.log`
- Mantenha `CazcoPayLogger::log` nos fluxos criticos.

## Testes e validacoes rapidas
- Hook de pagamento (container):
  ```bash
  docker compose exec ps_php sh -lc 'php -d display_errors=1 -r "
    require \"/var/www/presta/config/config.inc.php\";
    \$ctx = Context::getContext();
    \$ctx->cart = new Cart();
    \$ctx->currency = new Currency((int)Configuration::get(\"PS_CURRENCY_DEFAULT\"));
    \$ctx->link = new Link();
    \$ctx->shop = new Shop((int)Configuration::get(\"PS_SHOP_DEFAULT\"));
    \$ctx->language = new Language((int)Configuration::get(\"PS_LANG_DEFAULT\"));
    \$module = Module::getInstanceByName(\"cazcopay\");
    \$options = \$module->hookPaymentOptions([]);
    var_dump(count(\$options));
  "'
  ```

## Estrutura principal do modulo
- Modulo principal: `cazcopay.php`
- Controllers:
  - `controllers/front/payment.php` (fluxo PIX e Boleto)
  - `controllers/front/webhook.php` (endpoint de webhook)
  - `controllers/front/barcode.php` (imagem de codigo de barras do boleto)
- Classes:
  - `classes/CazcoPayApiClient.php`
  - `classes/CazcoPayConfig.php`
  - `classes/CazcoPayLogger.php`
- Templates:
  - `views/templates/hook/option_pix.tpl`
  - `views/templates/hook/option_boleto.tpl`
  - `views/templates/hook/option_card.tpl`
  - `views/templates/front/payment_pix.tpl`
  - `views/templates/front/payment_boleto.tpl`
  - `views/templates/front/payment_card.tpl`
  - `views/templates/hook/order_detail_boleto.tpl`

## Boas praticas
- Sempre revisar a doc da API ao iniciar uma nova sessao.
- Limpar cache e checar logs apos alteracoes.
- Validar checkout e opcoes de pagamento antes de encerrar a tarefa.

## Atualizacoes recentes
- Mapeamento de documento no BO lista campos personalizados (cliente/endereco) no select principal.
- Quando o modulo `cadastrobrasilcazco` nao existe, nao lista opcoes extras.
- Removido mapeamento de campos do `agcustomers`; o modulo agora usa apenas campos nativos e `cadastrobrasilcazco`.
- URL de postback agora suporta formato amigavel (`/cazcopay/webhook/{token}`) e formato sem amigavel (`index.php?...&controller=webhook&token=...`).
- BO separado em abas `Configurações` e `Logs postback`, com listagem paginada e limpeza dos logs de webhook.
- Novo log persistente de postback na tabela `ps_cazcopay_webhook_log` com status HTTP, token válido, transação, pedido, erro e payload.
- Corrigido warning no FO de valor PIX (`Non-numeric value encountered`) ajustando precedencia no Smarty com parenteses no calculo `amount/100`.
- Expiração PIX no FO padronizada para BR (`dd/mm/aaaa` ou `dd/mm/aaaa HH:mm`) via formatação no backend (`getPixData`).
- FO PIX recebeu mais respiro lateral (padding interno) nos templates de retorno, detalhe do pedido e tela PIX, mantendo largura alinhada ao restante da página (sem `max-width` centralizado).
- Fluxo Boleto integrado no front/payment: cria transação real em `/transactions`, cria pedido em estado "Aguardando pagamento Boleto", salva linha digitável/link/expiração em `ps_cazcopay_order` e redireciona para `order-confirmation`.
- Novo estado de pedido configurável para boleto (`CAZCO_OS_BOLETO`) com criação automática no módulo.
- Tela de retorno (`payment_return.tpl`) e detalhe do pedido agora exibem dados de boleto (linha digitável, botão de cópia e link do boleto) quando `payment_method=boleto`.
- Webhook ajustado para preservar dados já salvos de pagamento e aproveitar campos de `data.boleto`/`data.pix` quando disponíveis (evita apagar linha/link existentes).
- Boleto no FO: agora exibe também `Código de barras` no retorno e no detalhe do pedido; quando a API não envia `barcode`, o módulo deriva a partir da linha digitável (47 -> 44 dígitos).
- A imagem escaneável (barras preta/branca) é gerada no backend via `TCPDFBarcode` (`I25`) e servida pelo front controller `barcode`, evitando dependência externa.
- Ajuste de UX no FO do boleto: removido bloco duplicado com código de barras numérico e botão de cópia; mantido somente imagem escaneável + linha digitável.

## Regras de atualizacao
- A cada feature commitada, atualizar este `AGENTS.md`.
- Quando concluir uma feature, atualizar `dev_notes.md` (secoes "O que ja deu certo" e "Proximos passos").

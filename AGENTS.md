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
  - `controllers/front/payment.php` (fluxo PIX atual)
  - `controllers/front/webhook.php` (endpoint de webhook)
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

## Boas praticas
- Sempre revisar a doc da API ao iniciar uma nova sessao.
- Limpar cache e checar logs apos alteracoes.
- Validar checkout e opcoes de pagamento antes de encerrar a tarefa.

## Atualizacoes recentes
- Mapeamento de documento no BO lista campos personalizados (cliente/endereco) no select principal.
- Quando o modulo `cadastrobrasilcazco` nao existe, nao lista opcoes extras.

## Regras de atualizacao
- A cada feature commitada, atualizar este `AGENTS.md`.
- Quando concluir uma feature, atualizar `dev_notes.md` (secoes "O que ja deu certo" e "Proximos passos").

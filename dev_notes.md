# Dev Notes – Cazco Pay Module

## Contexto Atual
- Documentação oficial da Cazco Pay (API v1.0): <https://cazcopay.readme.io/reference/introducao>.  
  > Sempre que iniciar uma nova sessão, revisar ou relembrar essa doc para garantir que informações de endpoints, payloads e regras estejam atualizadas.
- Integramos e estabilizamos o módulo `modules/cazcopay`.
- Controle de parcelamento configurável no BO:
  - Máximo de parcelas (1–12).
  - Juros (%) por parcela.
  - Valor mínimo do pedido para habilitar cada parcela.
- Checkout aplica juros e filtra parcelas de acordo com o valor mínimo, atualizando o resumo (“Total do pedido”) conforme seleção.
- Hooks de pagamento (`hookPaymentOptions`) retornam PIX, Boleto e Cartão com detalhes inline.
- Transações PIX geram pedido automaticamente, usando estado “Aguardando pagamento PIX”, e armazenam QR Code/URL/expiração em `ps_cazcopay_order` para exibição posterior.
- Carregamento de `CazcoPayConfig` e `CazcoPayLogger` protegido com fallback (`_PS_MODULE_DIR_` → `__DIR__`) e mensagens no `error_log` em caso de falha.
- Templates em uso:  
  - `views/templates/hook/option_pix.tpl`  
  - `views/templates/hook/option_boleto.tpl`  
  - `views/templates/hook/option_card.tpl` (formulário com cálculo dinâmico de parcelas, juros e valor mínimo).

## O que já deu certo
- ✅ Controle de parcelas completo: BO (máximo + juros + valor mínimo) com máscaras automáticas e persistência em `Configuration`.
- ✅ Checkout recalcula o valor total/parcela, exibe detalhes conforme seleção e respeita os limites mínimos configurados.
- ✅ Fluxo PIX integrado à API `/transactions`: gera PIX, mostra “copia e cola”, link, expiração e registra logs detalhados.
- ✅ PIX, Boleto e Cartão aparecem no checkout sem erro 500.
- ✅ Logs do Nginx estão habilitados (`nginx/logs/sites/presta`).

## Próximos Passos Sugeridos
1. **Integração real com a API da Cazco Pay**  
   - PIX & Boleto: criar transação, exibir link/QR e mapear status do pedido.  
   - Cartão: implementar tokenização (PK) + criação da transação, tratar respostas e erros.
2. **Postbacks/Webhooks**  
   - Criar endpoint para receber eventos e atualizar o status do pedido com idempotência.
3. **Estados do pedido**  
   - Definir status “Aguardando pagamento (PIX/Boleto)”, “Pago”, etc., com ícones.
4. **Logs e observabilidade**  
   - Manter `CazcoPayLogger::log` nos fluxos críticos.  
   - Verificar `docker compose logs ps_php` e `nginx/logs/sites/presta/error.log` após alterações.
5. **Cache**  
   - Sempre limpar (`docker compose exec ps_php rm -rf /var/www/presta/var/cache/*`) depois de editar arquivos PHP/Templates.

## Como Retomar a Próxima Sessão
1. Ler este `dev_notes.md` (garantir que as próximas instruções usem esse contexto).
2. Validar que o checkout abre em `http://local.presta/index.php?controller=order` com o módulo ativo.
3. Verificar logs recentes:
   - PHP-FPM: `docker compose logs --tail 200 ps_php`.
   - Nginx: `tail -n 200 nginx/logs/sites/presta/error.log`.
4. Confirmar que `git status` está limpo ou saber exatamente o que precisa ser commitado antes de prosseguir.

## Checklist Rápido (antes de codar)
- [ ] Cache limpo?  
- [ ] Logs sem erro?  
- [ ] Checkout renderiza opções Cazco Pay?  
- [ ] Dev Notes lidos?  

## Referências Úteis
- Programa de testes rápido (via container):
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
- Limpeza de cache:
  ```bash
  docker compose exec ps_php rm -rf /var/www/presta/var/cache/*
  ```

> Sempre que uma feature for concluída com sucesso, atualizar este arquivo imediatamente (seção “O que já deu certo” + eventuais próximos passos).*** End Patch

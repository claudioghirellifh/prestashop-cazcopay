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
- Transações PIX e Boleto geram pedido automaticamente:
  - PIX usa estado “Aguardando pagamento PIX”.
  - Boleto usa estado “Aguardando pagamento Boleto”.
  - Ambos armazenam dados de cobrança em `ps_cazcopay_order` para exibição posterior no retorno/detalhe do pedido.
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
- ✅ Mapeamento de documento no BO inclui campos personalizados do módulo de cadastro brasileiro (com fallback seguro).
- ✅ Removido mapeamento de documento por campos do `agcustomers` (CPF/CNPJ/document_number/person_type).
- ✅ URL de postback aceita formato amigável e não amigável, e o BO exibe as duas opções para configuração.
- ✅ Configuração do módulo separada em abas (`Configurações` e `Logs postback`) para operação diária.
- ✅ Webhook grava logs persistentes em tabela própria com paginação/limpeza no BO.
- ✅ Logs do Nginx estão habilitados (`nginx/logs/sites/presta`).
- ✅ Corrigido warning no FO do retorno PIX (`Non-numeric value encountered`) ao formatar valor, ajustando a expressão Smarty para aplicar `number_format` no resultado da divisão.
- ✅ Campo "Expira em" no FO do PIX padronizado para formato BR (`dd/mm/aaaa` ou `dd/mm/aaaa HH:mm`) no backend.
- ✅ Layout FO do PIX ajustado com mais respiro lateral (padding), preservando a largura alinhada ao grid da página para manter consistência visual com os blocos nativos.
- ✅ Fluxo de boleto integrado ao `front/payment`: cria transação real, cria pedido, salva linha digitável/link/expiração e redireciona para `order-confirmation`.
- ✅ Retorno do pedido e detalhe do pedido agora exibem informações de boleto quando `payment_method=boleto`.
- ✅ Webhook passou a preservar dados já salvos de cobrança e preencher campos de `data.boleto`/`data.pix` quando disponíveis.
- ✅ Boleto agora exibe também “Código de barras” no FO (retorno/detalhe), com fallback por conversão da linha digitável quando a API não retornar o `barcode`.
- ✅ Boleto agora exibe a imagem escaneável do código de barras (barras preta/branca) no FO, gerada no backend via `TCPDFBarcode` (`I25`) pelo endpoint `controllers/front/barcode.php`.
- ✅ Ajuste de UX no boleto: removido bloco numérico duplicado de “Código de barras” no FO; mantidos imagem escaneável e linha digitável.

## Próximos Passos Sugeridos
1. **Integração de Cartão com a API da Cazco Pay**  
   - Implementar tokenização (PK) + criação da transação, tratar respostas e erros.
2. **Postbacks/Webhooks**  
   - Criar endpoint para receber eventos e atualizar o status do pedido com idempotência.
3. **Estados do pedido**  
   - Revisar ícones/cores e eventuais e-mails dos estados “Aguardando pagamento (PIX/Boleto)” e “Pago”.
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

> Sempre que uma feature for concluída com sucesso, atualizar este arquivo imediatamente (seção “O que já deu certo” + eventuais próximos passos).

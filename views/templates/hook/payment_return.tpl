{* Tela de retorno após pagamento/validação *}
{literal}
<style>
  .cazcopay-return .cazcopay-pix-main {
    max-width: 100%;
    margin: 0;
  }
  .cazcopay-return .cazcopay-pix-card-body {
    padding: 1.25rem;
  }
  @media (max-width: 768px) {
    .cazcopay-return .cazcopay-pix-card-body {
      padding: 1rem;
    }
  }
</style>
{/literal}
<section class="cazcopay-return">
  <p>{l s='Obrigado por comprar na %s!' sprintf=[$shop_name] mod='cazcopay'}</p>

  {if isset($cazco_order) && $cazco_order.payment_method == 'pix'}
    <div class="card mt-3 cazcopay-pix-main">
      <div class="card-header">
        <h3>{l s='Pagamento via PIX' mod='cazcopay'}</h3>
      </div>
      <div class="card-body cazcopay-pix-card-body">
        {if isset($cazco_order.amount)}
          <p>
            <strong>{l s='Valor total:' mod='cazcopay'}</strong>
            {$currency_sign|escape:'htmlall'} {(($cazco_order.amount|default:0)/100)|number_format:2:',':'.'}
          </p>
        {/if}
        {if $cazco_order.pix_expiration_formatted}
          <p>
            <strong>{l s='Expira em:' mod='cazcopay'}</strong>
            {$cazco_order.pix_expiration_formatted|escape:'htmlall'}
          </p>
        {/if}
        {if $cazco_order.qrcode_image}
          <div class="mb-3 text-center">
            <img src="{$cazco_order.qrcode_image|escape:'htmlall'}" alt="{l s='QRCode PIX' mod='cazcopay'}" class="img-fluid" style="max-width:320px;">
          </div>
        {/if}
        <div class="mb-3">
          <label class="form-label" for="cazco-pix-code">
            {l s='PIX Copia e Cola' mod='cazcopay'}
          </label>
          <textarea class="form-control" id="cazco-pix-code" rows="4" readonly>{$cazco_order.pix_qrcode|escape:'htmlall'}</textarea>
          <button type="button" class="btn btn-secondary mt-2" id="cazco-copy-pix" data-copy-target="cazco-pix-code" data-label-copy="{l s='Copiar código' mod='cazcopay'}" data-label-copied="{l s='Copiado!' mod='cazcopay'}">
            {l s='Copiar código' mod='cazcopay'}
          </button>
        </div>
        {if $cazco_order.pix_url}
          <p>
            <a class="btn btn-primary" href="{$cazco_order.pix_url|escape:'htmlall'}" target="_blank" rel="noopener">
              {l s='Abrir link PIX' mod='cazcopay'}
            </a>
          </p>
        {/if}
        {if $cazco_order.transaction_id}
          <p class="text-muted">
            <small>{l s='Transação:' mod='cazcopay'} {$cazco_order.transaction_id|escape:'htmlall'}</small>
          </p>
        {/if}
        <p class="mt-3">
          {l s='O pedido ficará como “Aguardando pagamento (PIX)” até a confirmação. Você receberá notificações assim que o pagamento for reconhecido.' mod='cazcopay'}
        </p>
      </div>
    </div>
  {elseif isset($cazco_order) && $cazco_order.payment_method == 'boleto'}
    <div class="card mt-3 cazcopay-pix-main">
      <div class="card-header">
        <h3>{l s='Pagamento via Boleto' mod='cazcopay'}</h3>
      </div>
      <div class="card-body cazcopay-pix-card-body">
        {if isset($cazco_order.amount)}
          <p>
            <strong>{l s='Valor total:' mod='cazcopay'}</strong>
            {$currency_sign|escape:'htmlall'} {(($cazco_order.amount|default:0)/100)|number_format:2:',':'.'}
          </p>
        {/if}
        {if $cazco_order.payment_expiration_formatted}
          <p>
            <strong>{l s='Vencimento:' mod='cazcopay'}</strong>
            {$cazco_order.payment_expiration_formatted|escape:'htmlall'}
          </p>
        {/if}
        {if $cazco_order.boleto_barcode_image_url}
          <div class="mb-3">
            <label class="form-label">{l s='Código de barras' mod='cazcopay'}</label>
            <div>
              <img src="{$cazco_order.boleto_barcode_image_url|escape:'htmlall'}" alt="{l s='Código de barras do boleto' mod='cazcopay'}" class="img-fluid" style="max-width:100%;background:#fff;padding:6px;border:1px solid #ddd;">
            </div>
          </div>
        {/if}
        <div class="mb-3">
          <label class="form-label" for="cazco-boleto-line">
            {l s='Linha digitável' mod='cazcopay'}
          </label>
          <textarea class="form-control" id="cazco-boleto-line" rows="3" readonly>{$cazco_order.boleto_digitable_line|default:$cazco_order.pix_qrcode|escape:'htmlall'}</textarea>
          <button type="button" class="btn btn-secondary mt-2" id="cazco-copy-boleto" data-copy-target="cazco-boleto-line" data-label-copy="{l s='Copiar linha digitável' mod='cazcopay'}" data-label-copied="{l s='Copiado!' mod='cazcopay'}">
            {l s='Copiar linha digitável' mod='cazcopay'}
          </button>
        </div>
        {if $cazco_order.pix_url}
          <p>
            <a class="btn btn-primary" href="{$cazco_order.pix_url|escape:'htmlall'}" target="_blank" rel="noopener">
              {l s='Abrir boleto' mod='cazcopay'}
            </a>
          </p>
        {/if}
        {if $cazco_order.transaction_id}
          <p class="text-muted">
            <small>{l s='Transação:' mod='cazcopay'} {$cazco_order.transaction_id|escape:'htmlall'}</small>
          </p>
        {/if}
        <p class="mt-3">
          {l s='O pedido ficará como “Aguardando pagamento (Boleto)” até a compensação. Você receberá notificações quando houver atualização.' mod='cazcopay'}
        </p>
      </div>
    </div>
  {else}
    <p>{l s='Você receberá atualizações do pagamento em instantes.' mod='cazcopay'}</p>
  {/if}
</section>

{if isset($cazco_order) && ($cazco_order.payment_method == 'pix' || $cazco_order.payment_method == 'boleto')}
{literal}
<script>
(function () {
  var copyButtons = document.querySelectorAll('[data-copy-target]');
  if (!copyButtons.length) {
    return;
  }

  copyButtons.forEach(function (copyButton) {
    var targetId = copyButton.getAttribute('data-copy-target') || '';
    var textarea = targetId ? document.getElementById(targetId) : null;
    if (!textarea) {
      return;
    }
    var defaultLabel = copyButton.getAttribute('data-label-copy') || copyButton.innerText;
    var copiedLabel = copyButton.getAttribute('data-label-copied') || 'Copiado!';
    copyButton.addEventListener('click', function () {
      textarea.select();
      textarea.setSelectionRange(0, textarea.value.length);
      try {
        var ok = document.execCommand('copy');
        copyButton.innerText = ok ? copiedLabel : defaultLabel;
        if (ok) {
          setTimeout(function () {
            copyButton.innerText = defaultLabel;
          }, 2000);
        }
      } catch (err) {}
    });
  });
})();
</script>
{/literal}
{/if}

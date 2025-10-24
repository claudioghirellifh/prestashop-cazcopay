{* Tela de retorno após pagamento/validação *}
<section class="cazcopay-return">
  <p>{l s='Obrigado por comprar na %s!' sprintf=[$shop_name] mod='cazcopay'}</p>

  {if isset($cazco_order) && $cazco_order.payment_method == 'pix'}
    <div class="card mt-3">
      <div class="card-header">
        <h3>{l s='Pagamento via PIX' mod='cazcopay'}</h3>
      </div>
      <div class="card-body">
        {if isset($cazco_order.amount)}
          <p>
            <strong>{l s='Valor total:' mod='cazcopay'}</strong>
            {$currency_sign|escape:'htmlall'} {$cazco_order.amount/100|number_format:2:',':'.'}
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
          <button type="button" class="btn btn-secondary mt-2" id="cazco-copy-pix" data-label-copy="{l s='Copiar código' mod='cazcopay'}" data-label-copied="{l s='Copiado!' mod='cazcopay'}">
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
  {else}
    <p>{l s='Você receberá atualizações do pagamento em instantes.' mod='cazcopay'}</p>
  {/if}
</section>

{if isset($cazco_order) && $cazco_order.payment_method == 'pix'}
{literal}
<script>
(function () {
  var copyButton = document.getElementById('cazco-copy-pix');
  var textarea = document.getElementById('cazco-pix-code');
  if (!copyButton || !textarea) {
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
    } catch (err) {
      console.error('Erro ao copiar PIX', err);
    }
  });
})();
</script>
{/literal}
{/if}

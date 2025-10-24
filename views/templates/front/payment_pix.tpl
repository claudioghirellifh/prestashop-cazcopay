{extends file='page.tpl'}

{block name='page_content'}
  <div class="card">
    <div class="card-header">
      <h3>Cazco Pay — PIX</h3>
    </div>
    <div class="card-body">
      {if isset($cazco_error)}
        <div class="alert alert-danger" role="alert">
          {$cazco_error|escape:'htmlall'}
        </div>
        <a class="btn btn-outline-primary" href="{$link->getPageLink('order',true,null,['step'=>3])}">
          {l s='Voltar ao checkout' mod='cazcopay'}
        </a>
      {elseif isset($cazco_pix)}
        <p>
          <strong>{l s='Valor total:' mod='cazcopay'}</strong>
          {$currency_sign|escape:'htmlall'} {$cazco_pix.amount_cents/100|number_format:2:',':'.'}
        </p>
        {if $cazco_pix.expiration}
          <p>
            <strong>{l s='Expira em:' mod='cazcopay'}</strong>
            {$cazco_pix.expiration|escape:'htmlall'}
          </p>
        {/if}
        <div class="mb-3">
          <label class="form-label" for="cazco-pix-code">
            {l s='PIX Copia e Cola' mod='cazcopay'}
          </label>
          <textarea class="form-control" id="cazco-pix-code" rows="4" readonly>{$cazco_pix.qrcode|escape:'htmlall'}</textarea>
          <button type="button" class="btn btn-secondary mt-2" id="cazco-copy-pix" data-label-copy="{l s='Copiar código' mod='cazcopay'}" data-label-copied="{l s='Copiado!' mod='cazcopay'}">
            {l s='Copiar código' mod='cazcopay'}
          </button>
        </div>
        {if $cazco_pix.url}
          <p>
            <a class="btn btn-primary" href="{$cazco_pix.url|escape:'htmlall'}" target="_blank" rel="noopener">
              {l s='Abrir link PIX' mod='cazcopay'}
            </a>
          </p>
        {/if}
        {if $cazco_pix.transaction_id}
          <p class="text-muted">
            <small>{l s='Transação:' mod='cazcopay'} {$cazco_pix.transaction_id|escape:'htmlall'}</small>
          </p>
        {/if}
        <p class="mt-3">
          {l s='Após a confirmação do pagamento, seu pedido será liberado automaticamente.' mod='cazcopay'}
        </p>
        <a class="btn btn-outline-primary" href="{$link->getPageLink('order',true,null,['step'=>3])}">
          {l s='Voltar ao checkout' mod='cazcopay'}
        </a>
      {else}
        <p>{l s='Gerando seu PIX, aguarde alguns instantes...' mod='cazcopay'}</p>
      {/if}
    </div>
  </div>
{/block}

{if isset($cazco_pix)}
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

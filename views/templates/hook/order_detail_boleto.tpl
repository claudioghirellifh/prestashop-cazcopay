{if isset($cazco_order)}
{literal}
<style>
  .cazcopay-order-boleto.cazcopay-pix-main {
    max-width: 100%;
    margin: 0;
  }
  .cazcopay-order-boleto .cazcopay-pix-card-body {
    padding: 1.25rem;
  }
  @media (max-width: 768px) {
    .cazcopay-order-boleto .cazcopay-pix-card-body {
      padding: 1rem;
    }
  }
</style>
{/literal}
<section class="cazcopay-order-boleto cazcopay-pix-main card mt-3">
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
      <label class="form-label" for="cazco-order-boleto-line">
        {l s='Linha digitável' mod='cazcopay'}
      </label>
      <textarea class="form-control" id="cazco-order-boleto-line" rows="3" readonly>{$cazco_order.boleto_digitable_line|default:$cazco_order.pix_qrcode|escape:'htmlall'}</textarea>
      <button type="button" class="btn btn-secondary mt-2" data-copy-target="cazco-order-boleto-line" data-label-copy="{l s='Copiar linha digitável' mod='cazcopay'}" data-label-copied="{l s='Copiado!' mod='cazcopay'}">
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
      {l s='O pedido será liberado automaticamente após a compensação do boleto. Você receberá notificações por e-mail.' mod='cazcopay'}
    </p>
  </div>
</section>

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

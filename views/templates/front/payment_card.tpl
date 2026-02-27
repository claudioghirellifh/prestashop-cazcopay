{extends file='page.tpl'}

{block name='page_content'}
  <div class="card">
    <div class="card-header">
      <h3>Cazco Pay — Cartão de crédito</h3>
    </div>
    <div class="card-body">
      {if !empty($cazco_error)}
        <div class="alert alert-danger" role="alert">
          {$cazco_error|escape:'html':'UTF-8'}
          {if !empty($cazco_error_detail)}
            <br><small>{$cazco_error_detail|escape:'html':'UTF-8'}</small>
          {/if}
        </div>
      {else}
        <div class="alert alert-info" role="alert">
          {l s='Os dados do cartão devem ser preenchidos no checkout, na opção de pagamento Cazco Pay.' mod='cazcopay'}
        </div>
      {/if}
      <a class="btn btn-secondary" href="{$link->getPageLink('order',true,null,['step'=>3])}">
        {l s='Voltar ao checkout' mod='cazcopay'}
      </a>
    </div>
  </div>
{/block}

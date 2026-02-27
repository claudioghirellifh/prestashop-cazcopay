{extends file='page.tpl'}

{block name='page_content'}
  <div class="card">
    <div class="card-header">
      <h3>Cazco Pay — Boleto</h3>
    </div>
    <div class="card-body">
      {if isset($cazco_error)}
        <div class="alert alert-danger" role="alert">
          {$cazco_error|escape:'htmlall'}
        </div>
      {else}
        <p>{l s='Gerando seu boleto, aguarde alguns instantes...' mod='cazcopay'}</p>
      {/if}
      <a class="btn btn-outline-primary" href="{$link->getPageLink('order',true,null,['step'=>3])}">
        {l s='Voltar ao checkout' mod='cazcopay'}
      </a>
    </div>
  </div>
{/block}

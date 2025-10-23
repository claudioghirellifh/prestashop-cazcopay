{extends file='page.tpl'}

{block name='page_content'}
  <div class="card">
    <div class="card-header">
      <h3>Cazco Pay</h3>
    </div>
    <div class="card-body">
      <p>Método selecionado: <strong>{$cazco_method|escape:'htmlall':'UTF-8'}</strong></p>
      <p>Este é um passo inicial. Na próxima etapa integraremos a criação da transação na API e o redirecionamento/retorno conforme o método.</p>
      <a class="btn btn-primary" href="{$link->getPageLink('order',true,null,['step'=>3])}">Voltar ao checkout</a>
    </div>
  </div>
{/block}


{extends file='page.tpl'}

{block name='page_content'}
  <div class="card">
    <div class="card-header">
      <h3>Cazco Pay — PIX</h3>
    </div>
    <div class="card-body">
      <p>O PIX será gerado na próxima etapa, após confirmar seu pedido.</p>
      <ul>
        <li>Você verá o QR Code/Link PIX para pagamento imediato.</li>
        <li>O pedido ficará como “Aguardando pagamento (PIX)” até a confirmação.</li>
      </ul>
      <a class="btn btn-primary" href="{$link->getPageLink('order',true,null,['step'=>3])}">Voltar ao checkout</a>
    </div>
  </div>
{/block}


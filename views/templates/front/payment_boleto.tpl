{extends file='page.tpl'}

{block name='page_content'}
  <div class="card">
    <div class="card-header">
      <h3>Cazco Pay — Boleto</h3>
    </div>
    <div class="card-body">
      <p>O boleto será gerado na próxima etapa, após confirmar seu pedido.</p>
      <ul>
        <li>Você receberá a linha digitável e o link do boleto.</li>
        <li>O pedido ficará como “Aguardando pagamento (Boleto)” até a compensação.</li>
      </ul>
      <a class="btn btn-primary" href="{$link->getPageLink('order',true,null,['step'=>3])}">Voltar ao checkout</a>
    </div>
  </div>
{/block}


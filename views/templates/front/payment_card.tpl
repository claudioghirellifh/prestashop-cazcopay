{extends file='page.tpl'}

{block name='page_content'}
  <div class="card">
    <div class="card-header">
      <h3>Cazco Pay — Cartão de crédito</h3>
    </div>
    <div class="card-body">
      <p>Preencha os dados do cartão. A cobrança será efetuada na confirmação.</p>

      <form id="cazco-card-form" onsubmit="return false;">
        <div class="row">
          <div class="form-group col-md-8">
            <label for="cc-number">Número do cartão</label>
            <input class="form-control" id="cc-number" name="cc-number" inputmode="numeric" autocomplete="cc-number" placeholder="0000 0000 0000 0000" maxlength="23">
            <small class="text-muted">Bandeira: <span id="cc-brand">—</span></small>
          </div>
          <div class="form-group col-md-4">
            <label for="cc-holder">Nome impresso</label>
            <input class="form-control" id="cc-holder" name="cc-holder" autocomplete="cc-name" placeholder="NOME COMO NO CARTÃO">
          </div>
        </div>

        <div class="row">
          <div class="form-group col-md-3">
            <label for="cc-exp-month">Mês</label>
            <select class="form-control" id="cc-exp-month" name="cc-exp-month">
              {foreach from=[1,2,3,4,5,6,7,8,9,10,11,12] item=m}
                <option value="{$m}">{$m|string_format:"%02d"}</option>
              {/foreach}
            </select>
          </div>
          <div class="form-group col-md-3">
            <label for="cc-exp-year">Ano</label>
            <select class="form-control" id="cc-exp-year" name="cc-exp-year"></select>
          </div>
          <div class="form-group col-md-3">
            <label for="cc-cvv">CVV</label>
            <input class="form-control" id="cc-cvv" name="cc-cvv" inputmode="numeric" autocomplete="cc-csc" placeholder="CVV" maxlength="4">
          </div>
          <div class="form-group col-md-3">
            <label for="cc-installments">Parcelas</label>
            <select class="form-control" id="cc-installments" name="cc-installments"></select>
            <small class="text-muted">Total do pedido: <span id="order-total"></span></small>
          </div>
        </div>

        <div class="mt-3">
          <a class="btn btn-secondary" href="{$link->getPageLink('order',true,null,['step'=>3])}">Voltar ao checkout</a>
          <button type="button" class="btn btn-primary" id="cc-continue">Continuar</button>
        </div>
      </form>

      <script>
        (function() {
          const totalCents = parseInt('{$cart_total_cents|intval}', 10) || 0;
          const currency = '{$currency_iso|escape:'js'}' || 'BRL';
          const brandSpan = document.getElementById('cc-brand');
          const ccNum = document.getElementById('cc-number');
          const ccCvv = document.getElementById('cc-cvv');
          const yearSel = document.getElementById('cc-exp-year');
          const instSel = document.getElementById('cc-installments');
          const orderTotal = document.getElementById('order-total');

          // Formata moeda pt-BR
          function fmt(v) { try { return (v/100).toLocaleString('pt-BR', {style:'currency', currency}); } catch(e) { return 'R$ ' + (v/100).toFixed(2).replace('.',','); } }
          orderTotal.textContent = fmt(totalCents);

          // Preenche anos
          const now = new Date();
          const start = now.getFullYear();
          for (let y = 0; y < 15; y++) {
            const opt = document.createElement('option');
            opt.value = (start + y);
            opt.textContent = (start + y);
            yearSel.appendChild(opt);
          }

          // Parcelas 1..12 (sem juros no front; cálculo final no backend)
          for (let i = 1; i <= 12; i++) {
            const opt = document.createElement('option');
            opt.value = i;
            const per = Math.round(totalCents / i);
            opt.textContent = i + 'x de ' + fmt(per);
            instSel.appendChild(opt);
          }

          // Máscara simples número do cartão
          ccNum.addEventListener('input', function() {
            let v = this.value.replace(/\D/g,'').slice(0,19);
            this.value = v.replace(/(.{4})/g,'$1 ').trim();
            const brand = detectBrand(v);
            brandSpan.textContent = brand || '—';
            // CVV length por bandeira (amex=4, demais=3)
            ccCvv.maxLength = (brand === 'amex') ? 4 : 3;
          });

          // Detecção simples de bandeira
          function detectBrand(num) {
            if (/^4[0-9]{6,}$/.test(num)) return 'visa';
            if (/^5[1-5][0-9]{5,}$/.test(num)) return 'mastercard';
            if (/^3[47][0-9]{5,}$/.test(num)) return 'amex';
            if (/^(606282|3841)/.test(num)) return 'hipercard';
            if (/^(4011(78|79)|431274|438935|451416|457393|504175|627780|636297|636368|650\d{2}|651\d{2}|652\d{2})/.test(num)) return 'elo';
            return '';
          }

          document.getElementById('cc-continue').addEventListener('click', function() {
            alert('Dados preenchidos. Na próxima etapa faremos a tokenização e a criação da transação.');
          });
        })();
      </script>
    </div>
  </div>
{/block}


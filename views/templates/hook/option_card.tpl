<div class="cazcopay-option cazcopay-option--card">
  <p>Preencha os dados do cartão. A cobrança será efetuada na confirmação.</p>

  <form id="cazco-card-form" onsubmit="return false;" class="mb-2">
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
          {for $m=1 to 12}
            <option value="{$m}">{$m}</option>
          {/for}
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
        <small class="text-muted">
          Total do pedido: <span id="order-total"></span><br>
          <span id="installment-details"></span>
        </small>
      </div>
    </div>
  </form>

  <script>
    var cazcoPayCardInit = function() {
      if (window.__cazcoCardInit) {
        return;
      }
      window.__cazcoCardInit = true;
      var totalCents = {$cart_total_cents|intval};
      var currency = '{$currency_iso|escape:"javascript"}' || 'BRL';
      var installmentsData = {$installments_config_json|default:'[]' nofilter};
      {literal}
      if (!Array.isArray(installmentsData) || !installmentsData.length) {
        installmentsData = [{number: 1, interest: 0, min: 0}];
      }
      var brandSpan = document.getElementById('cc-brand');
      var ccNum = document.getElementById('cc-number');
      var ccCvv = document.getElementById('cc-cvv');
      var yearSel = document.getElementById('cc-exp-year');
      var instSel = document.getElementById('cc-installments');
      var orderTotal = document.getElementById('order-total');
      var detailsEl = document.getElementById('installment-details');

      function fmt(v) {
        try {
          return (v / 100).toLocaleString('pt-BR', {style: 'currency', currency: currency});
        } catch (e) {
          return 'R$ ' + (v / 100).toFixed(2).replace('.', ',');
        }
      }
      if (orderTotal) {
        orderTotal.textContent = fmt(totalCents);
      }

      if (yearSel) {
        var now = new Date();
        var start = now.getFullYear();
        yearSel.innerHTML = '';
        for (var y = 0; y < 15; y++) {
          var optY = document.createElement('option');
          optY.value = (start + y);
          optY.textContent = (start + y);
          yearSel.appendChild(optY);
        }
      }

      if (instSel) {
        instSel.innerHTML = '';
        installmentsData.sort(function(a, b) {
          return (parseInt(a.number, 10) || 0) - (parseInt(b.number, 10) || 0);
        });
        var seen = {};
        installmentsData.forEach(function(inst) {
          var number = parseInt(inst.number, 10);
          if (!number || number < 1) {
            number = 1;
          }
          if (number > 12) {
            number = 12;
          }
          if (seen[number]) {
            return;
          }
          seen[number] = true;
          var interest = parseFloat(inst.interest || 0);
          if (!isFinite(interest)) {
            interest = 0;
          }
          var minAmount = Math.round(parseFloat(inst.min || 0) * 100);
          if (!isFinite(minAmount) || minAmount < 0) {
            minAmount = 0;
          }
          if (totalCents < minAmount) {
            return;
          }
          var totalFinal = Math.round(totalCents * (1 + (interest / 100)));
          var per = Math.round(totalFinal / number);

          var opt = document.createElement('option');
          opt.value = number;
          var label = number + 'x de ' + fmt(per);
          if (interest > 0) {
            label += ' (' + interest.toFixed(2).replace('.', ',') + '%)';
          }
          opt.textContent = label;
          opt.dataset.per = per;
          opt.dataset.total = totalFinal;
          opt.dataset.interest = interest;
          instSel.appendChild(opt);
        });
        if (!instSel.options.length) {
          var opt = document.createElement('option');
          opt.value = 1;
          opt.textContent = '1x de ' + fmt(totalCents);
          opt.dataset.per = totalCents;
          opt.dataset.total = totalCents;
          opt.dataset.interest = 0;
          instSel.appendChild(opt);
        }
        var updateSummary = function() {
          if (!detailsEl || !instSel || !instSel.options.length) {
            return;
          }
          var selected = instSel.selectedOptions.length ? instSel.selectedOptions[0] : instSel.options[0];
          var n = parseInt(selected.value, 10) || 1;
          var per = parseInt(selected.dataset.per, 10);
          if (!isFinite(per)) {
            per = Math.round(totalCents / n);
          }
          var totalFinal = parseInt(selected.dataset.total, 10);
          if (!isFinite(totalFinal)) {
            totalFinal = totalCents;
          }
          var interestValue = parseFloat(selected.dataset.interest || 0);
          var parts = [n + 'x de ' + fmt(per)];
          if (interestValue > 0) {
            parts.push('(juros ' + interestValue.toFixed(2).replace('.', ',') + '%)');
          }
          parts.push('— total ' + fmt(totalFinal));
          detailsEl.textContent = parts.join(' ');
        };
        instSel.addEventListener('change', updateSummary);
        updateSummary();
      }

      if (ccNum) {
        ccNum.addEventListener('input', function() {
          var v = this.value.replace(/\D/g, '').slice(0, 19);
          this.value = v.replace(/(.{4})/g, '$1 ').trim();
          var brand = detectBrand(v);
          if (brandSpan) {
            brandSpan.textContent = brand || '—';
          }
          if (ccCvv) {
            ccCvv.maxLength = (brand === 'amex') ? 4 : 3;
          }
        });
      }

      function detectBrand(num) {
        if (/^4[0-9]{6,}$/.test(num)) return 'visa';
        if (/^5[1-5][0-9]{5,}$/.test(num)) return 'mastercard';
        if (/^3[47][0-9]{5,}$/.test(num)) return 'amex';
        if (/^(606282|3841)/.test(num)) return 'hipercard';
        if (/^(4011(78|79)|431274|438935|451416|457393|504175|627780|636297|636368|650\d{2}|651\d{2}|652\d{2})/.test(num)) return 'elo';
        return '';
      }
      {/literal}
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', cazcoPayCardInit);
    } else {
      cazcoPayCardInit();
    }
  </script>
</div>

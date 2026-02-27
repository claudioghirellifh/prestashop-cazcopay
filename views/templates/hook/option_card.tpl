<div class="cazcopay-option cazcopay-option--card">
  <style>
    .cazcopay-option--card .cazco-card-intro {
      margin-bottom: 1rem;
      color: #4f5d6b;
    }
    .cazcopay-option--card .cazco-label {
      display: block;
      margin-bottom: .4rem;
      font-weight: 600;
      color: #2f3b47;
    }
    .cazcopay-option--card .cazco-field {
      margin-bottom: .9rem;
    }
    .cazcopay-option--card .cazco-brand-wrap {
      margin-top: .35rem;
      color: #66707a;
      font-size: .85rem;
    }
    .cazcopay-option--card .cazco-brand-badge {
      display: inline-block;
      margin-left: .35rem;
      padding: .1rem .45rem;
      border-radius: 999px;
      font-size: .75rem;
      font-weight: 600;
      letter-spacing: .02em;
      text-transform: uppercase;
      background: #eef2f5;
      color: #3b4a58;
    }
    .cazcopay-option--card .cazco-summary {
      border: 1px solid #d9e0e7;
      border-radius: 6px;
      background: #fafcfe;
      padding: .85rem .95rem;
      margin-top: .25rem;
    }
    .cazcopay-option--card .cazco-summary-row {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: .75rem;
      font-size: .92rem;
      color: #4a5865;
    }
    .cazcopay-option--card .cazco-summary-row + .cazco-summary-row {
      margin-top: .45rem;
      padding-top: .45rem;
      border-top: 1px dashed #d9e0e7;
    }
    .cazcopay-option--card .cazco-summary-value {
      font-weight: 700;
      color: #243241;
      text-align: right;
    }
  </style>

  <p class="cazco-card-intro">Preencha os dados do cartão. A cobrança será efetuada na confirmação.</p>

  <div id="cazco-card-form" class="mb-2">
    <div class="row">
      <div class="form-group col-md-12 cazco-field">
        <label for="cc-number" class="cazco-label">Número do cartão</label>
        <input class="form-control" id="cc-number" name="cc-number" inputmode="numeric" autocomplete="cc-number" placeholder="0000 0000 0000 0000" maxlength="23">
        <div class="cazco-brand-wrap">Bandeira: <span id="cc-brand" class="cazco-brand-badge">—</span></div>
      </div>
    </div>

    <div class="row">
      <div class="form-group col-md-12 cazco-field">
        <label for="cc-holder" class="cazco-label">Nome impresso</label>
        <input class="form-control" id="cc-holder" name="cc-holder" autocomplete="cc-name" placeholder="NOME COMO NO CARTÃO">
      </div>
    </div>

    <div class="row">
      <div class="form-group col-md-4 col-sm-6 cazco-field">
        <label for="cc-document-cpf" class="cazco-label">CPF do titular</label>
        <input class="form-control" id="cc-document-cpf" name="cc-document-cpf" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00" maxlength="14">
      </div>
    </div>

    <div class="row">
      <div class="form-group col-md-3 col-sm-4 cazco-field">
        <label for="cc-exp-month" class="cazco-label">Mês</label>
        <select class="form-control" id="cc-exp-month" name="cc-exp-month">
          {for $m=1 to 12}
            <option value="{$m}">{$m|string_format:"%02d"}</option>
          {/for}
        </select>
      </div>
      <div class="form-group col-md-3 col-sm-4 cazco-field">
        <label for="cc-exp-year" class="cazco-label">Ano</label>
        <select class="form-control" id="cc-exp-year" name="cc-exp-year"></select>
      </div>
      <div class="form-group col-md-3 col-sm-4 cazco-field">
        <label for="cc-cvv" class="cazco-label">CVV</label>
        <input class="form-control" id="cc-cvv" name="cc-cvv" inputmode="numeric" autocomplete="cc-csc" placeholder="CVV" maxlength="4">
      </div>
    </div>

    <div class="row">
      <div class="form-group col-md-6 col-sm-12 cazco-field">
        <label for="cc-installments" class="cazco-label">Nº de parcelas</label>
        <select class="form-control" id="cc-installments" name="cc-installments"></select>
      </div>
    </div>

    <div class="cazco-summary">
      <div class="cazco-summary-row">
        <span>Total do pedido</span>
        <strong id="order-total" class="cazco-summary-value">—</strong>
      </div>
      <div class="cazco-summary-row">
        <span>Simulação da parcela</span>
        <strong id="installment-details" class="cazco-summary-value">—</strong>
      </div>
    </div>
  </div>

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
      var ccHolder = document.getElementById('cc-holder');
      var ccDocumentCpf = document.getElementById('cc-document-cpf');
      var ccCvv = document.getElementById('cc-cvv');
      var monthSel = document.getElementById('cc-exp-month');
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

      function formatCpf(value) {
        var digits = (value || '').replace(/\D/g, '').slice(0, 11);
        if (digits.length <= 3) {
          return digits;
        }
        if (digits.length <= 6) {
          return digits.slice(0, 3) + '.' + digits.slice(3);
        }
        if (digits.length <= 9) {
          return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6);
        }
        return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6, 9) + '-' + digits.slice(9);
      }

      function isCazcoPaymentForm(form) {
        if (!form || form.tagName !== 'FORM') {
          return false;
        }
        var action = (form.getAttribute('action') || '').toLowerCase();
        return action.indexOf('module=cazcopay') !== -1
          || action.indexOf('/module/cazcopay/payment') !== -1
          || action.indexOf('/cazcopay/payment') !== -1;
      }

      function findHiddenInput(form, name) {
        var hiddenInputs = form.querySelectorAll('input[type="hidden"]');
        for (var i = 0; i < hiddenInputs.length; i++) {
          if (hiddenInputs[i].name === name) {
            return hiddenInputs[i];
          }
        }
        return null;
      }

      function upsertHiddenInput(form, name, value) {
        var input = findHiddenInput(form, name);
        if (!input) {
          input = document.createElement('input');
          input.type = 'hidden';
          input.name = name;
          form.appendChild(input);
        }
        input.value = value;
      }

      function collectCardValues() {
        return {
          'cc-number': ccNum ? ccNum.value || '' : '',
          'cc-holder': ccHolder ? ccHolder.value || '' : '',
          'cc-document-cpf': ccDocumentCpf ? ccDocumentCpf.value || '' : '',
          'cc-exp-month': monthSel ? monthSel.value || '' : '',
          'cc-exp-year': yearSel ? yearSel.value || '' : '',
          'cc-cvv': ccCvv ? ccCvv.value || '' : '',
          'cc-installments': instSel ? instSel.value || '' : ''
        };
      }

      function getCandidateForms(submitter) {
        var forms = [];
        var seen = [];

        function addForm(form) {
          if (!form || form.tagName !== 'FORM') {
            return;
          }
          if (seen.indexOf(form) !== -1) {
            return;
          }
          seen.push(form);
          forms.push(form);
        }

        if (submitter && submitter.form) {
          addForm(submitter.form);
        }
        if (submitter) {
          var formId = submitter.getAttribute('form');
          if (formId) {
            addForm(document.getElementById(formId));
          }
        }

        var allForms = document.querySelectorAll('form');
        for (var i = 0; i < allForms.length; i++) {
          if (isCazcoPaymentForm(allForms[i])) {
            addForm(allForms[i]);
          }
        }

        return forms;
      }

      function syncCardValuesToForms(forms) {
        if (!forms || !forms.length) {
          return;
        }
        var values = collectCardValues();
        for (var i = 0; i < forms.length; i++) {
          var form = forms[i];
          if (!isCazcoPaymentForm(form)) {
            continue;
          }
          upsertHiddenInput(form, 'cc-number', values['cc-number']);
          upsertHiddenInput(form, 'cc-holder', values['cc-holder']);
          upsertHiddenInput(form, 'cc-document-cpf', values['cc-document-cpf']);
          upsertHiddenInput(form, 'cc-exp-month', values['cc-exp-month']);
          upsertHiddenInput(form, 'cc-exp-year', values['cc-exp-year']);
          upsertHiddenInput(form, 'cc-cvv', values['cc-cvv']);
          upsertHiddenInput(form, 'cc-installments', values['cc-installments']);
        }
      }

      function syncToKnownForms() {
        syncCardValuesToForms(getCandidateForms(null));
      }

      if (ccNum) {
        ccNum.addEventListener('input', syncToKnownForms);
      }
      if (ccHolder) {
        ccHolder.addEventListener('input', syncToKnownForms);
      }
      if (ccDocumentCpf) {
        ccDocumentCpf.addEventListener('input', function() {
          this.value = formatCpf(this.value);
          syncToKnownForms();
        });
      }
      if (monthSel) {
        monthSel.addEventListener('change', syncToKnownForms);
      }
      if (yearSel) {
        yearSel.addEventListener('change', syncToKnownForms);
      }
      if (ccCvv) {
        ccCvv.addEventListener('input', syncToKnownForms);
      }
      if (instSel) {
        instSel.addEventListener('change', syncToKnownForms);
      }

      document.addEventListener('click', function(event) {
        var submitter = event.target && event.target.closest
          ? event.target.closest('#payment-confirmation button[type="submit"], #payment-confirmation input[type="submit"]')
          : null;
        if (!submitter) {
          return;
        }
        syncCardValuesToForms(getCandidateForms(submitter));
      }, true);

      document.addEventListener('submit', function(event) {
        var form = event.target;
        if (!isCazcoPaymentForm(form)) {
          return;
        }
        syncCardValuesToForms([form]);
      }, true);

      syncToKnownForms();
      {/literal}
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', cazcoPayCardInit);
    } else {
      cazcoPayCardInit();
    }
  </script>
</div>

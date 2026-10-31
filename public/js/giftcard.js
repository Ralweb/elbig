/* ============================================================================
   BIG ART GALLERY — giftcard.js
   Solo su gift-card.html ed en/gift-card.html.

   Il form non paga: valida, manda i dati ad api/giftcard-checkout.php e
   porta il browser sulla pagina di pagamento Stripe che quello restituisce.
   Al ritorno da Stripe (?esito=ok / ?esito=annullato) mostra l'esito.
   Le regole vere le ricontrolla il PHP: qui servono a non far perdere un
   giro a Stripe per un'email scritta male.
   ========================================================================== */

(function () {
  'use strict';

  var form = document.getElementById('gc-form');
  if (!form) { return; }

  var LANG = document.documentElement.lang === 'en' ? 'en' : 'it';
  var MIN = 20;
  var MAX = 2000;
  var TIMEOUT_MS = 20000;

  var COPY = {
    it: {
      fields: {
        'gc-importo-altro': { empty: 'Scrivi l’importo.', invalid: 'Un importo intero da 20 a 2.000 €.' },
        'gc-nome': { empty: 'Scrivi il tuo nome.' },
        'gc-email': { empty: 'Serve per mandarti il codice.', invalid: 'Controlla l’indirizzo email.' },
        'gc-dest-nome': { empty: 'Scrivi il nome di chi la riceve.' },
        'gc-dest-email': { empty: 'Serve per mandargli la gift card.', invalid: 'Controlla l’indirizzo email.' },
        'gc-messaggio': { invalid: 'Massimo 500 caratteri.' },
        'gc-data': { empty: 'Scegli il giorno di consegna.', invalid: 'Scegli un giorno da oggi a 6 mesi.' },
        'gc-termini': { empty: 'Per procedere accetta le condizioni.' }
      },
      sending: 'Apertura del pagamento…',
      fail: 'Il pagamento non si è aperto. Riprova tra poco, o chiamaci allo 06 543 4834.'
    },
    en: {
      fields: {
        'gc-importo-altro': { empty: 'Enter the amount.', invalid: 'A whole amount from €20 to €2,000.' },
        'gc-nome': { empty: 'Enter your name.' },
        'gc-email': { empty: 'We need it to send you the code.', invalid: 'Check the email address.' },
        'gc-dest-nome': { empty: 'Enter the recipient’s name.' },
        'gc-dest-email': { empty: 'We need it to send them the gift card.', invalid: 'Check the email address.' },
        'gc-messaggio': { invalid: '500 characters at most.' },
        'gc-data': { empty: 'Pick the delivery day.', invalid: 'Pick a day between today and 6 months from now.' },
        'gc-termini': { empty: 'Please accept the terms to continue.' }
      },
      sending: 'Opening payment…',
      fail: 'The payment page didn’t open. Try again shortly, or call us on +39 06 543 4834.'
    }
  }[LANG];

  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  var scriptEl = document.querySelector('script[src*="giftcard.js"]');
  var ENDPOINT = scriptEl
    ? new URL('../api/giftcard-checkout.php', scriptEl.src).href
    : 'api/giftcard-checkout.php';

  var $ = function (id) { return document.getElementById(id); };
  var custom = $('gc-custom');
  var customInput = $('gc-importo-altro');
  var gift = $('gc-gift');
  var dateInput = $('gc-data');
  var terms = $('gc-termini');
  var submit = $('gc-submit');
  var status = $('gc-status');
  var amountLabels = document.querySelectorAll('[data-gc-amount]');
  var isSending = false;


  /* --- Ritorno da Stripe --------------------------------------------------
     L'URL si ripulisce subito: ricaricando la pagina, o tornandoci dalla
     cronologia, non si deve rivedere "pagamento ricevuto". */

  var esito = new URLSearchParams(window.location.search).get('esito');
  if (esito) {
    window.history.replaceState(null, '', window.location.pathname);
  }

  if (esito === 'ok') {
    $('gc-layout').hidden = true;
    $('gc-done').hidden = false;
    $('gc-done').focus();
    return;
  }

  if (esito === 'annullato') {
    $('gc-cancelled').hidden = false;
  }


  /* --- Date ---------------------------------------------------------------
     Data locale del browser, non toISOString(): quella è in UTC, e tra
     mezzanotte e l'una (le due d'estate) in Italia darebbe ancora ieri. */

  function isoDate(d) {
    var m = d.getMonth() + 1;
    var day = d.getDate();
    return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
  }

  var today = new Date();
  var limit = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 180);
  dateInput.min = isoDate(today);
  dateInput.max = isoDate(limit);
  if (!dateInput.value) { dateInput.value = isoDate(today); }


  /* --- Importo ------------------------------------------------------------ */

  function selectedAmount() {
    var checked = form.querySelector('input[name="importo"]:checked');
    if (!checked) { return 0; }
    if (checked.value !== 'altro') { return parseInt(checked.value, 10); }
    var value = customInput.value.trim();
    return /^\d+$/.test(value) ? parseInt(value, 10) : 0;
  }

  function formatMoney(n) {
    var s = String(n).replace(/\B(?=(\d{3})+(?!\d))/g, LANG === 'en' ? ',' : '.');
    return LANG === 'en' ? '€' + s : s + ' €';
  }

  function renderAmount() {
    var n = selectedAmount();
    var text = n >= MIN && n <= MAX ? formatMoney(n) : '';
    Array.prototype.forEach.call(amountLabels, function (el) { el.textContent = text; });
  }

  function syncCustom() {
    var isCustom = form.querySelector('input[name="importo"]:checked').value === 'altro';
    custom.hidden = !isCustom;
    customInput.required = isCustom;
    if (!isCustom) { showFieldError(customInput, ''); }
    renderAmount();
  }

  form.addEventListener('change', function (event) {
    if (event.target.name === 'importo') {
      syncCustom();
      if (event.target.value === 'altro') { customInput.focus(); }
    }
    if (event.target.name === 'regalo') { syncGift(); }
  });
  customInput.addEventListener('input', renderAmount);


  /* --- Regalo -------------------------------------------------------------
     I campi del destinatario esistono sempre nel DOM, ma sono obbligatori
     solo con "direttamente a chi la riceve". Nascosti, non si validano. */

  var giftFields = [$('gc-dest-nome'), $('gc-dest-email'), dateInput];

  function syncGift() {
    var on = form.querySelector('input[name="regalo"]:checked').value === '1';
    gift.hidden = !on;
    giftFields.forEach(function (input) {
      input.required = on;
      if (!on) { showFieldError(input, ''); }
    });
  }


  /* --- Validazione -------------------------------------------------------- */

  function fieldError(input) {
    var rules = COPY.fields[input.id] || {};

    if (input.type === 'checkbox') {
      return input.checked ? '' : rules.empty;
    }

    var value = input.value.trim();
    if (input.required && !value) { return rules.empty || ''; }
    if (!value) { return ''; }

    if (input.type === 'email' && !EMAIL_RE.test(value)) { return rules.invalid; }

    if (input === customInput) {
      var n = /^\d+$/.test(value) ? parseInt(value, 10) : 0;
      if (n < MIN || n > MAX) { return rules.invalid; }
    }

    if (input === dateInput && (value < dateInput.min || value > dateInput.max)) {
      return rules.invalid;
    }

    return '';
  }

  function showFieldError(input, message) {
    var target = $(input.id + '-error');
    if (!target) { return; }

    if (message) {
      input.setAttribute('aria-invalid', 'true');
      target.textContent = message;
      target.hidden = false;
    } else {
      input.removeAttribute('aria-invalid');
      target.textContent = '';
      target.hidden = true;
    }
  }

  var checked = [customInput, $('gc-nome'), $('gc-email')].concat(giftFields, [terms]);

  checked.forEach(function (input) {
    var live = function () {
      if (input.hasAttribute('aria-invalid')) { showFieldError(input, fieldError(input)); }
    };
    input.addEventListener('input', live);
    input.addEventListener('change', live);
    input.addEventListener('blur', function () {
      if (input.type !== 'checkbox' && (input.value.trim() || input.hasAttribute('aria-invalid'))) {
        showFieldError(input, fieldError(input));
      }
    });
  });


  /* --- Invio -------------------------------------------------------------- */

  function showStatus(message) {
    status.textContent = message;
    status.hidden = !message;
  }

  function setSending(on) {
    isSending = on;
    submit.disabled = on;
    form.setAttribute('aria-busy', on ? 'true' : 'false');
  }

  // Il server rimanda i nomi dei campi come li riceve: si mappano sugli id.
  var SERVER_FIELDS = {
    importo: customInput,
    nome: $('gc-nome'),
    email: $('gc-email'),
    dest_nome: $('gc-dest-nome'),
    dest_email: $('gc-dest-email'),
    messaggio: $('gc-messaggio'),
    data: dateInput,
    termini: terms
  };

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (isSending) { return; }
    showStatus('');

    var firstInvalid = null;
    checked.forEach(function (input) {
      if (input.closest('[hidden]')) { return; }
      var message = fieldError(input);
      showFieldError(input, message);
      if (message && !firstInvalid) { firstInvalid = input; }
    });

    if (firstInvalid) {
      firstInvalid.focus();
      return;
    }

    var isGift = form.querySelector('input[name="regalo"]:checked').value === '1';

    var payload = {
      importo: String(selectedAmount()),
      nome: $('gc-nome').value.trim(),
      email: $('gc-email').value.trim(),
      regalo: isGift ? '1' : '0',
      dest_nome: isGift ? $('gc-dest-nome').value.trim() : '',
      dest_email: isGift ? $('gc-dest-email').value.trim() : '',
      messaggio: isGift ? $('gc-messaggio').value.trim() : '',
      data: isGift ? dateInput.value : '',
      ora: isGift ? ($('gc-ora').value || '09:00') : '',
      termini: terms.checked ? '1' : '',
      sito: $('gc-sito').value,
      lang: LANG
    };

    setSending(true);
    showStatus(COPY.sending);

    var controller = new AbortController();
    var timer = window.setTimeout(function () { controller.abort(); }, TIMEOUT_MS);

    fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: controller.signal
    })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (data) {
          return { status: response.status, data: data };
        });
      })
      .then(function (result) {
        window.clearTimeout(timer);

        if (result.data.ok && result.data.url) {
          // Si resta in "invio" fino al cambio di pagina: un secondo click
          // aprirebbe un secondo checkout.
          window.location.assign(result.data.url);
          return;
        }

        setSending(false);

        if (result.status === 422 && result.data.fields) {
          showStatus('');
          var first = null;
          result.data.fields.forEach(function (name) {
            var input = SERVER_FIELDS[name];
            if (!input) { return; }
            if (name === 'importo' && custom.hidden) { input = null; }
            if (input) {
              showFieldError(input, (COPY.fields[input.id] || {}).invalid || (COPY.fields[input.id] || {}).empty);
              if (!first) { first = input; }
            }
          });
          if (first) { first.focus(); return; }
        }

        showStatus(COPY.fail);
      })
      .catch(function () {
        window.clearTimeout(timer);
        setSending(false);
        showStatus(COPY.fail);
      });
  });

  // Tornando indietro da Stripe col tasto del browser la pagina può uscire
  // dalla cache così com'era, col bottone ancora disabilitato.
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      setSending(false);
      showStatus('');
    }
  });

  syncCustom();
  syncGift();
}());

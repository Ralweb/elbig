/* ============================================================================
   BIG ART GALLERY — main.js
   Vanilla JS, nessuna dipendenza, nessun build step.
   Caricato con `defer`: il DOM è già pronto quando questo file esegue.
   ========================================================================== */

(function () {
  'use strict';

  /* --------------------------------------------------------------------------
     COPIE — italiano default, inglese se <html lang="en">.

     I testi visibili stanno nell'HTML. Qui restano solo le stringhe che il
     JS scrive da sé: aria-label del menu, errori del form, stato di invio.
     ------------------------------------------------------------------------ */

  var LANG = document.documentElement.lang === 'en' ? 'en' : 'it';

  var COPY = LANG === 'en' ? {
    openMenu: 'Open menu',
    closeMenu: 'Close menu',
    sending: 'Sending…',
    sendFail: 'We couldn’t send the request. Try again, or call us on 06 543 4834.',
    gallery: 'Work gallery',
    prevWork: 'Previous work',
    nextWork: 'Next work',
    closeGallery: 'Close',
    fields: {
      'cf-nome': { empty: 'Tell us what to call you.' },
      'cf-email': {
        empty: 'We need an email address to write back.',
        invalid: 'Check the address: the @ or the domain is missing.'
      },
      'cf-telefono': { invalid: 'Check the number: at least six digits.' },
      'cf-stile': { empty: 'Choose a style. If you haven’t decided yet, pick “Other”.' },
      'cf-messaggio': { empty: 'Describe the project — two lines is enough.' }
    }
  } : {
    openMenu: 'Apri il menu',
    closeMenu: 'Chiudi il menu',
    sending: 'Invio in corso…',
    sendFail: 'Non siamo riusciti a inviare la richiesta. Riprova, oppure chiamaci al 06 543 4834.',
    gallery: 'Galleria lavori',
    prevWork: 'Lavoro precedente',
    nextWork: 'Lavoro successivo',
    closeGallery: 'Chiudi',
    fields: {
      'cf-nome': { empty: 'Dicci come chiamarti.' },
      'cf-email': {
        empty: 'Serve un indirizzo email per risponderti.',
        invalid: 'Controlla l’indirizzo: manca la chiocciola o il dominio.'
      },
      'cf-telefono': { invalid: 'Controlla il numero: servono almeno sei cifre.' },
      'cf-stile': { empty: 'Scegli uno stile. Se non hai ancora deciso, indica “Altro”.' },
      'cf-messaggio': { empty: 'Descrivi il progetto, bastano due righe.' }
    }
  };

  /* --------------------------------------------------------------------------
     HEADER — stato "scrolled"

     Nessun listener di scroll: una sentinella alta quanto l'header viene
     osservata dall'IntersectionObserver. Quando esce dal viewport l'header
     prende fondo e hairline. Costo zero durante lo scroll.
     ------------------------------------------------------------------------ */

  var header = document.getElementById('site-header');
  var sentinel = document.getElementById('top');

  if (header && sentinel && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      header.classList.toggle('is-scrolled', !entries[0].isIntersecting);
    }).observe(sentinel);
  }


  /* --------------------------------------------------------------------------
     NAV OVERLAY — menu a tutto schermo (sotto i 900px)
     ------------------------------------------------------------------------ */

  var toggle = document.getElementById('nav-toggle');
  var overlay = document.getElementById('nav-overlay');

  // Ponte verso il modale, più in basso: il menu va chiuso quando si apre il
  // popup contatti, ma closeNav vive dentro il blocco qui sotto. Resta una
  // funzione vuota se la nav non è in pagina.
  var closeNavOverlay = function () {};
  var closeContactModal = function () {};
  var closeLightboxViewer = function () {};

  if (toggle && overlay) {
    var desktopQuery = window.matchMedia('(min-width: 900px)');
    var isOpen = false;
    var navInerted = [];

    // Logo (visibile sopra l'overlay) + azioni header + voci del menu.
    // Senza il logo, resterebbe cliccabile col mouse ma fuori dal ciclo Tab.
    function focusableItems() {
      return Array.prototype.slice.call(document.querySelectorAll(
        '.site-header .logo[href], .site-header__actions a[href], .site-header__actions button:not([disabled]), #nav-overlay a[href]'
      ));
    }

    function inertPageBehindNav(on) {
      if (on) {
        navInerted = Array.prototype.filter.call(
          document.body.children,
          function (el) {
            return el !== overlay &&
              el !== header &&
              !el.classList.contains('site-header') &&
              !el.hasAttribute('inert');
          }
        );
        navInerted.forEach(function (el) { el.setAttribute('inert', ''); });
        return;
      }
      navInerted.forEach(function (el) { el.removeAttribute('inert'); });
      navInerted = [];
    }

    function openNav() {
      isOpen = true;
      overlay.removeAttribute('inert');
      overlay.classList.add('is-open');
      header.classList.add('is-nav-open');
      document.body.classList.add('has-nav-open');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', COPY.closeMenu);
      inertPageBehindNav(true);

      // Il focus va sulla prima voce del menu, non sulla prima azione
      // dell'header: è il contenuto appena comparso.
      // La lettura di offsetHeight forza il ricalcolo dello stile: finché
      // l'overlay risulta visibility:hidden il focus() non attecchisce.
      void overlay.offsetHeight;

      var firstLink = overlay.querySelector('a[href]');
      if (firstLink) { firstLink.focus(); }
    }

    function closeNav(restoreFocus) {
      isOpen = false;
      overlay.classList.remove('is-open');
      header.classList.remove('is-nav-open');
      document.body.classList.remove('has-nav-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', COPY.openMenu);
      inertPageBehindNav(false);

      // `inert` toglie l'overlay dal tab order e dall'albero di
      // accessibilità: va rimesso solo dopo che il focus è uscito, altrimenti
      // il browser lo scarta e il focus finisce sul <body>.
      if (restoreFocus) { toggle.focus(); }
      overlay.setAttribute('inert', '');
    }

    // Senza ripristino del focus: se ne occupa il modale, che sta per
    // prenderselo.
    closeNavOverlay = function () {
      if (isOpen) { closeNav(false); }
    };

    toggle.addEventListener('click', function () {
      if (isOpen) { closeNav(true); } else { openNav(); }
    });

    // Un link scelto chiude il menu. Gli anchor interni scrollano da soli:
    // scroll-padding-top in CSS li tiene sotto l'header.
    overlay.addEventListener('click', function (event) {
      if (event.target.closest('a[href]')) { closeNav(false); }
    });

    // Il logo sta sopra l'overlay e non è una voce del menu: senza questo
    // il click andrebbe a #top / home lasciando il menu aperto.
    if (header) {
      header.addEventListener('click', function (event) {
        if (!isOpen) { return; }
        if (event.target.closest('.logo')) { closeNav(false); }
      });
    }

    document.addEventListener('keydown', function (event) {
      if (!isOpen) { return; }

      if (event.key === 'Escape') {
        closeNav(true);
        return;
      }

      // Focus trap: a menu aperto il Tab gira tra header e overlay,
      // senza scendere nella pagina che sta sotto.
      if (event.key === 'Tab') {
        var items = focusableItems();
        if (!items.length) { return; }

        var first = items[0];
        var last = items[items.length - 1];

        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    });

    // Passando a desktop la nav torna orizzontale: l'overlay va chiuso,
    // altrimenti resterebbe lo scroll bloccato sul body.
    function handleBreakpoint(event) {
      if (event.matches && isOpen) { closeNav(false); }
    }

    if (typeof desktopQuery.addEventListener === 'function') {
      desktopQuery.addEventListener('change', handleBreakpoint);
    } else {
      desktopQuery.addListener(handleBreakpoint); // Safari < 14
    }
  }


  /* --------------------------------------------------------------------------
     MODALE CONTATTI

     Trigger: ogni [data-open-modal] in pagina (header, hero, banner CTA).
     Sono <a href="#contatti">, quindi senza JS cadono sui recapiti del
     footer invece di non fare nulla: il preventDefault scatta solo se il
     modale esiste davvero nel DOM.

     Il resto della pagina viene messo in `inert` a modale aperto — è il
     browser stesso a togliere fuori fuoco, tab e lettura schermo, e il ciclo
     di Tab qui sotto serve solo a far girare il focus dentro al pannello.
     ------------------------------------------------------------------------ */

  var modal = document.getElementById('contact-modal');

  if (modal) {
    var modalScrim = document.getElementById('contact-modal-scrim');
    var modalScroll = modal.querySelector('.modal__scroll');
    var modalClose = document.getElementById('contact-modal-close');

    var form = document.getElementById('contact-form');
    var formStatus = document.getElementById('cf-status');
    var formSubmit = document.getElementById('cf-submit');
    var formTrap = document.getElementById('cf-sito');
    var modalHead = document.getElementById('contact-modal-head');
    var donePanel = document.getElementById('contact-done');
    var doneClose = document.getElementById('contact-done-close');

    var submitLabel = formSubmit.textContent.trim();

    var isModalOpen = false;
    var isSending = false;
    var lastTrigger = null;
    var pressTarget = null;
    var inertedSiblings = [];
    var pendingRequest = null;
    var swapTimer = null;
    var swapOnEnd = null;

    /* --- Apertura e chiusura ---------------------------------------------- */

    function modalFocusables() {
      return Array.prototype.slice.call(modal.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])'
      )).filter(function (el) {
        // tabindex=-1: trappola anti-bot e pannello conferma. getClientRects
        // da solo non basta — il honeypot è 1×1 e risulterebbe visibile.
        if (el.tabIndex < 0) { return false; }
        // getClientRects vuoto = elemento nascosto (form o conferma spenti).
        return el.getClientRects().length > 0;
      });
    }

    function openModal(trigger) {
      if (isModalOpen) { return; }
      isModalOpen = true;
      lastTrigger = trigger || null;

      // Il menu mobile e il lightbox non convivono con il modale.
      closeNavOverlay();
      closeLightboxViewer();

      // Si riapre sempre dal form vuoto, anche dopo un invio andato a buon
      // fine. Farlo qui e non in chiusura evita di vedere la ricevuta
      // tornare form mentre il pannello sta ancora sfumando.
      resetForm();

      // Nessuna compensazione della scrollbar da fare qui: `scrollbar-gutter:
      // stable` su <html> tiene lo spazio riservato anche a scroll bloccato.
      document.body.classList.add('has-modal-open');

      // `inert` su tutto il resto del body. Chi ce l'ha già (la nav overlay
      // chiusa) resta com'è: non va rimosso in chiusura.
      inertedSiblings = Array.prototype.filter.call(
        document.body.children,
        function (el) { return el !== modal && !el.hasAttribute('inert'); }
      );
      inertedSiblings.forEach(function (el) { el.setAttribute('inert', ''); });

      modal.removeAttribute('inert');
      modal.classList.add('is-open');

      // Come per la nav: finché il modale risulta visibility:hidden il
      // focus() non attecchisce. La lettura forza il ricalcolo dello stile.
      void modal.offsetHeight;

      var first = form.querySelector('.field__input');
      if (first) { first.focus(); }
    }

    function closeModal(restoreFocus) {
      if (!isModalOpen) { return; }
      isModalOpen = false;

      // Se un invio è in volo lo si abbandona: la risposta non avrebbe più
      // dove comparire.
      if (pendingRequest) {
        pendingRequest.abort();
        pendingRequest = null;
      }
      setSending(false);
      cancelSwap();

      modal.classList.remove('is-open');

      inertedSiblings.forEach(function (el) { el.removeAttribute('inert'); });
      inertedSiblings = [];

      document.body.classList.remove('has-modal-open');

      // Il focus deve uscire prima che `inert` torni sul modale, altrimenti
      // il browser lo scarta e finisce sul <body>.
      if (restoreFocus && lastTrigger) { lastTrigger.focus(); }
      modal.setAttribute('inert', '');
      lastTrigger = null;
    }

    closeContactModal = function () {
      if (isModalOpen) { closeModal(false); }
    };

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-open-modal]');
      if (!trigger) { return; }
      event.preventDefault();
      openModal(trigger);
    });

    modalClose.addEventListener('click', function () { closeModal(true); });
    doneClose.addEventListener('click', function () { closeModal(true); });

    // Click fuori. Si guarda anche dove il puntatore è stato premuto: una
    // selezione di testo iniziata dentro al pannello e finita sullo scrim
    // non deve chiudere il modale.
    modal.addEventListener('pointerdown', function (event) {
      pressTarget = event.target;
    });

    modal.addEventListener('click', function (event) {
      var onScrim = function (el) { return el === modalScrim || el === modal; };
      if (onScrim(event.target) && onScrim(pressTarget)) { closeModal(true); }
    });

    document.addEventListener('keydown', function (event) {
      if (!isModalOpen) { return; }

      if (event.key === 'Escape') {
        closeModal(true);
        return;
      }

      if (event.key === 'Tab') {
        var items = modalFocusables();
        if (!items.length) { return; }

        var first = items[0];
        var last = items[items.length - 1];

        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    });

    /* --- Validazione -------------------------------------------------------
       I vincoli stanno sugli attributi HTML (required, type="email"): qui si
       decide solo cosa dire e quando. Il testo di un errore spiega cosa
       manca, non che qualcosa è "non valido".
       --------------------------------------------------------------------- */

    // Volutamente permissiva: qualcosa, una chiocciola, qualcosa, un punto,
    // qualcosa. Le regex più severe di questa scartano indirizzi veri.
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    var MESSAGES = COPY.fields;

    function fieldError(input) {
      var rules = MESSAGES[input.id] || {};
      var value = input.value.trim();

      if (input.required && !value) { return rules.empty || ''; }
      if (!value) { return ''; }

      if (input.id === 'cf-email' && !EMAIL_RE.test(value)) {
        return rules.invalid;
      }

      if (input.id === 'cf-telefono' && value.replace(/\D/g, '').length < 6) {
        return rules.invalid;
      }

      return '';
    }

    function showFieldError(input, message) {
      var target = document.getElementById(input.id + '-error');

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

    var fields = Array.prototype.slice.call(
      form.querySelectorAll('.field__input')
    );

    fields.forEach(function (input) {
      // Si controlla mentre si scrive solo un campo già segnalato: chi sta
      // ancora battendo la prima lettera dell'email non va corretto.
      var live = function () {
        if (input.hasAttribute('aria-invalid')) {
          showFieldError(input, fieldError(input));
        }
      };

      input.addEventListener('input', live);
      input.addEventListener('change', live);

      // All'uscita dal campo invece si controlla sempre: l'errore arriva
      // quando quella riga è finita, non a form spedito.
      input.addEventListener('blur', function () {
        if (input.value.trim() || input.hasAttribute('aria-invalid')) {
          showFieldError(input, fieldError(input));
        }
      });
    });

    /* --- Invio ------------------------------------------------------------- */

    /* Endpoint del form. Risolto rispetto a questo file, non alla pagina:
       da /en/index.html un path relativo `api/contact.php` punterebbe a
       /en/api/contact.php. Vedi DEPLOY.md per email e FTP.

       Per un servizio esterno (Formspree, Web3Forms) sostituisci ENDPOINT
       con il loro URL e, se serve, la forma di `payload`. */
    var scriptEl = document.querySelector('script[src*="main.js"]');
    var ENDPOINT = scriptEl
      ? new URL('../api/contact.php', scriptEl.src).href
      : 'api/contact.php';
    var TIMEOUT_MS = 15000;

    function setSending(sending) {
      isSending = sending;
      formSubmit.disabled = sending;
      formSubmit.textContent = sending ? COPY.sending : submitLabel;
    }

    function showStatus(message) {
      formStatus.textContent = message || '';
      formStatus.hidden = !message;
    }

    function prefersReducedMotion() {
      return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function cancelSwap() {
      if (swapTimer) {
        window.clearTimeout(swapTimer);
        swapTimer = null;
      }
      if (swapOnEnd) {
        form.removeEventListener('transitionend', swapOnEnd);
        swapOnEnd = null;
      }
    }

    function revealDone() {
      modalHead.hidden = true;
      modalHead.classList.remove('is-exiting');
      form.hidden = true;
      form.classList.remove('is-exiting');

      donePanel.hidden = false;
      modalScroll.scrollTop = 0;

      // Senza reflow la transizione partirebbe da display:none e si perderebbe.
      void donePanel.offsetHeight;
      donePanel.classList.add('is-entered');
      // Il focus va sul pannello (tabindex="-1") e non sul bottone: così lo
      // screen reader legge la conferma intera, non solo "Chiudi".
      donePanel.focus();
    }

    function showDone() {
      cancelSwap();

      // Reduced motion: niente attesa sull'uscita, resta solo il fade della
      // ricevuta (opacity, gestito in CSS).
      if (prefersReducedMotion()) {
        revealDone();
        return;
      }

      // Sparisce anche la testata: a richiesta inviata "Raccontaci l'idea"
      // sarebbe un invito a fare una cosa appena fatta.
      modalHead.classList.add('is-exiting');
      form.classList.add('is-exiting');

      var settled = false;
      function proceed() {
        if (settled) { return; }
        settled = true;
        cancelSwap();
        revealDone();
      }

      swapOnEnd = function (event) {
        if (event.target !== form || event.propertyName !== 'opacity') { return; }
        proceed();
      };
      form.addEventListener('transitionend', swapOnEnd);
      // --duration-fast è 150ms; il margine copre un frame lento.
      swapTimer = window.setTimeout(proceed, 220);
    }

    function resetForm() {
      cancelSwap();
      form.reset();
      modalHead.classList.remove('is-exiting');
      form.classList.remove('is-exiting');
      donePanel.classList.remove('is-entered');
      modalHead.hidden = false;
      form.hidden = false;
      donePanel.hidden = true;
      fields.forEach(function (input) { showFieldError(input, ''); });
      showStatus('');
      setSending(false);
      if (modalScroll) { modalScroll.scrollTop = 0; }
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (isSending) { return; }

      showStatus('');

      var firstInvalid = null;
      fields.forEach(function (input) {
        var message = fieldError(input);
        showFieldError(input, message);
        if (message && !firstInvalid) { firstInvalid = input; }
      });

      if (firstInvalid) {
        firstInvalid.focus();
        return;
      }

      // Trappola compilata: è un bot. Si mostra la conferma senza inviare
      // nulla — un errore gli direbbe come aggirarla.
      if (formTrap.value) {
        showDone();
        return;
      }

      // Campo nascosto presente solo sulle pagine artista-*.html: dice a
      // chi è indirizzata la richiesta. Sulla home e sul catalogo non
      // esiste, e la mail non porta la riga.
      var artistaEl = document.getElementById('cf-artista');

      var payload = {
        nome: document.getElementById('cf-nome').value.trim(),
        email: document.getElementById('cf-email').value.trim(),
        telefono: document.getElementById('cf-telefono').value.trim(),
        stile: document.getElementById('cf-stile').value,
        messaggio: document.getElementById('cf-messaggio').value.trim(),
        artista: artistaEl ? artistaEl.value : '',
        lang: LANG
      };

      setSending(true);

      // AbortController e non AbortSignal.timeout(): quest'ultimo manca su
      // Safari sotto la 16, e serve comunque un abort manuale se il modale
      // viene chiuso a invio in corso.
      var controller = new AbortController();
      pendingRequest = controller;
      var timer = window.setTimeout(function () { controller.abort(); }, TIMEOUT_MS);

      fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        signal: controller.signal
      })
        .then(function (response) {
          if (!response.ok) { throw new Error('HTTP ' + response.status); }
          showDone();
        })
        .catch(function (error) {
          if (error.name === 'AbortError' && !isModalOpen) { return; }
          setSending(false);
          showStatus(COPY.sendFail);
        })
        .then(function () {
          window.clearTimeout(timer);
          // Solo se è ancora questa la richiesta in volo: chiudendo e
          // rinviando subito, la coda della vecchia arriverebbe qui a
          // cancellare il riferimento a quella nuova.
          if (pendingRequest === controller) { pendingRequest = null; }
        });
    });
  }


  /* --------------------------------------------------------------------------
     LIGHTBOX LAVORI

     Ogni <a data-gallery> in un .work__scroller apre un viewer a pagina
     intera. Senza JS il link apre il file. Le frecce restano nella stessa
     striscia: la galleria di Alessandro non include i lavori di Lobo.
     ------------------------------------------------------------------------ */

  (function () {
    var triggers = document.querySelectorAll('a[data-gallery]');
    if (!triggers.length) { return; }

    var root = document.createElement('div');
    root.className = 'lightbox';
    root.id = 'lightbox';
    root.setAttribute('inert', '');
    root.innerHTML =
      '<div class="lightbox__scrim" data-lightbox-scrim aria-hidden="true"></div>' +
      '<div class="lightbox__dialog" role="dialog" aria-modal="true" aria-label="' + COPY.gallery + '">' +
        '<button class="lightbox__close" type="button" aria-label="' + COPY.closeGallery + '">' +
          '<span class="modal__close-mark" aria-hidden="true"></span>' +
        '</button>' +
        '<button class="lightbox__prev" type="button" aria-label="' + COPY.prevWork + '">' +
          '<span aria-hidden="true">&#8592;</span>' +
        '</button>' +
        '<button class="lightbox__next" type="button" aria-label="' + COPY.nextWork + '">' +
          '<span aria-hidden="true">&#8594;</span>' +
        '</button>' +
        '<figure class="lightbox__figure">' +
          '<img class="lightbox__img" alt="">' +
          '<figcaption class="lightbox__counter"></figcaption>' +
        '</figure>' +
      '</div>';
    document.body.appendChild(root);

    var scrim = root.querySelector('[data-lightbox-scrim]');
    var btnClose = root.querySelector('.lightbox__close');
    var btnPrev = root.querySelector('.lightbox__prev');
    var btnNext = root.querySelector('.lightbox__next');
    var img = root.querySelector('.lightbox__img');
    var counter = root.querySelector('.lightbox__counter');

    var isOpen = false;
    var lastTrigger = null;
    var items = [];
    var current = 0;
    var inerted = [];
    var pressTarget = null;
    var swapTimer = null;
    var swipeX = 0;
    var swipeY = 0;
    var swipeFrom = false;

    function prefersReducedMotion() {
      return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function focusables() {
      return Array.prototype.filter.call(
        [btnClose, btnPrev, btnNext],
        function (el) { return el.getClientRects().length > 0; }
      );
    }

    function applySlide() {
      var item = items[current];
      var thumb = item.querySelector('.work__img');
      img.src = item.getAttribute('href');
      img.alt = thumb ? (thumb.getAttribute('alt') || '') : '';
      if (thumb) {
        img.setAttribute('width', thumb.getAttribute('width') || '');
        img.setAttribute('height', thumb.getAttribute('height') || '');
      }
      counter.textContent = (current + 1) + ' / ' + items.length;
    }

    function showSlide(index, animate) {
      current = (index + items.length) % items.length;

      if (swapTimer) {
        window.clearTimeout(swapTimer);
        swapTimer = null;
      }

      if (!animate || prefersReducedMotion()) {
        img.classList.remove('is-changing');
        applySlide();
        return;
      }

      img.classList.add('is-changing');
      swapTimer = window.setTimeout(function () {
        applySlide();
        swapTimer = null;
        void img.offsetHeight;
        img.classList.remove('is-changing');
      }, 150);
    }

    function openLightbox(group, index, trigger) {
      if (isOpen) {
        items = group;
        lastTrigger = trigger || lastTrigger;
        root.classList.toggle('is-single', items.length < 2);
        btnPrev.hidden = items.length < 2;
        btnNext.hidden = items.length < 2;
        showSlide(index, true);
        return;
      }

      isOpen = true;
      items = group;
      current = index;
      lastTrigger = trigger || null;

      closeNavOverlay();
      closeContactModal();

      root.classList.toggle('is-single', items.length < 2);
      btnPrev.hidden = items.length < 2;
      btnNext.hidden = items.length < 2;

      img.classList.remove('is-changing');
      applySlide();

      document.body.classList.add('has-lightbox-open');

      inerted = Array.prototype.filter.call(
        document.body.children,
        function (el) { return el !== root && !el.hasAttribute('inert'); }
      );
      inerted.forEach(function (el) { el.setAttribute('inert', ''); });

      root.removeAttribute('inert');
      root.classList.add('is-open');
      void root.offsetHeight;
      btnClose.focus();
    }

    function closeLightbox(restoreFocus) {
      if (!isOpen) { return; }
      isOpen = false;

      if (swapTimer) {
        window.clearTimeout(swapTimer);
        swapTimer = null;
      }
      img.classList.remove('is-changing');

      root.classList.remove('is-open');

      inerted.forEach(function (el) { el.removeAttribute('inert'); });
      inerted = [];

      document.body.classList.remove('has-lightbox-open');

      if (restoreFocus && lastTrigger) { lastTrigger.focus(); }
      root.setAttribute('inert', '');
      lastTrigger = null;
      items = [];
    }

    closeLightboxViewer = function () {
      if (isOpen) { closeLightbox(false); }
    };

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('a[data-gallery]');
      if (!trigger) { return; }
      var scroller = trigger.closest('.work__scroller');
      if (!scroller) { return; }
      event.preventDefault();
      var group = Array.prototype.slice.call(
        scroller.querySelectorAll('a[data-gallery]')
      );
      openLightbox(group, group.indexOf(trigger), trigger);
    });

    btnClose.addEventListener('click', function () { closeLightbox(true); });
    btnPrev.addEventListener('click', function () { showSlide(current - 1, true); });
    btnNext.addEventListener('click', function () { showSlide(current + 1, true); });

    root.addEventListener('pointerdown', function (event) {
      pressTarget = event.target;
      if (event.target === img || event.target === counter) {
        swipeFrom = true;
        swipeX = event.clientX;
        swipeY = event.clientY;
      } else {
        swipeFrom = false;
      }
    });

    root.addEventListener('click', function (event) {
      var onScrim = event.target === scrim || event.target === root;
      var pressedScrim = pressTarget === scrim || pressTarget === root;
      if (onScrim && pressedScrim) { closeLightbox(true); }
    });

    root.addEventListener('pointerup', function (event) {
      if (!isOpen || !swipeFrom) { return; }
      swipeFrom = false;
      var dx = event.clientX - swipeX;
      var dy = event.clientY - swipeY;
      if (Math.abs(dx) < 50 || Math.abs(dx) < Math.abs(dy)) { return; }
      if (items.length < 2) { return; }
      showSlide(current + (dx < 0 ? 1 : -1), true);
    });

    document.addEventListener('keydown', function (event) {
      if (!isOpen) { return; }

      if (event.key === 'Escape') {
        closeLightbox(true);
        return;
      }

      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        showSlide(current - 1, true);
        return;
      }

      if (event.key === 'ArrowRight') {
        event.preventDefault();
        showSlide(current + 1, true);
        return;
      }

      if (event.key === 'Tab') {
        var list = focusables();
        if (!list.length) { return; }
        var first = list[0];
        var last = list[list.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    });
  })();


  /* --------------------------------------------------------------------------
     SCROLLERS ORIZZONTALI — tabindex solo mentre scorrono

     Sotto i 768px (work) e i 1200px (instagram) i contenitori scorrono e
     senza un tab stop non sono raggiungibili da tastiera. Da quelle soglie
     in su diventano griglie: il tab stop resterebbe un salto vuoto.
     ------------------------------------------------------------------------ */

  function bindScrollerTabindex(selector, query) {
    var el = document.querySelector(selector);
    if (!el) { return; }

    // I riquadri lavori diventano link al lightbox: il tab stop sullo
    // scroller sarebbe un salto vuoto. Resta solo se i frame sono ancora
    // placeholder senza destinazione.
    if (selector === '.work__scroller' && el.querySelector('[data-gallery]')) {
      el.removeAttribute('tabindex');
      return;
    }

    var mq = window.matchMedia(query);

    function sync() {
      el.tabIndex = mq.matches ? -1 : 0;
    }

    sync();

    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', sync);
    } else {
      mq.addListener(sync);
    }
  }

  bindScrollerTabindex('.work__scroller', '(min-width: 768px)');
  bindScrollerTabindex('.instagram__strip', '(min-width: 1200px)');


  /* --------------------------------------------------------------------------
     INSTAGRAM — otto lavori a caso

     I riquadri restano quelli della striscia. A ogni caricamento si pescano
     otto scatti diversi tra images/lavori, senza ripetizioni. Il bianco e
     nero e il formato quadrato stanno nel CSS, sui file non si tocca nulla.
     ------------------------------------------------------------------------ */

  (function () {
    var strip = document.querySelector('.instagram__strip');
    if (!strip) { return; }

    var cells = strip.querySelectorAll('.instagram__cell');
    if (!cells.length) { return; }

    var files = [
      'alessandro-01.webp', 'alessandro-02.webp', 'alessandro-03.webp',
      'alessandro-04.webp', 'alessandro-05.webp', 'alessandro-06.webp',
      'angela-01.webp', 'angela-02.webp', 'angela-03.webp',
      'angela-04.webp', 'angela-05.webp', 'angela-06.webp',
      'big-01.webp', 'big-02.webp', 'big-03.webp', 'big-04.webp', 'big-05.webp',
      'blessed-01.webp', 'blessed-02.webp', 'blessed-03.webp',
      'christian-01.webp', 'christian-02.webp', 'christian-03.webp',
      'christian-04.webp', 'christian-05.webp', 'christian-06.webp',
      'giuseppe-01.webp', 'giuseppe-02.webp', 'giuseppe-03.webp',
      'giuseppe-04.webp', 'giuseppe-05.webp', 'giuseppe-06.webp',
      'lobo-01.webp', 'lobo-02.webp', 'lobo-03.webp',
      'lobo-04.webp', 'lobo-05.webp', 'lobo-06.webp',
      'simone-01.webp', 'simone-02.webp', 'simone-03.webp',
      'simone-04.webp', 'simone-05.webp', 'simone-06.webp',
      'valerio-01.webp', 'valerio-02.webp', 'valerio-03.webp',
      'valerio-04.webp', 'valerio-05.webp'
    ];

    var names = {
      alessandro: 'Alessandro',
      angela: 'Angela',
      big: 'Big',
      blessed: 'Blessed',
      christian: 'Christian',
      giuseppe: 'Giuseppe',
      lobo: 'Lobo',
      simone: 'Simone',
      valerio: 'Valerio'
    };

    var pool = files.slice();
    var i;
    for (i = pool.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var swap = pool[i];
      pool[i] = pool[j];
      pool[j] = swap;
    }

    var root = LANG === 'en' ? '../images/lavori/' : 'images/lavori/';
    var altPrefix = LANG === 'en' ? 'Tattoo by ' : 'Tatuaggio di ';

    Array.prototype.forEach.call(cells, function (cell, index) {
      var file = pool[index];
      if (!file) { return; }
      var artist = file.slice(0, file.indexOf('-'));
      var img = document.createElement('img');
      img.className = 'instagram__img';
      img.src = root + file;
      img.alt = altPrefix + (names[artist] || artist);
      img.width = 600;
      img.height = 600;
      img.loading = 'lazy';
      img.decoding = 'async';
      cell.textContent = '';
      cell.appendChild(img);
    });
  })();


  /* --------------------------------------------------------------------------
     SCROLLERS ORIZZONTALI — drag del mouse e rotella

     Overflow-x nativo copre il dito (momentum iOS compreso) e il trackpad
     orizzontale. Non copre il trascinamento col mouse, né la rotella
     verticale che di default scorre la pagina. Qui si aggiungono solo quelli,
     e solo su pointer mouse/pen: un drag custom su touch ruberebbe lo
     scroll nativo e lo peggiorerebbe.

     1:1 col pointer, soglia 10px prima di impegnarsi (così un click sulla
     card resta un click). La pointer capture parte solo dopo quella soglia:
     catturarla al down ritargetta il click sulla lista e il link non apre.
     Snap CSS spento durante il gesto. A fine corsa della rotella la pagina
     riprende — non si resta prigionieri della striscia.
     ------------------------------------------------------------------------ */

  function bindHScroll(selector) {
    var el = document.querySelector(selector);
    if (!el) { return; }

    var THRESHOLD = 10;
    var EDGE = 1;
    var dragging = false;
    var dragged = false;
    var pointerId = 0;
    var startX = 0;
    var startScroll = 0;

    function endDrag(event) {
      if (!dragging) { return; }
      if (event && event.pointerId !== pointerId) { return; }
      if (el.hasPointerCapture(pointerId)) {
        el.releasePointerCapture(pointerId);
      }
      dragging = false;
      pointerId = 0;
      el.classList.remove('is-dragging');
      window.removeEventListener('pointerup', onWindowUp);
      window.removeEventListener('pointercancel', onWindowCancel);
    }

    function onWindowUp(event) {
      if (event.pointerId !== pointerId) { return; }
      endDrag(event);
      // Il click arriva dopo il pointerup. Teniamo `dragged` per quel
      // tick, poi lo abbassiamo: se il click non parte (pointercancel,
      // gesture persa) il prossimo tap sulla card non deve essere mangiato.
      if (dragged) {
        window.setTimeout(function () { dragged = false; }, 0);
      }
    }

    function onWindowCancel(event) {
      if (event.pointerId !== pointerId) { return; }
      dragged = false;
      endDrag(event);
    }

    el.addEventListener('pointerdown', function (event) {
      if (event.pointerType !== 'mouse' && event.pointerType !== 'pen') { return; }
      if (event.button !== 0) { return; }
      dragging = true;
      dragged = false;
      pointerId = event.pointerId;
      startX = event.clientX;
      startScroll = el.scrollLeft;
      window.addEventListener('pointerup', onWindowUp);
      window.addEventListener('pointercancel', onWindowCancel);
    });

    el.addEventListener('pointermove', function (event) {
      if (!dragging || event.pointerId !== pointerId) { return; }
      var dx = event.clientX - startX;
      if (!dragged) {
        if (Math.abs(dx) < THRESHOLD) { return; }
        dragged = true;
        el.classList.add('is-dragging');
        el.setPointerCapture(event.pointerId);
      }
      el.scrollLeft = startScroll - dx;
    });

    el.addEventListener('dragstart', function (event) {
      event.preventDefault();
    });

    // Cattura: il click parte sul link della card. Se è stato un drag,
    // va fermato prima che apra artisti.html.
    el.addEventListener('click', function (event) {
      if (!dragged) { return; }
      event.preventDefault();
      event.stopPropagation();
      dragged = false;
    }, true);

    el.addEventListener('wheel', function (event) {
      // Il trackpad orizzontale è già nativo: non interferire.
      if (Math.abs(event.deltaX) > Math.abs(event.deltaY)) { return; }
      if (event.deltaY === 0) { return; }

      var max = el.scrollWidth - el.clientWidth;
      if (max <= 0) { return; }

      var atStart = el.scrollLeft <= EDGE;
      var atEnd = el.scrollLeft >= max - EDGE;
      if ((event.deltaY < 0 && atStart) || (event.deltaY > 0 && atEnd)) { return; }

      event.preventDefault();
      el.scrollLeft += event.deltaY;
    }, { passive: false });
  }

  bindHScroll('.artists__scroller');


  /* --------------------------------------------------------------------------
     OUR WORK — nastro circolare in home

     Due copie della stessa striscia, translateX(-50%) in CSS. Senza JS
     restano le nove originali in scroller. Il clone è aria-hidden e fuori
     dal tab order: tastiera e screen reader vedono un set solo. Il click
     sul clone apre comunque il lightbox (stesso href, stesso gruppo).
     ------------------------------------------------------------------------ */

  (function () {
    var root = document.querySelector('.work--marquee');
    if (!root) { return; }

    var track = root.querySelector('.work__track');
    var scroller = root.querySelector('.work__scroller');
    if (!track || !scroller) { return; }

    var clone = scroller.cloneNode(true);
    clone.classList.add('work__set--clone');
    clone.removeAttribute('aria-label');
    clone.setAttribute('aria-hidden', 'true');
    clone.setAttribute('role', 'presentation');
    Array.prototype.forEach.call(clone.querySelectorAll('a'), function (a) {
      a.setAttribute('tabindex', '-1');
    });
    track.appendChild(clone);
  })();

})();

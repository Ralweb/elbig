/* ============================================================================
   BIG ART GALLERY — lang.js
   Scelta lingua. Caricato in <head>, senza defer: se serve un redirect
   (browser in inglese, o preferenza già salvata) deve partire prima del
   paint, altrimenti si vede un flash della pagina sbagliata.

   Convenzione:
   - Italiano = default del sito (root). Inglese = /en/.
   - Un link /en/ aperto di proposito non viene ributtato in italiano al
     primo accesso: si resta in inglese e si ricorda EN.
   - Il click su IT | EN scrive la preferenza; da lì in poi le visite
     successive seguono quella scelta (URL compreso).
   - L'equivalente nell'altra lingua sta nei <link rel="alternate">.
   ========================================================================== */

(function () {
  'use strict';

  var KEY = 'elbig-lang';

  function pageLang() {
    return document.documentElement.lang === 'en' ? 'en' : 'it';
  }

  function readStored() {
    try { return localStorage.getItem(KEY); } catch (err) { return null; }
  }

  function writeStored(lang) {
    try { localStorage.setItem(KEY, lang); } catch (err) { /* private mode */ }
  }

  function detect() {
    var list = navigator.languages || [navigator.language || ''];
    var i;
    var tag;

    for (i = 0; i < list.length; i += 1) {
      tag = String(list[i] || '').toLowerCase();
      if (tag.indexOf('en') === 0) { return 'en'; }
      if (tag.indexOf('it') === 0) { return 'it'; }
    }

    return 'it';
  }

  function altHref(lang) {
    var link = document.querySelector('link[rel="alternate"][hreflang="' + lang + '"]');
    var href;
    var hash;

    if (!link) { return ''; }
    href = link.getAttribute('href');
    if (!href) { return ''; }

    hash = window.location.hash;
    if (hash && href.indexOf('#') === -1) { return href + hash; }
    return href;
  }

  function go(href) {
    if (!href) { return; }
    window.location.replace(href);
  }

  var current = pageLang();
  var stored = readStored();
  var skipRedirect = document.documentElement.getAttribute('data-lang-redirect') === 'off';

  if (!skipRedirect) {
    if (!stored) {
      // Prima visita. Un URL inglese è una scelta esplicita (Google, link
      // condiviso): non si torna indietro. Su una pagina italiana, se il
      // browser è in inglese si passa a /en/.
      if (current === 'en') {
        writeStored('en');
      } else {
        var guessed = detect();
        writeStored(guessed);
        if (guessed !== current) { go(altHref(guessed)); }
      }
    } else if (stored !== current) {
      go(altHref(stored));
    }
  }

  // Il click sul selettore deve scrivere la preferenza PRIMA del cambio
  // pagina, altrimenti il redirect di ritorno la sovrascrive. Delegation
  // su document: questo file gira in <head>, i link stanno nel body.
  document.addEventListener('click', function (event) {
    var node = event.target;
    var link;
    var next;
    var dest;
    var hash;

    if (node && node.nodeType !== 1) { node = node.parentElement; }
    if (!node || typeof node.closest !== 'function') { return; }

    link = node.closest('[data-set-lang]');
    if (!link) { return; }

    next = link.getAttribute('data-set-lang');
    if (next !== 'en' && next !== 'it') { return; }

    event.preventDefault();
    writeStored(next);

    dest = link.href;
    hash = window.location.hash;
    if (hash && dest.indexOf('#') === -1) { dest += hash; }
    window.location.assign(dest);
  });
})();

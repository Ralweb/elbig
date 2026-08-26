# Prompt per Claude Code — Big Art Gallery

Come usarli: apri Claude Code nella cartella del progetto, allega lo screenshot del mockup e il file `01_BRIEF_PROGETTO.md` come contesto, poi incolla i prompt **in ordine, uno alla volta**, aspettando che ognuno sia completato prima del successivo. Non incollarli tutti insieme: ogni step deve poter essere rivisto prima di procedere.

## Quale skill usare quando

Due skill diverse, ruoli non sovrapposti:

- **frontend-design** → decisioni di design generale: layout, spacing, gerarchia visiva, come tradurre il mockup in componenti coerenti. Va richiamata esplicitamente durante **tutti gli step di costruzione (0-7)**, prima di scrivere markup/CSS di ogni sezione.
- **emilkowalski/skills** (`find-animation-opportunities`, `animate`, `review-animations`) → solo motion: easing, durate, cosa animare e cosa no. Va usata **solo agli step 8-9**, dopo che i componenti esistono già staticamente.

Verifica prima di iniziare che entrambe siano effettivamente installate/disponibili nel progetto Claude Code (`.claude/skills/`), altrimenti i richiami nei prompt sotto non avranno effetto.

---



## Step 0 — Setup e contesto

```
Ti allego uno screenshot del mockup di design creato dal cliente per la landing page
del suo tattoo studio, e un file di brief (01_BRIEF_PROGETTO.md) con l'analisi dello
stile e la struttura del progetto. Leggili entrambi con attenzione prima di iniziare.

Usa la skill frontend-design per tutte le decisioni di layout, spacing e gerarchia
visiva da qui in avanti, in ogni step di costruzione — non solo per questo setup
iniziale. Non usarla per le animazioni, quelle le affronteremo più avanti con
un'altra skill dedicata.

Voglio costruire questo sito come HTML/CSS/JS statico puro, senza framework e senza
build step, perché andrà su hosting condiviso Aruba via semplice upload FTP.

Setup iniziale:
- Crea la struttura cartelle: /css, /js, /images, /assets
- Crea index.html vuoto con boilerplate HTML5, meta viewport, meta description,
  favicon placeholder
- Crea css/style.css con un reset CSS minimo e le CSS custom properties (variabili)
  per la palette colori, i font-family, gli spacing, desunti dallo screenshot
  (vedi sezione "Palette" e "Tipografia" nel brief)
- Per i font: proponimi 2-3 alternative Google Fonts gratuite che si avvicinano allo
  stile condensed/bold/poster visto nei titoli del mockup (tipo Anton, Oswald,
  Bebas Neue) e importale via <link>, non via @import
- Non scrivere ancora il markup delle sezioni, solo lo scheletro

Prima di procedere, fammi vedere le variabili CSS che hai definito.
```

---



## Step 1 — Header e navigazione

```
Usa la skill frontend-design per le decisioni di layout di questa sezione.

Ora implementa header e nav della index.html, seguendo lo screenshot:
- Logo a sinistra (per ora un placeholder testuale "BIG", poi lo sostituirò con
  l'SVG del logo reale)
- A destra: bottone "PRENOTA IL TUO TATTOO" (stile bottone primario da variabili CSS)
  + icona hamburger per il menu mobile
- Header fisso (sticky) con sfondo che diventa leggermente opaco/sfumato allo scroll
  (solo un piccolo effetto via JS o CSS, niente di invasivo)
- Il menu hamburger deve aprire una nav a tutto schermo overlay su mobile, con i link
  alle sezioni della pagina (Home, Our Work, Artisti, Studio, Contatti) e un link
  esterno alla pagina artisti.html
- Su desktop il menu può restare orizzontale invece che a hamburger, con breakpoint
  a tua scelta ragionevole (es. 768px)

Usa markup semantico (<header>, <nav>) e assicurati che sia accessibile da tastiera
(focus visibile, aria-expanded sul bottone hamburger).
```

---



## Step 2 — Sezione Hero

```
Usa la skill frontend-design per le decisioni di layout di questa sezione.

Implementa la sezione hero, subito sotto l'header:
- Immagine di sfondo full-bleed (per ora usa un placeholder scuro con testo
  "HERO IMAGE PLACEHOLDER", la sostituirò con la foto reale del cliente)
- Overlay scuro semi-trasparente sopra l'immagine per garantire contrasto col testo
- Contenuto centrato verticalmente e orizzontalmente:
  - Titolo grande "BIG ART GALLERY" (font condensed/bold definito allo step 0)
  - Sottotitolo "BLACK & GREY TATTOO STUDIO" (letter-spacing ampio, più piccolo)
  - Piccolo divisore orizzontale
  - Badge/testo "ROMA"
  - Due bottoni affiancati: "SCOPRI GLI ARTISTI" (outline, scrolla a sezione artisti)
    e "PRENOTA IL TUO TATTOO" (pieno, apre il popup di contatto — per ora lascia
    un TODO nel JS, il popup lo costruiamo dopo)
- Su mobile i due bottoni vanno impilati o affiancati con wrap, valuta tu in base
  allo spazio
- Altezza hero: 100vh su desktop, valuta se ridurre leggermente su mobile per non
  tagliare troppo il contenuto

Verifica che il testo resti leggibile anche se l'immagine di sfondo cambia contrasto.
```

---



## Step 3 — Sezione "Our Work" (gallery)

```
Usa la skill frontend-design per le decisioni di layout di questa sezione.

Implementa la sezione "OUR WORK":
- Titolo centrato "OUR WORK" con piccolo divisore sotto (stesso stile del resto)
- Griglia di 6 immagini verticali di tatuaggi (placeholder per ora, poi le sostituisco
  con le foto reali del cliente). Ogni immagine deve avere hover state leggero
  (leggero zoom o schiarimento, niente di eccessivo)
- Su mobile: scroll orizzontale con snap, o griglia 2 colonne (valuta tu quale rende
  meglio con 6 immagini in verticale — guarda lo screenshot per riferimento)
- Su desktop: griglia a 6 colonne come nello screenshot originale (che è mobile,
  quindi qui stai estendendo tu il layout)
- Sotto la griglia: riga di testo piccolo maiuscolo con i tag separati da "•":
  "BLACK & GREY • REALISM • FINE LINE • LETTERING"
- Bottone outline centrato "VEDI TUTTI I LAVORI" (per ora link "#", non abbiamo
  ancora una pagina lavori dedicata)
```

---



## Step 4 — Sezione "I Nostri Artisti"

```
Usa la skill frontend-design per le decisioni di layout di questa sezione.

Implementa la sezione "I NOSTRI ARTISTI":
- Titolo centrato con divisore, stesso stile delle altre sezioni
- Griglia di 4 card:
  - 3 card artisti reali: foto b/n (placeholder), nome in bold, specialità sotto
    in grigio piccolo maiuscolo, link "SCOPRI I LAVORI →"
  - 1 card "coming soon": stesso formato/dimensione delle altre ma vuota, con
    bordo sottile, icona "+" al centro e testo "COMING SOON" sotto
  - Usa questi dati placeholder (dal brief): Big (Black & Grey / Realism),
    Roman (Realism / Portrait), Simone (Black & Grey / Fine Line)
- Layout: 1 colonna su mobile stretto, 2 su tablet, 4 su desktop
- Hover state sulle card artisti (leggero, es. overlay che schiarisce o mostra
  meglio il nome)
- Bottone outline centrato sotto la griglia "SCOPRI TUTTI GLI ARTISTI", che linka
  a artisti.html (questa pagina la costruiamo più avanti)
```

---



## Step 5 — Sezione Studio + CTA banner + Instagram + Footer

```
Usa la skill frontend-design per le decisioni di layout di queste sezioni.

Implementa le sezioni rimanenti della home, nell'ordine:

1. Sezione "Studio" (about):
   - Layout due colonne su desktop (foto ambiente studio a sinistra, testo a destra),
     stack su mobile (foto sopra, testo sotto)
   - Testo: titolo "BIG ART GALLERY", un paragrafo breve descrittivo (usa un testo
     placeholder plausibile in italiano, tono che comunica cura e ricerca artistica,
     lo rifiniremo dopo), pin/icona location + "ROMA"

2. CTA banner:
   - Sfondo con texture scura (per ora colore pieno scuro leggermente diverso dal
     nero base, poi valutiamo se aggiungere texture marmo)
   - Titolo forte "HAI UN PROGETTO IN MENTE?"
   - Testo breve sotto (2 righe placeholder)
   - Bottone bianco pieno "RICHIEDI IL TUO TATTOO" (apre il popup contatto, stesso
     TODO dello step 2)

3. Sezione Instagram:
   - Titolo "SEGUICI SU INSTAGRAM"
   - Striscia orizzontale di thumbnail (placeholder), scrollabile su mobile
   - Bottone outline con "@BIGARTGALLERYSTUDIO" che linka a instagram.com/bigartgallerystudio

4. Footer:
   - Logo placeholder
   - Colonne con: indirizzo (Via Nomentana 1111, 00100 Roma RM), telefono
     prenotazioni (351 1234567), instagram, orari (Lunedì-Sabato 10:00-19:00)
   - Su mobile le colonne vanno in stack
   - Nota nel codice con un commento che questi sono dati placeholder da confermare
     col cliente
```

---



## Step 6 — Popup contatto

```
Usa la skill frontend-design per le decisioni di layout del modale. Per le
animazioni di apertura/chiusura non usarla — ce ne occupiamo allo step 8 con
un'altra skill dedicata: per ora implementa apertura/chiusura semplici, senza
curarti troppo di easing e durate.

Implementa il popup/modale di contatto, richiamabile da tutti i bottoni "Prenota/
Richiedi il tuo tattoo" già presenti in pagina:

- Modale overlay a schermo intero semi-trasparente, contenuto centrato con animazione
  di apertura/chiusura (fade + leggero scale, easing corretto — vedi note animazioni
  allo step 8)
- Chiudibile con: bottone X, click fuori dal contenuto, tasto ESC
- Focus trap all'interno del modale quando aperto (accessibilità), focus torna al
  bottone che l'ha aperto quando si chiude
- Form con campi: nome, email, telefono (opzionale), stile di tatuaggio desiderato
  (select con opzioni: Black & Grey, Realism, Fine Line, Lettering, Altro),
  messaggio/descrizione progetto (textarea)
- Validazione client-side base (campi obbligatori, formato email)
- Per l'invio: implementa il fetch verso un endpoint placeholder /api/contact.php
  con un commento chiaro che spiega che va sostituito con l'endpoint reale
  (PHP su Aruba oppure servizio esterno tipo Formspree/Web3Forms — lo decidiamo
  a parte)
- Stato di invio: bottone disabilitato + testo "Invio in corso..." durante il
  fetch, messaggio di conferma o errore dopo la risposta
- Il body della pagina non deve scrollare quando il modale è aperto
```

---



## Step 7 — Pagina artisti.html

```
Usa la skill frontend-design per le decisioni di layout di questa pagina.

Crea artisti.html, pagina dedicata alla lista completa dei tatuatori:
- Stesso header/nav e footer della home (riusa i componenti, non duplicare stili
  inutilmente)
- Hero più compatto (non 100vh) con titolo "I NOSTRI ARTISTI" e breve sottotitolo
- Griglia estesa di card artisti, stesso stile della sezione home ma con più
  dettaglio per card: foto grande, nome, specialità, breve bio placeholder
  (2-3 righe), bottone/link "Vedi i lavori" (per ora "#", non abbiamo gallery
  individuali)
- Includi anche la card "coming soon" alla fine della griglia
- Assicurati che i link dalla home (bottone "Scopri tutti gli artisti") puntino
  correttamente qui, e che da qui si possa tornare alla home dal logo in header
```

---



## Step 8 — Animazioni (usando la skill emilkowalski)

```
Ora voglio rifinire le animazioni del sito usando la skill emilkowalski/skills
(find-animation-opportunities e poi animate) che ho installato in questo progetto.

Prima usa find-animation-opportunities per analizzare tutte le sezioni che abbiamo
costruito (hero, gallery Our Work, card artisti, popup contatto, CTA banner) e
dimmi dove secondo te vale davvero la pena aggiungere motion e dove invece è meglio
non animare, seguendo il criterio di restraint della skill.

Poi, per ogni opportunità che approvo, usa la skill animate per implementarla
correttamente (easing, durata, proprietà giuste — transform/opacity, non layout).

Non aggiungere nulla che io non abbia approvato nella lista che mi proponi prima.
```

*(Dopo la risposta di Claude Code con la lista delle opportunità, rispondi indicando quali approvi, poi lascia che implementi solo quelle.)*

---



## Step 9 — Review animazioni

```
Ora usa la skill review-animations per fare un audit di tutte le animazioni che
abbiamo implementato nel sito (hero, gallery, card, popup, hover states). Applica
i criteri della skill: durate corrette, easing corretto per entrate/uscite, uso di
transform/opacity, rispetto di prefers-reduced-motion, hover gestito con
@media (hover: hover) and (pointer: fine) per non attivarsi su touch.

Correggi tutto quello che non passa la review.
```

---



## Step 10 — Responsive check e performance

```
Fai una revisione completa di responsive e performance su tutto il sito
(index.html e artisti.html):

- Verifica i breakpoint su mobile (360-414px), tablet (768px) e desktop (1280px+),
  segnalami eventuali problemi di layout che trovi guardando il codice
- Controlla che tutte le immagini abbiano width/height o aspect-ratio impostati per
  evitare layout shift, e attributo loading="lazy" tranne per l'immagine hero
  (che va invece eager/priority)
- Controlla che non ci siano font-family non caricati o CSS inutilizzato
- Verifica che il popup di contatto e il menu mobile siano usabili anche solo da
  tastiera
- Dammi un riepilogo di eventuali problemi trovati e correggili
```

---



## Step 11 — Preparazione per deploy su Aruba

```
Preparami il progetto per il deploy su hosting condiviso Aruba via FTP:

- Verifica che tutti i path (css, js, immagini) siano relativi e funzionanti anche
  se il sito non è in root del dominio
- Crea un file .htaccess base con: redirect www/non-www a tua scelta, forzatura
  HTTPS, cache headers ragionevoli per immagini/css/js statici, pagina 404
  personalizzata se ne creiamo una
- Scrivimi un file DEPLOY.md con i passaggi manuali che devo fare: quali cartelle/
  file caricare via FTP, dove va lo script PHP del form (se scegliamo quella
  strada), e un checklist finale da spuntare prima di andare live (sostituire
  immagini placeholder, verificare dati di contatto reali, testare il form,
  controllare i link social)
```

---



## Note d'uso generali

- Dopo ogni step, controlla visivamente il risultato (screenshot o browser locale)
prima di passare allo step successivo: è più facile correggere un dettaglio subito
che disfare più step dopo.
- Le immagini placeholder vanno sostituite man mano che il cliente fornisce le foto
reali (gallery lavori, foto artisti, foto studio, logo in SVG).
- Se in un punto qualsiasi Claude Code propone soluzioni diverse da quanto scritto
nel brief, va bene assecondarlo se il motivo è valido (es. accessibilità,
performance) — il brief è una guida, non una gabbia.


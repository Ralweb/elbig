# Brief di progetto — Big Art Gallery Tattoo Studio

## Panoramica
Landing page per uno studio di tatuaggi a Roma, specializzato in **black & grey, realismo, fine line, lettering**. Sito statico, mobile-first, tono elegante/scuro ("da galleria d'arte", non "da salone").

## Stack tecnico
- HTML5 + CSS3 + JavaScript vanilla (no framework, no build step)
- Nessun bundler: file serviti così come sono
- Hosting finale: **Aruba, hosting condiviso** → deve funzionare via semplice upload FTP
- Form di contatto: popup JS (non pagina dedicata) che invia via `fetch()` a uno script PHP (Aruba supporta PHP) oppure a un servizio esterno tipo Formspree/Web3Forms — da decidere in fase di implementazione
- Animazioni: skill `emilkowalski/skills` per criteri di qualità/easing/durata; eventualmente GSAP + ScrollTrigger per animazioni scroll-based più elaborate (via CDN, niente npm)
- Font: da scegliere condensed/bold sans-serif per i titoli (vedi sezione tipografia), un sans-serif pulito per i testi

## Struttura pagine
1. `index.html` — home page (tutte le sezioni descritte sotto)
2. `artisti.html` — pagina con lista completa tatuatori (card più estese di quelle della home)
3. Popup contatti — **non è una pagina**, è un modale JS richiamabile da qualsiasi bottone "Prenota/Richiedi il tuo tattoo"

## Analisi visiva dello screenshot di riferimento

### Palette
- Sfondo: nero pieno (`#000000` / `#0a0a0a`)
- Testo: bianco (`#ffffff`) e grigio chiaro per i sottotitoli (`#b5b5b5` circa)
- Accenti: bianco pieno per i bottoni primari (sfondo bianco, testo nero), bordo bianco sottile per i bottoni secondari (outline, sfondo trasparente)
- Fotografia: sempre desaturata / bianco e nero o toni molto scuri, mai colori accesi — coerente col tema black & grey tattoo

### Tipografia
- Titoli: font **condensed, bold, tutto maiuscolo**, molto simile a stili tipo "Anton", "Oswald" (bold), "Bebas Neue" — ha un carattere "da poster/rock"
- Sottotitoli sotto i titoli principali: stesso font ma peso più leggero, letter-spacing ampio, tutto maiuscolo, dimensione ridotta (es. "BLACK & GREY TATTOO STUDIO")
- Corpo testo: sans-serif pulito, peso normale, leggibile su sfondo scuro
- Uso frequente di piccoli separatori orizzontali (linee sottili) sotto ai titoli di sezione, es. sotto "OUR WORK"

### Componenti UI ricorrenti
- **Bottone primario**: sfondo bianco, testo nero, tutto maiuscolo, bordi leggermente arrotondati o squadrati (verificare nello screenshot: sembrano quasi squadrati/pill leggero)
- **Bottone secondario/outline**: bordo bianco sottile, sfondo trasparente, testo bianco maiuscolo
- **Card artista**: foto b/n a piena larghezza card, overlay scuro in basso con nome (bold), specialità (piccolo, grigio), link "SCOPRI I LAVORI →"
- **Card "coming soon"**: stessa dimensione delle altre card, sfondo vuoto/bordo, icona "+" centrale
- Logo: monogramma a forma di diamante/rombo con lettere stilizzate ("BIG"), usato sia in header che in footer

### Struttura sezioni (in ordine, dalla home)
1. **Header/nav**: logo a sinistra, bottone "PRENOTA IL TUO TATTOO" + icona hamburger a destra
2. **Hero**: foto full-bleed in bianco e nero (mano con macchinetta da tatuaggio + braccio tatuato), overlay scuro per leggibilità testo. Contenuto centrato: nome studio grande ("BIG ART GALLERY"), sottotitolo ("BLACK & GREY TATTOO STUDIO"), badge città ("ROMA"), due bottoni CTA affiancati (outline + pieno)
3. **Our Work**: titolo sezione centrato con linea decorativa sotto, griglia orizzontale/scroll di 6 immagini di tatuaggi (verticali, stesso aspect ratio), riga di tag stile sotto ("BLACK & GREY • REALISM • FINE LINE • LETTERING"), bottone outline "VEDI TUTTI I LAVORI"
4. **I Nostri Artisti**: titolo sezione, griglia di 4 card (3 artisti reali + 1 "coming soon"), bottone outline "SCOPRI TUTTI GLI ARTISTI"
5. **About/Studio**: layout a due colonne — foto ambiente studio a sinistra, testo a destra (titolo "BIG ART GALLERY", paragrafo descrittivo, pin location "ROMA")
6. **CTA banner**: sfondo con texture marmo scuro, titolo forte ("HAI UN PROGETTO IN MENTE?"), testo breve, bottone bianco pieno "RICHIEDI IL TUO TATTOO"
7. **Instagram**: titolo "SEGUICI SU INSTAGRAM", striscia orizzontale di thumbnail quadrati/verticali, bottone outline con handle "@BIGARTGALLERYSTUDIO"
8. **Footer**: logo, indirizzo, telefono/prenotazioni, instagram, orari — layout a colonne su desktop, stack su mobile

### Note di layout
- Design mostrato è **mobile** (viewport stretto) — va comunque progettato responsive, adattando le griglie a più colonne su desktop (es. gallery "Our Work" da 2-3 per riga su mobile a 6 su desktop, card artisti da 1 colonna a 4)
- Molto uso di spazio nero/vuoto tra sezioni — non affollare
- Immagini sempre protagoniste, testo minimale e diretto

## Contenuti noti dallo screenshot (segnaposto — da confermare col cliente)
- Nome: Big Art Gallery — Black & Grey Tattoo Studio
- Città: Roma
- Indirizzo: Via Nomentana 1111, 00100 Roma RM
- Telefono prenotazioni: 351 1234567
- Instagram: @bigartgallerystudio
- Orari: Lunedì–Sabato, 10:00–19:00
- Artisti: Big (Black & Grey / Realism), Roman (Realism / Portrait), Simone (Black & Grey / Fine Line), quarto slot "Coming soon"
- Stili: Black & Grey, Realism, Fine Line, Lettering

⚠️ Questi sono dati presenti nel mockup del cliente: vanno usati come placeholder e confermati/sostituiti con quelli reali prima della pubblicazione.

## Priorità
1. Fedeltà stilistica al mockup (mood scuro, elegante, "da galleria")
2. Performance (immagini ottimizzate, animazioni leggere, no librerie pesanti non necessarie)
3. Responsive corretto (il mockup è mobile, va esteso a desktop con criterio)
4. Compatibilità con hosting statico Aruba (nessuna dipendenza da Node in produzione)

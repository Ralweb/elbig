# Deploy su Aruba (FTP)

Sito statico: HTML + CSS + JS, più uno script PHP per il form. Nessun build, nessun Node in produzione. Si carica così com’è.

Canonico previsto a go-live: **`https://tuodominio.it`** (senza `www`). I redirect HTTPS/www in `.htaccess` sono **spenti** finché il sito non è l’unico sul dominio — altrimenti, in una cartella di test accanto a WordPress, il browser entra in loop (`ERR_TOO_MANY_REDIRECTS`).

---

## 1. Cosa caricare via FTP

**Tutto e solo il contenuto di `public/`.** Il sito vive lì dentro; il resto della repo (questo file, `documentation/`, `.git/`, `.claude/`…) resta in locale e non va mai sul server.

Destinazione sul server Aruba Linux: cartella pubblica **`/www/`** (su alcuni piani Windows è `/httpdocs/` — questo progetto si aspetta Apache + `.htaccess`, quindi hosting **Linux**).

Carica **il contenuto** di `public/`, non la cartella stessa: `public/index.html` deve finire in `/www/index.html`. In FileZilla: a sinistra entri in `public/`, selezioni tutto, trascini in `/www/`.

```
public/
  index.html, artisti.html, artista-*.html, privacy.html, 404.html
  .htaccess
  favicon.ico
  css/  js/  fonts/  images/  assets/  api/
  en/   (stesse pagine in inglese, privacy.html compresa)
```

In FileZilla attiva *Server → Forza visualizzazione file nascosti*, altrimenti `.htaccess` non si vede e non si carica.

Trasferimento: **testo/ASCII** per html, css, js, php, htaccess; **binario** per immagini, svg, font (`.woff2`), ico.

Il Mac crea `.DS_Store` anche dentro `public/`: se finisce sul server non succede niente, `.htaccess` blocca i file che iniziano col punto e quelli di lavoro (`.md`, `.json`, `.env`, `.bak`…). È una rete di sicurezza, non un motivo per caricare altro.

---

## 2. Dove va lo script PHP del form

Percorso sul server, rispetto alla root pubblica:

```
/www/api/contact.php
```

Il JavaScript chiama l’endpoint risolvendolo da `js/main.js` (non dalla pagina), così funziona sia da `index.html` sia da `en/index.html`. Lo script PHP vive in `public/api/contact.php`.

Il form invia anche `lang` (`it` o `en`): oggetto e corpo della mail seguono la lingua del visitatore. `MAIL_SUBJECT` resta il fallback italiano.

**Prima di caricare** apri `public/api/contact.php` e sostituisci le tre costanti in testa:

```php
const MAIL_TO   = 'INFO@TUODOMINIO.IT';       // casella che riceve le richieste
const MAIL_FROM = 'noreply@TUODOMINIO.IT';    // deve essere una casella DEL dominio Aruba
const MAIL_SUBJECT = 'Nuova richiesta tattoo — Big Art Gallery';
```

Su Aruba `mail()` viene accettata solo se `MAIL_FROM` è una casella reale dello stesso dominio. Creala dal pannello (anche `noreply@…` va bene) prima di testare il form.

Se le mail non arrivano o finiscono in spam: nel pannello Aruba abilita SMTP sulla casella e valuta un invio autenticato (non è in questo file: `mail()` è la strada zero-config).

Finché le costanti restano sui valori segnaposto, lo script risponde **500** di proposito — così non si va live con un form che “funziona” ma non spedisce da nessuna parte.

---

## 3. Path relativi (già sistemati)

Tutti i riferimenti a css, js, pagine e (quando ci saranno) immagini sono **relativi alla pagina**, non alla root del dominio:

| Cosa | Esempio | Perché |
| --- | --- | --- |
| CSS / JS / favicon | `css/style.css`, `js/main.js`, `js/lang.js`, `assets/favicon.svg` | Funzionano in `/` e in `/sito/` |
| Pagine interne (IT) | `artisti.html`, `artista-big.html`, `index.html#studio` | Idem |
| Pagine inglesi | `en/index.html`, `en/artisti.html`, `en/artista-big.html` | Cartella `en/`, CSS/JS con `../` |
| Foto (da inserire) | `images/hero.jpg` (da root) / `../images/hero.jpg` (da `en/`) | Relativo all’HTML |
| Form | risolto da `js/main.js` → `api/contact.php` | Stesso endpoint da IT e da EN |

A go-live, i `<link rel="alternate" hreflang>` conviene farli assoluti (`https://tuodominio.it/` e `https://tuodominio.it/en/`). Oggi sono relativi, sufficienti al selettore di lingua.

Nel CSS, un `url()` punta rispetto al file CSS. Una foto di sfondo si scrive `url("../images/nome.jpg")`, non `url("/images/nome.jpg")`.

**Eccezione: `404.html` e `en/404.html`.** Apache le serve lasciando nella barra l’URL sbagliato, quindi i path relativi si rompono. Entrambe hanno `<base href="/">`. Se il sito **non** è in root, cambialo **in tutti e due** i file:

```html
<base href="/sito/">
```

e in `.htaccess`:

```
ErrorDocument 404 /sito/404.html
# RewriteBase /sito/
# RewriteRule ^en/ /sito/en/404.html [L]
```

---

## 4. Passi sul pannello Aruba, poi FTP

1. Dominio puntato all’hosting, DNS propagati.
2. **SSL** attivo sul dominio (Let’s Encrypt / certificato incluso nel piano). Senza SSL il redirect HTTPS in `.htaccess` manda in loop o in errore.
3. Crea la casella `MAIL_FROM` (e quella `MAIL_TO`, se diversa).
4. Credenziali FTP dal pannello (host tipo `ftp.tuodominio.it`, utente, password).
5. Connettiti, apri `/www/`, carica il contenuto di `public/` (punto 1).
6. Verifica che `.htaccess` sia davvero sul server (file nascosti).
7. Apri `https://tuodominio.it` (o `https://tuodominio.it/sito/` se in sottocartella).

Dopo il primo deploy, un aggiornamento è lo stesso: si ricaricano solo i file toccati. CSS e JS restano in cache una settimana: dopo una modifica a `style.css` / `main.js` fai un hard refresh o rinomina (cache-bust) se un cliente vede ancora la versione vecchia.

---

## 5. Test in una cartella del WordPress attuale

`www.bigartgallerystudio.com` è già un WP che forza `www` + HTTPS. Lo `.htaccess` di questo progetto, se toglie il `www` o forza di nuovo HTTPS, entra in loop col WP: Chrome mostra **“ti ha reindirizzato troppe volte”**.

In quella cartella di test:

1. Ricarica lo `.htaccess` aggiornato (i redirect host sono commentati).
2. Apri l’URL in **finestra anonima** — Chrome memorizza i 301 e il loop resta anche dopo la correzione.
3. Opzionale, se la cartella si chiama ad es. `preview`:
   - in `.htaccess`: `RewriteBase /preview/` e `ErrorDocument 404 /preview/404.html`
   - in `404.html` e `en/404.html`: `<base href="/preview/">`

Le pagine HTML (`index.html`, `artisti.html`) funzionano comunque: css/js sono path relativi.

I redirect HTTPS/www si scommentano **solo** quando questo sito sostituisce WordPress e resta solo sul dominio. Se a go-live tieni `www.` (come ora), usa il blocco “canonico CON www” nel file, non quello senza.

---

## 6. Checklist pre-live

Spunta tutto prima di considerare il sito pubblico. I dati oggi in pagina sono quelli del mockup, **non** quelli reali.

### Contenuti e identità

- [ ] Logo SVG reale al posto del monogramma testuale “BIG” (header, footer, overlay, 404)
- [ ] `assets/favicon.svg` sostituito; aggiunto `favicon.ico` in root per i browser vecchi
- [ ] Meta `og:image` su `index.html`, `artisti.html` e su ogni `artista-*.html` (foto reale, URL assoluto `https://…`)
- [ ] Testo about dello studio riletto col cliente (`index.html`, sezione Studio)
- [ ] Bio artisti sostituite: quella corta in `artisti.html` e quella lunga in `artista-*.html` (IT **e** EN)
- [ ] Nomi degli artisti confermati. Se cambiano: rinominare i file `artista-*.html`, i link in `index.html` e `artisti.html`, la fascia precedente/successivo in fondo a ogni pagina artista, il campo `cf-artista` di ciascuna e la lista `$artisti_ammessi` in `api/contact.php`
- [x] Ragione sociale, sede legale e P. IVA nel footer e in `privacy.html` / `en/privacy.html`
- [ ] Numero REA e capitale sociale nel footer (art. 2250 c.c., obbligatori per le società) — da chiedere al cliente
- [ ] Informativa privacy riletta dal consulente del cliente (tempi di conservazione compresi)

### Immagini (oggi sono placeholder)

- [ ] Hero: `images/hero.jpg` (eager, non lazy) al posto di `.hero__placeholder`
- [ ] Our Work: sei scatti verticali `images/work-01.jpg` … `work-06.jpg`, alt veri
- [ ] Artisti: un ritratto per ciascuno, `images/artista-big.jpg` … (serve in home, in `artisti.html` **e** nella pagina dell'artista)
- [ ] Lavori per artista: `images/lavori/big-01.jpg` … sei per pagina, alt veri
- [ ] Studio: `images/studio.jpg`
- [ ] Instagram: otto thumbnail `images/ig-01.jpg` …, oppure feed reale
- [ ] Foto in bianco e nero / desaturate; peso contenuto (webp o jpeg compressi)

I commenti HTML sopra ogni placeholder dicono esatto quale markup `<img>` mettere.

### Contatti reali (stessi dati in più punti)

Sostituire indirizzo, telefono, orari e Instagram **ovunque** compaiono:

- [ ] Footer di `index.html` e di `artisti.html`
- [ ] Meta del menu overlay, entrambe le pagine
- [ ] Link `tel:+39…` allineato al numero visibile
- [ ] Messaggio di errore del form in `js/main.js` (riga con “chiamaci al 351 1234567”)
- [ ] Bottone / handle Instagram → URL del profilo vero (`https://instagram.com/…`)
- [ ] Profilo Instagram esistente, pubblico, handle corretto

### Form

- [ ] Costanti in `api/contact.php` non sono più i segnaposto
- [ ] Casella `MAIL_FROM` creata su Aruba
- [ ] Invio di prova da home e da `artisti.html` (stesso JS), e dalla home inglese
- [ ] Invio da una pagina artista: la mail deve riportare la riga `Artista:` col nome giusto
- [ ] Mail arrivata nella lingua del form (IT o EN), Reply-To = email di chi ha scritto
- [ ] Trappola anti-bot: il campo nascosto “sito” non compare in pagina
- [ ] Validazione: submit vuoto, email rotta, poi un invio buono
- [ ] Su telefono: tastiera giusta su email/tel, chiusura con X / ESC / tap fuori

### Link e pagine

- [ ] Home → `artisti.html` (bottone “Scopri tutti gli artisti”, nav) e Home EN → `en/artisti.html`
- [ ] Home → pagina del singolo artista: ogni card della striscia porta a `artista-*.html`
- [ ] `artisti.html` → pagina del singolo artista: “Vedi i lavori” di ogni voce
- [ ] Da una pagina artista: precedente / successivo / “Tutti gli artisti”, e il giro si chiude (dall'ultimo si torna al primo)
- [ ] `artisti.html` e `artista-*.html` → home (logo, nav); idem in `en/`
- [ ] Ancore `#our-work`, `#artisti`, `#studio`, `#contatti` (stessi id in IT e EN)
- [ ] Selettore IT | EN: header e footer, mantiene l’ancora (`#studio` resta `#studio`)
- [ ] Browser in inglese al primo accesso: da `/` si arriva su `/en/`
- [ ] “Vedi tutti i lavori” è ancora `#` — nasconderlo o puntarlo a qualcosa di reale
- [ ] URL inesistente (es. `/questa-non-c-e`) mostra la 404 del sito, non quella di Aruba
- [ ] URL inesistente sotto `/en/` mostra `en/404.html` (RewriteRule scommentata)
- [ ] 404: CSS caricato, bottoni Home / Artisti funzionanti anche da un path annidato (`/foo/bar`)

### Privacy e sicurezza

- [ ] Nessuna richiesta a domini esterni: DevTools → Network, ricarica, tutto da `tuodominio.it` (i font sono in `fonts/`, non Google Fonts)
- [ ] Footer: link “Privacy e cookie” → `privacy.html` (e `en/privacy.html` da EN)
- [ ] Modale: link “Informativa privacy” sotto il bottone si apre in una nuova scheda
- [ ] Console del browser pulita: nessun errore `Content-Security-Policy` (se compare, una risorsa esterna è stata aggiunta senza aggiornare la CSP in `.htaccess`)
- [ ] `https://tuodominio.it/.htaccess` e `https://tuodominio.it/.DS_Store` rispondono 403
- [ ] Se si aggiungono analytics, mappe o video incorporati, feed Instagram: aggiornare informativa e CSP, e serve un banner cookie con consenso

### HTTPS, www, mobile

- [ ] `http://` reindirizza a `https://`
- [ ] `www.` reindirizza al canonico (o il contrario, se hai invertito)
- [ ] Certificato valido, lucchetto ok su iPhone e Android
- [ ] Home, artisti, modale: viewport 360–414, 768, 1280+
- [ ] Nessun mix http/https su immagini o script

### Dopo il primo giorno live

- [ ] Una richiesta vera è arrivata in casella (non solo il test tuo)
- [ ] Se HSTS è stato scommentato, `http://` non è più raggiungibile nemmeno al primo hop
- [ ] Backup locale (o git) allineato a ciò che sta su FTP

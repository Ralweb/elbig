<?php
/**
 * Configurazione gift card — Big Art Gallery
 *
 * Copia questo file in config.php (stessa cartella) e compila i valori.
 * config.php NON va in git: contiene le chiavi Stripe. Va caricato via FTP
 * insieme al resto di public/. Vedi DEPLOY.md, sezione "Gift card".
 *
 * Finché un valore contiene XXX, checkout, webhook e cron rispondono
 * "non configurato": si va live solo con tutto compilato. L'admin invece
 * chiede solo ADMIN_PASSWORD, così si apre anche prima di Stripe.
 */

declare(strict_types=1);

// Indirizzo pubblico del sito, senza barra finale. Serve per i link di
// ritorno da Stripe e per le immagini nelle email (devono essere assoluti).
const SITE_URL = 'https://bigartgallerystudio.com';

// Stripe → Sviluppatori → Chiavi API. In prova sk_test_…, a go-live sk_live_….
const STRIPE_SECRET_KEY = 'sk_test_XXX';

// Stripe → Sviluppatori → Webhook → endpoint …/api/stripe-webhook.php →
// "Chiave segreta di firma". Cambia tra modalità test e live.
const STRIPE_WEBHOOK_SECRET = 'whsec_XXX';

// Chiave del cron che spedisce i regali programmati. Una stringa lunga e
// casuale qualsiasi: va ripetuta nell'URL configurato su cron-job.org.
const CRON_KEY = 'XXX';

// Password della pagina /admin/. Lunga, non riusata altrove.
const ADMIN_PASSWORD = 'XXX';

// Mittente: casella reale del dominio Aruba (come MAIL_FROM in contact.php).
const GC_MAIL_FROM = 'website@bigartgallerystudio.com';

// Casella dello studio: riceve l'avviso di ogni vendita ed è il Reply-To
// delle mail ai clienti.
const GC_MAIL_STUDIO = 'info@bigartgallerystudio.com';

// Tagli proposti in pagina (euro). Se cambiano, vanno cambiati anche i
// bottoni in gift-card.html ed en/gift-card.html.
const GC_AMOUNTS = [50, 100, 200, 300, 400, 500];

// Importo libero: minimo e massimo in euro, interi.
const GC_CUSTOM_MIN = 20;
const GC_CUSTOM_MAX = 2000;

// Validità, in mesi, a partire dalla consegna.
const GC_VALIDITY_MONTHS = 12;

// Quanto avanti si può programmare l'invio di un regalo, in giorni.
const GC_MAX_SCHEDULE_DAYS = 180;

<?php
/**
 * Gift card — email.
 *
 * Le mail HTML non sono pagine web: niente CSS esterno, niente flex o grid,
 * tabelle e stili in linea. Gmail e Outlook buttano via quasi tutto il
 * resto. Stessa materia del sito però: tre neri, hairline, condensed
 * maiuscolo tracciato, il rombo del monogramma.
 *
 * Il logo è un PNG bianco su trasparente servito dal sito (SITE_URL): l'SVG
 * Gmail non lo mostra, e un'immagine allegata finirebbe tra gli allegati.
 * Oswald si carica dal sito dove il client lo permette (Apple Mail, iOS);
 * altrove si ripiega su Arial Narrow / Helvetica, già previsto nel CSS.
 *
 * Ogni mail ha anche la versione solo testo: chi la apre in un client
 * testuale, o la riceve con le immagini bloccate, legge comunque il codice.
 */

declare(strict_types=1);

const GC_FONT_DISPLAY = "'Oswald','Arial Narrow','Helvetica Neue',Helvetica,Arial,sans-serif";
const GC_FONT_BODY = "'Archivo','Helvetica Neue',Helvetica,Arial,sans-serif";

function gc_money(int $cents, string $lang): string
{
    $decimals = $cents % 100 === 0 ? 0 : 2;
    if ($lang === 'en') {
        return '€' . number_format($cents / 100, $decimals, '.', ',');
    }
    return number_format($cents / 100, $decimals, ',', '.') . ' €';
}

function gc_date(string $utc, string $lang, bool $withTime = false): string
{
    $local = gc_utc($utc)->setTimezone(new DateTimeZone(GC_TZ));
    $months = $lang === 'en'
        ? ['January', 'February', 'March', 'April', 'May', 'June', 'July',
           'August', 'September', 'October', 'November', 'December']
        : ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio',
           'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

    $out = $local->format('j') . ' ' . $months[(int) $local->format('n') - 1] . ' ' . $local->format('Y');
    if ($withTime) {
        $out .= ($lang === 'en' ? ' at ' : ' alle ') . $local->format('H:i');
    }
    return $out;
}

function gc_home_url(string $lang): string
{
    return SITE_URL . ($lang === 'en' ? '/en/' : '/');
}

/**
 * Spedisce una mail HTML + testo. Stessa strada di api/contact.php: mail()
 * di Aruba, mittente casella del dominio.
 */
function gc_send_mail(string $to, string $subject, string $html, string $text, ?string $replyTo = null): bool
{
    $to = str_replace(["\r", "\n"], '', $to);
    $replyTo = str_replace(["\r", "\n"], '', $replyTo ?? GC_MAIL_STUDIO);
    $boundary = 'bag-' . bin2hex(random_bytes(12));

    $headers = [
        'From: Big Art Gallery <' . GC_MAIL_FROM . '>',
        'Reply-To: ' . $replyTo,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: BigArtGallery-giftcard',
    ];

    // base64 e non 8bit: le righe dell'HTML sono lunghe, e oltre i 998
    // caratteri un server di posta ha il diritto di spezzarle a metà tag.
    $body = "--$boundary\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--$boundary\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";

    $subject = '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], ' ', $subject)) . '?=';

    return @mail($to, $subject, $body, implode("\r\n", $headers));
}


// ---------------------------------------------------------------------------
// Mattoni HTML
// ---------------------------------------------------------------------------

function gc_mail_eyebrow(string $text, string $color = '#7a7a7a', int $size = 11): string
{
    return '<span style="font-family:' . GC_FONT_DISPLAY . ';font-size:' . $size . 'px;'
        . 'letter-spacing:3px;text-transform:uppercase;color:' . $color . ';">'
        . gc_h($text) . '</span>';
}

// Il divisore del sito: due hairline e il rombo in mezzo.
function gc_mail_rule(int $width = 160): string
{
    $side = (int) (($width - 24) / 2);
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"><tr>'
        . '<td width="' . $side . '" style="border-top:1px solid #3a3a3a;font-size:0;line-height:0;">&nbsp;</td>'
        . '<td width="24" align="center" style="font-size:8px;line-height:8px;color:#ffffff;">&#9670;</td>'
        . '<td width="' . $side . '" style="border-top:1px solid #3a3a3a;font-size:0;line-height:0;">&nbsp;</td>'
        . '</tr></table>';
}

function gc_mail_p(string $html, string $color = '#b5b5b5', int $size = 16): string
{
    return '<p style="margin:0 0 16px;font-family:' . GC_FONT_BODY . ';font-size:' . $size . 'px;'
        . 'line-height:1.65;color:' . $color . ';">' . $html . '</p>';
}

function gc_mail_title(string $text): string
{
    return '<h1 style="margin:0 0 20px;font-family:' . GC_FONT_DISPLAY . ';font-size:34px;line-height:1;'
        . 'font-weight:700;letter-spacing:0;text-transform:uppercase;color:#ffffff;">'
        . gc_h($text) . '</h1>';
}

function gc_mail_button(string $href, string $label): string
{
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"><tr>'
        . '<td bgcolor="#ffffff" style="border-radius:2px;">'
        . '<a href="' . gc_h($href) . '" style="display:inline-block;padding:16px 28px;'
        . 'font-family:' . GC_FONT_DISPLAY . ';font-size:13px;font-weight:500;letter-spacing:3px;'
        . 'text-transform:uppercase;color:#000000;text-decoration:none;">' . gc_h($label) . '</a>'
        . '</td></tr></table>';
}

/** La carta: importo, codice, scadenza. Il centro di ogni mail al cliente. */
function gc_mail_card(array $card): string
{
    $lang = $card['lang'];
    $en = $lang === 'en';

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#121212" '
        . 'style="background-color:#121212;border:1px solid #2b2b2b;border-radius:2px;">'
        . '<tr><td align="center" style="padding:36px 24px 0;">'
        . gc_mail_eyebrow('Gift card', '#b5b5b5', 12)
        . '</td></tr>'
        . '<tr><td align="center" style="padding:14px 24px 0;font-family:' . GC_FONT_DISPLAY . ';font-size:64px;'
        . 'line-height:1;font-weight:700;color:#ffffff;">'
        . gc_h(gc_money((int) $card['amount_cents'], $lang))
        . '</td></tr>'
        . '<tr><td align="center" style="padding:28px 24px;">' . gc_mail_rule() . '</td></tr>'
        . '<tr><td align="center" style="padding:0 24px 10px;">'
        . gc_mail_eyebrow($en ? 'Your code' : 'Il tuo codice')
        . '</td></tr>'
        . '<tr><td align="center" style="padding:0 24px 36px;">'
        . '<span style="display:inline-block;padding:14px 22px;border:1px solid #3a3a3a;border-radius:2px;'
        . 'font-family:' . GC_FONT_DISPLAY . ';font-size:26px;line-height:1;letter-spacing:4px;color:#ffffff;">'
        . gc_h($card['code'])
        . '</span>'
        . '</td></tr>'
        . '<tr><td style="padding:14px 24px;border-top:1px solid #1f1f1f;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td align="left">' . gc_mail_eyebrow(($en ? 'Valid until ' : 'Valida fino al ') . gc_date($card['expires_at'], $lang), '#7a7a7a', 10) . '</td>'
        . '<td align="right">' . gc_mail_eyebrow($en ? 'Rome' : 'Roma', '#7a7a7a', 10) . '</td>'
        . '</tr></table>'
        . '</td></tr>'
        . '</table>';
}

function gc_mail_steps(string $lang): string
{
    $steps = $lang === 'en'
        ? ['Get in touch to book a consultation.',
           'Give us your code when you book.',
           'The amount comes off the price of your tattoo.']
        : ['Scrivici o chiamaci per fissare la consulenza.',
           'Comunica il codice quando prenoti.',
           'L&rsquo;importo si scala dal prezzo del tatuaggio.'];

    $rows = '';
    foreach ($steps as $i => $step) {
        $rows .= '<tr>'
            . '<td width="36" valign="top" style="padding:0 0 12px;font-family:' . GC_FONT_DISPLAY . ';font-size:14px;'
            . 'line-height:1.65;letter-spacing:2px;color:#7a7a7a;">0' . ($i + 1) . '</td>'
            . '<td valign="top" style="padding:0 0 12px;font-family:' . GC_FONT_BODY . ';font-size:15px;'
            . 'line-height:1.65;color:#b5b5b5;">' . $step . '</td>'
            . '</tr>';
    }

    return '<p style="margin:0 0 16px;">' . gc_mail_eyebrow($lang === 'en' ? 'How to use it' : 'Come si usa', '#ffffff', 12) . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $rows . '</table>';
}

function gc_mail_terms(array $card): string
{
    $lang = $card['lang'];
    $text = $lang === 'en'
        ? 'Valid until ' . gc_date($card['expires_at'], $lang) . ' at the Big Art Gallery studio in Rome. '
          . 'Not exchangeable for cash. Whoever holds the code can use it: keep it safe.'
        : 'Valida fino al ' . gc_date($card['expires_at'], $lang) . ' nello studio Big Art Gallery a Roma. '
          . 'Non convertibile in denaro. Il codice vale per chi lo presenta: custodiscilo.';

    return '<p style="margin:0;font-family:' . GC_FONT_BODY . ';font-size:12px;line-height:1.6;color:#7a7a7a;">'
        . gc_h($text) . '</p>';
}

/** Il messaggio di chi regala, come una dedica scritta a mano sul biglietto. */
function gc_mail_message(array $card): string
{
    $message = trim((string) $card['message']);
    if ($message === '') {
        return '';
    }

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 32px;"><tr>'
        . '<td style="padding:4px 0 4px 20px;border-left:1px solid #5a5a5a;">'
        . '<p style="margin:0 0 10px;font-family:' . GC_FONT_BODY . ';font-size:17px;line-height:1.65;'
        . 'font-style:italic;color:#ffffff;">' . nl2br(gc_h($message)) . '</p>'
        . '<p style="margin:0;">' . gc_mail_eyebrow('— ' . $card['buyer_name']) . '</p>'
        . '</td></tr></table>';
}

/** Cornice comune: logo in testa, contenuto, recapiti in fondo. */
function gc_mail_layout(string $lang, string $preheader, string $content): string
{
    $en = $lang === 'en';
    $logo = SITE_URL . '/assets/logo.png';
    $fonts = SITE_URL . '/fonts/';

    $footer = gc_mail_eyebrow('Black & Grey Tattoo Studio', '#7a7a7a', 10)
        . '<p style="margin:16px 0 0;font-family:' . GC_FONT_BODY . ';font-size:13px;line-height:1.7;color:#7a7a7a;">'
        . 'Via delle Passiflore, 31 &middot; 00172 ' . ($en ? 'Rome' : 'Roma') . '<br>'
        . '<a href="tel:+39065434834" style="color:#b5b5b5;text-decoration:none;">06 543 4834</a>'
        . ' &middot; '
        . '<a href="https://instagram.com/bigartgallerystudio" style="color:#b5b5b5;text-decoration:none;">@bigartgallerystudio</a>'
        . '</p>'
        . '<p style="margin:16px 0 0;font-family:' . GC_FONT_BODY . ';font-size:11px;line-height:1.6;color:#4a4a4a;">'
        . 'BIG ART GALLERY S.r.l.s. &middot; P. IVA 16110741002'
        . '</p>';

    return '<!DOCTYPE html>'
        . '<html lang="' . ($en ? 'en' : 'it') . '"><head>'
        . '<meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="color-scheme" content="dark">'
        . '<meta name="supported-color-schemes" content="dark">'
        . '<title>Big Art Gallery</title>'
        . '<style>'
        . '@font-face{font-family:"Oswald";font-weight:300 700;src:url("' . $fonts . 'oswald-latin.woff2") format("woff2");}'
        . '@font-face{font-family:"Archivo";font-weight:400;src:url("' . $fonts . 'archivo-400-latin.woff2") format("woff2");}'
        . 'body{margin:0;padding:0;background-color:#000000;}'
        . 'a{color:#ffffff;}'
        . '@media (max-width:600px){.gc-pad{padding-left:20px !important;padding-right:20px !important;}}'
        . '</style>'
        . '</head>'
        . '<body style="margin:0;padding:0;background-color:#000000;">'
        // Il preheader: la riga grigia che Gmail e iOS mostrano accanto
        // all'oggetto. Invisibile nella mail aperta.
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#000000;">' . gc_h($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#000000" style="background-color:#000000;">'
        . '<tr><td align="center" style="padding:32px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#0a0a0a" '
        . 'style="max-width:560px;background-color:#0a0a0a;border:1px solid #1f1f1f;">'
        . '<tr><td align="center" class="gc-pad" style="padding:44px 40px 0;">'
        . '<a href="' . gc_h(gc_home_url($lang)) . '"><img src="' . gc_h($logo) . '" width="72" height="63" alt="Big Art Gallery" '
        . 'style="display:block;width:72px;height:auto;border:0;color:#ffffff;font-family:' . GC_FONT_DISPLAY . ';"></a>'
        . '</td></tr>'
        . '<tr><td class="gc-pad" style="padding:40px 40px 8px;">' . $content . '</td></tr>'
        . '<tr><td align="center" class="gc-pad" style="padding:32px 40px 40px;border-top:1px solid #1f1f1f;">' . $footer . '</td></tr>'
        . '</table>'
        . '</td></tr></table>'
        . '</body></html>';
}


// ---------------------------------------------------------------------------
// Le quattro mail
// ---------------------------------------------------------------------------

/** La gift card vera e propria, a chi la userà. */
function gc_mail_voucher(array $card, bool $toRecipient): bool
{
    $lang = $card['lang'];
    $en = $lang === 'en';
    $amount = gc_money((int) $card['amount_cents'], $lang);
    $to = $toRecipient ? $card['recipient_email'] : $card['buyer_email'];
    $buyer = $card['buyer_name'];

    if ($toRecipient) {
        $subject = $en ? "$buyer has given you a tattoo" : "$buyer ti ha regalato un tatuaggio";
        $title = $en ? 'A tattoo, on us' : 'Un tatuaggio per te';
        $intro = $en
            ? 'Hi ' . gc_h($card['recipient_name']) . ', <strong style="color:#ffffff;font-weight:400;">' . gc_h($buyer)
              . '</strong> has given you a ' . gc_h($amount) . ' gift card for Big Art Gallery. The design, the artist and the date are yours to choose.'
            : 'Ciao ' . gc_h($card['recipient_name']) . ', <strong style="color:#ffffff;font-weight:400;">' . gc_h($buyer)
              . '</strong> ti ha regalato una gift card da ' . gc_h($amount) . ' per Big Art Gallery. Il disegno, l&rsquo;artista e la data li scegli tu.';
        $textIntro = $en
            ? "Hi {$card['recipient_name']}, $buyer has given you a $amount gift card for Big Art Gallery."
            : "Ciao {$card['recipient_name']}, $buyer ti ha regalato una gift card da $amount per Big Art Gallery.";
    } else {
        $subject = $en ? "Your Big Art Gallery gift card — $amount" : "La tua gift card Big Art Gallery — $amount";
        $title = $en ? 'Your gift card' : 'La tua gift card';
        $intro = $en
            ? 'Thank you, ' . gc_h($buyer) . '. Here is your ' . gc_h($amount) . ' gift card: forward this email or print it and give it to whoever you like.'
            : 'Grazie, ' . gc_h($buyer) . '. Ecco la tua gift card da ' . gc_h($amount) . ': inoltra questa mail o stampala e regalala a chi vuoi.';
        $textIntro = $en
            ? "Thank you, $buyer. Here is your $amount gift card."
            : "Grazie, $buyer. Ecco la tua gift card da $amount.";
    }

    $content = gc_mail_title($title)
        . gc_mail_p($intro)
        . ($toRecipient ? gc_mail_message($card) : '<div style="height:16px;line-height:16px;font-size:0;">&nbsp;</div>')
        . gc_mail_card($card)
        . '<div style="height:40px;line-height:40px;font-size:0;">&nbsp;</div>'
        . gc_mail_steps($lang)
        . '<div style="height:24px;line-height:24px;font-size:0;">&nbsp;</div>'
        . gc_mail_button(gc_home_url($lang), $en ? 'Book your tattoo' : 'Prenota il tuo tattoo')
        . '<div style="height:40px;line-height:40px;font-size:0;">&nbsp;</div>'
        . gc_mail_terms($card);

    $preheader = $en
        ? "Code {$card['code']} · $amount · valid until " . gc_date($card['expires_at'], $lang)
        : "Codice {$card['code']} · $amount · valida fino al " . gc_date($card['expires_at'], $lang);

    $text = implode("\n", array_filter([
        $textIntro,
        '',
        $toRecipient && trim((string) $card['message']) !== '' ? '"' . trim((string) $card['message']) . '" — ' . $buyer . "\n" : null,
        ($en ? 'GIFT CARD: ' : 'GIFT CARD: ') . $amount,
        ($en ? 'Code: ' : 'Codice: ') . $card['code'],
        ($en ? 'Valid until: ' : 'Valida fino al: ') . gc_date($card['expires_at'], $lang),
        '',
        $en ? 'How to use it: get in touch to book a consultation and give us your code. The amount comes off the price of your tattoo.'
            : 'Come si usa: scrivici o chiamaci per fissare la consulenza e comunica il codice. L\'importo si scala dal prezzo del tatuaggio.',
        '',
        'Big Art Gallery — Via delle Passiflore, 31, 00172 Roma — 06 543 4834',
        gc_home_url($lang),
    ], static fn ($line) => $line !== null));

    return gc_send_mail($to, $subject, gc_mail_layout($lang, $preheader, $content), $text);
}

function gc_mail_voucher_buyer(array $card): bool
{
    return gc_mail_voucher($card, false);
}

function gc_mail_voucher_recipient(array $card): bool
{
    return gc_mail_voucher($card, true);
}

/** Conferma a chi ha comprato un regalo con consegna diretta. */
function gc_mail_buyer_gift(array $card): bool
{
    $lang = $card['lang'];
    $en = $lang === 'en';
    $amount = gc_money((int) $card['amount_cents'], $lang);
    $recipient = $card['recipient_name'];
    $when = gc_date($card['send_at'], $lang, true);
    $already = $card['send_at'] <= gc_now();

    $subject = $en ? "Your gift for $recipient is ready" : "Il tuo regalo per $recipient è pronto";
    $title = $en ? 'Gift on its way' : 'Regalo in partenza';

    if ($already) {
        $delivery = $en
            ? 'We have just sent it to <span style="color:#ffffff;">' . gc_h($card['recipient_email']) . '</span>.'
            : 'L&rsquo;abbiamo appena mandata a <span style="color:#ffffff;">' . gc_h($card['recipient_email']) . '</span>.';
        $textDelivery = $en ? "We have just sent it to {$card['recipient_email']}." : "L'abbiamo appena mandata a {$card['recipient_email']}.";
    } else {
        $delivery = $en
            ? 'It will reach <span style="color:#ffffff;">' . gc_h($card['recipient_email']) . '</span> on <span style="color:#ffffff;">' . gc_h($when) . '</span> (Italian time).'
            : 'Arriverà a <span style="color:#ffffff;">' . gc_h($card['recipient_email']) . '</span> il <span style="color:#ffffff;">' . gc_h($when) . '</span> (ora italiana).';
        $textDelivery = $en
            ? "It will reach {$card['recipient_email']} on $when (Italian time)."
            : "Arriverà a {$card['recipient_email']} il $when (ora italiana).";
    }

    $content = gc_mail_title($title)
        . gc_mail_p(($en
            ? 'Thank you, ' . gc_h($card['buyer_name']) . '. The ' . gc_h($amount) . ' gift card for ' . gc_h($recipient) . ' is paid. '
            : 'Grazie, ' . gc_h($card['buyer_name']) . '. La gift card da ' . gc_h($amount) . ' per ' . gc_h($recipient) . ' è pagata. ')
            . $delivery)
        . gc_mail_p($en
            ? 'The code is below as well: if their email doesn&rsquo;t arrive, you can pass it on yourself.'
            : 'Il codice lo trovi anche qui sotto: se la mail non dovesse arrivare, puoi girarglielo tu.')
        . '<div style="height:16px;line-height:16px;font-size:0;">&nbsp;</div>'
        . gc_mail_card($card)
        . '<div style="height:32px;line-height:32px;font-size:0;">&nbsp;</div>'
        . gc_mail_p($en
            ? 'Need to change the date or the address? Just reply to this email.'
            : 'Vuoi cambiare data o indirizzo? Rispondi a questa mail.', '#7a7a7a', 14);

    $preheader = $en ? "$amount for $recipient · $when" : "$amount per $recipient · $when";

    $text = implode("\n", [
        $en ? "Thank you, {$card['buyer_name']}. The $amount gift card for $recipient is paid."
            : "Grazie, {$card['buyer_name']}. La gift card da $amount per $recipient è pagata.",
        $textDelivery,
        '',
        ($en ? 'Code: ' : 'Codice: ') . $card['code'],
        ($en ? 'Valid until: ' : 'Valida fino al: ') . gc_date($card['expires_at'], $lang),
        '',
        $en ? 'Need to change the date or the address? Just reply to this email.'
            : 'Vuoi cambiare data o indirizzo? Rispondi a questa mail.',
    ]);

    return gc_send_mail($card['buyer_email'], $subject, gc_mail_layout($lang, $preheader, $content), $text);
}

/** Avviso allo studio. Sempre in italiano, Reply-To = chi ha comprato. */
function gc_mail_studio(array $card): bool
{
    $amount = gc_money((int) $card['amount_cents'], 'it');
    $rows = [
        'Codice' => $card['code'],
        'Importo' => $amount,
        'Acquistata il' => gc_date($card['paid_at'], 'it', true),
        'Valida fino al' => gc_date($card['expires_at'], 'it'),
        'Acquirente' => $card['buyer_name'] . ' <' . $card['buyer_email'] . '>',
    ];
    if ($card['is_gift']) {
        $rows['Destinatario'] = $card['recipient_name'] . ' <' . $card['recipient_email'] . '>';
        $rows['Consegna'] = gc_date($card['send_at'], 'it', true);
        if (trim((string) $card['message']) !== '') {
            $rows['Messaggio'] = trim((string) $card['message']);
        }
    }
    $rows['Lingua'] = $card['lang'] === 'en' ? 'inglese' : 'italiano';
    $rows['Pagamento Stripe'] = $card['stripe_payment'];

    $html = '';
    $text = "Nuova gift card venduta dal sito.\n\n";
    foreach ($rows as $label => $value) {
        $html .= '<tr>'
            . '<td valign="top" style="padding:8px 16px 8px 0;border-top:1px solid #1f1f1f;white-space:nowrap;">' . gc_mail_eyebrow($label, '#7a7a7a', 10) . '</td>'
            . '<td valign="top" style="padding:8px 0;border-top:1px solid #1f1f1f;font-family:' . GC_FONT_BODY . ';font-size:15px;line-height:1.5;color:#ffffff;">'
            . nl2br(gc_h((string) $value)) . '</td>'
            . '</tr>';
        $text .= str_pad($label . ':', 18) . $value . "\n";
    }

    $content = gc_mail_title('Gift card venduta')
        . gc_mail_p('L&rsquo;acquirente ha già ricevuto la mail con il codice. La card è anche nella pagina admin.', '#b5b5b5', 15)
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $html . '</table>'
        . '<div style="height:24px;line-height:24px;font-size:0;">&nbsp;</div>'
        . gc_mail_button(SITE_URL . '/admin/', 'Apri admin');

    return gc_send_mail(
        GC_MAIL_STUDIO,
        "Gift card venduta — $amount — {$card['code']}",
        gc_mail_layout('it', "{$card['code']} · $amount · {$card['buyer_name']}", $content),
        $text,
        $card['buyer_email']
    );
}

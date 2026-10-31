<?php
/**
 * Gift card — crea il pagamento.
 *
 * Riceve POST JSON da js/giftcard.js, valida, salva la card come 'pending'
 * e apre una sessione Stripe Checkout. Risponde con l'URL di Stripe: il
 * browser ci va, paga lì, e torna su gift-card?esito=ok.
 *
 * Qui NON si genera il codice: la card diventa valida solo quando Stripe
 * conferma il pagamento al webhook (api/stripe-webhook.php). Il ritorno del
 * browser su ?esito=ok non prova niente, si può scrivere a mano.
 */

declare(strict_types=1);

require __DIR__ . '/../private/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function gc_reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    gc_reply(405, ['ok' => false, 'error' => 'method']);
}

$data = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($data)) {
    gc_reply(400, ['ok' => false, 'error' => 'payload']);
}

// Trappola anti-bot, come in contact.php. Qui non si finge il successo: un
// bot non arriverebbe comunque a pagare, basta non creare nulla.
if (trim((string) ($data['sito'] ?? '')) !== '') {
    gc_reply(400, ['ok' => false, 'error' => 'payload']);
}

if (!gc_configured()) {
    gc_reply(500, ['ok' => false, 'error' => 'unconfigured']);
}

$str = static fn (string $key): string => trim((string) ($data[$key] ?? ''));

$lang = $str('lang') === 'en' ? 'en' : 'it';
$amount = $str('importo');
$buyerName = $str('nome');
$buyerEmail = $str('email');
$isGift = $str('regalo') === '1';
$recipientName = $str('dest_nome');
$recipientEmail = $str('dest_email');
$message = str_replace("\r\n", "\n", $str('messaggio'));
$date = $str('data');
$time = $str('ora');

$errors = [];

$euros = ctype_digit($amount) ? (int) $amount : 0;
if (!in_array($euros, GC_AMOUNTS, true) && ($euros < GC_CUSTOM_MIN || $euros > GC_CUSTOM_MAX)) {
    $errors[] = 'importo';
}

if ($buyerName === '' || gc_len($buyerName) > 120) {
    $errors[] = 'nome';
}
if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL) || gc_len($buyerEmail) > 180) {
    $errors[] = 'email';
}

$sendAt = null;
if ($isGift) {
    if ($recipientName === '' || gc_len($recipientName) > 120) {
        $errors[] = 'dest_nome';
    }
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) || gc_len($recipientEmail) > 180) {
        $errors[] = 'dest_email';
    }
    if (gc_len($message) > 500) {
        $errors[] = 'messaggio';
    }

    // Data e ora sono quelle italiane che ha scelto chi compra.
    $tz = new DateTimeZone(GC_TZ);
    $local = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$date $time", $tz);
    $now = new DateTimeImmutable('now', $tz);

    if (!$local || $local->format('Y-m-d H:i') !== "$date $time") {
        $errors[] = 'data';
    } elseif ($local < $now->modify('-1 day') || $local > $now->modify('+' . GC_MAX_SCHEDULE_DAYS . ' days')) {
        $errors[] = 'data';
    } else {
        // Un orario già passato di oggi = "subito".
        $sendAt = max($local, $now)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
} else {
    $recipientName = $recipientEmail = $message = '';
}

if ($str('termini') !== '1') {
    $errors[] = 'termini';
}

if ($errors) {
    gc_reply(422, ['ok' => false, 'error' => 'validation', 'fields' => $errors]);
}

$cents = $euros * 100;
$ref = bin2hex(random_bytes(16));

try {
    gc_db()->prepare(
        'INSERT INTO giftcards
            (ref, amount_cents, lang, buyer_name, buyer_email, is_gift,
             recipient_name, recipient_email, message, send_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $ref, $cents, $lang, $buyerName, $buyerEmail, $isGift ? 1 : 0,
        $isGift ? $recipientName : null,
        $isGift ? $recipientEmail : null,
        $isGift && $message !== '' ? $message : null,
        $sendAt,
        gc_now(),
    ]);

    $page = SITE_URL . ($lang === 'en' ? '/en' : '') . '/gift-card';
    $money = gc_money($cents, $lang);

    $session = gc_stripe('POST', 'checkout/sessions', [
        'mode' => 'payment',
        'locale' => $lang,
        'client_reference_id' => $ref,
        'customer_email' => $buyerEmail,
        'submit_type' => 'pay',
        'success_url' => $page . '?esito=ok',
        'cancel_url' => $page . '?esito=annullato',
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => $cents,
                'product_data' => [
                    'name' => $lang === 'en'
                        ? "Big Art Gallery gift card — $money"
                        : "Gift card Big Art Gallery — $money",
                    'description' => $lang === 'en'
                        ? 'Valid for ' . GC_VALIDITY_MONTHS . ' months at the studio in Rome.'
                        : 'Valida ' . GC_VALIDITY_MONTHS . ' mesi nello studio a Roma.',
                ],
            ],
        ]],
        'metadata' => ['giftcard_ref' => $ref],
        'payment_intent_data' => [
            'description' => "Gift card $money",
            'metadata' => ['giftcard_ref' => $ref],
        ],
    ]);

    gc_db()->prepare('UPDATE giftcards SET stripe_session = ? WHERE ref = ?')
        ->execute([(string) $session['id'], $ref]);
} catch (Throwable $e) {
    error_log('giftcard checkout: ' . $e->getMessage());
    gc_reply(502, ['ok' => false, 'error' => 'stripe']);
}

gc_reply(200, ['ok' => true, 'url' => (string) $session['url']]);

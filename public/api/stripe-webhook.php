<?php
/**
 * Webhook Stripe — l'unico punto in cui una gift card diventa valida.
 *
 * Stripe chiama questo URL quando un pagamento va a buon fine. Si verifica
 * la firma (STRIPE_WEBHOOK_SECRET), si assegna codice e scadenza, e partono
 * le mail. Eventi da abilitare nel pannello Stripe:
 *   - checkout.session.completed
 *   - checkout.session.async_payment_succeeded
 *
 * Risposte: 200 = ricevuto (anche per eventi che non ci interessano, se no
 * Stripe riprova per giorni), 400 = firma non valida, 500 = errore nostro,
 * e Stripe riproverà da solo.
 */

declare(strict_types=1);

require __DIR__ . '/../private/lib.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!gc_configured()) {
    http_response_code(500);
    echo 'unconfigured';
    exit;
}

$payload = file_get_contents('php://input') ?: '';
$event = gc_stripe_event($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));

if ($event === null) {
    http_response_code(400);
    echo 'bad signature';
    exit;
}

$type = (string) ($event['type'] ?? '');
$session = $event['data']['object'] ?? [];

// Con carta il pagamento è 'paid' già al completed. Con metodi differiti
// (bonifico SEPA e simili, se un giorno si attivano) il completed arriva
// 'unpaid' e la conferma vera è async_payment_succeeded.
$paid = ($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded')
    && ($session['payment_status'] ?? '') === 'paid';

if ($paid) {
    try {
        gc_mark_paid($session);
        gc_process_mail();
    } catch (Throwable $e) {
        error_log('giftcard webhook: ' . $e->getMessage());
        http_response_code(500);
        echo 'error';
        exit;
    }
}

echo 'ok';

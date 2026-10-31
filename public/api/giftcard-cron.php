<?php
/**
 * Gift card — invii programmati.
 *
 * L'hosting Aruba non ha un cron: lo fa un servizio esterno gratuito
 * (cron-job.org) che apre questo URL ogni 10 minuti:
 *
 *   https://bigartgallerystudio.com/api/giftcard-cron.php?key=CRON_KEY
 *
 * Ogni passaggio manda i regali arrivati all'ora di consegna e riprova le
 * mail fallite. Toglie anche i checkout abbandonati più vecchi di 7 giorni:
 * chi ha chiuso la pagina di Stripe senza pagare non lascia dati in giro.
 */

declare(strict_types=1);

require __DIR__ . '/../private/lib.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (!gc_configured() || !hash_equals(CRON_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    echo 'forbidden';
    exit;
}

try {
    $sent = gc_process_mail();

    gc_db()->prepare("DELETE FROM giftcards WHERE status = 'pending' AND created_at < ?")
        ->execute([gmdate('Y-m-d H:i:s', time() - 7 * 86400)]);
} catch (Throwable $e) {
    error_log('giftcard cron: ' . $e->getMessage());
    http_response_code(500);
    echo 'error';
    exit;
}

echo "ok, mail inviate: $sent\n";

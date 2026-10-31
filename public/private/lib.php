<?php
/**
 * Gift card — codice condiviso da checkout, webhook, cron e admin.
 *
 * Niente SDK Stripe né Composer: si carica via FTP così com'è. Le chiamate
 * a Stripe sono due (crea sessione, verifica firma del webhook) e bastano
 * curl e hash_hmac.
 *
 * Orari: nel database sempre UTC, formato 'Y-m-d H:i:s' (si confrontano
 * come stringhe). Si convertono in ora italiana solo per mostrarli.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/mail.php';

const GC_TZ = 'Europe/Rome';

function gc_configured(): bool
{
    foreach ([STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET, CRON_KEY, ADMIN_PASSWORD] as $value) {
        if ($value === '' || str_contains($value, 'XXX')) {
            return false;
        }
    }
    return true;
}

// L'admin non chiama Stripe: per entrare basta la password. Così la pagina
// si può aprire (e la lista vedere) anche prima di configurare i pagamenti.
function gc_admin_configured(): bool
{
    return ADMIN_PASSWORD !== '' && !str_contains(ADMIN_PASSWORD, 'XXX');
}

function gc_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function gc_utc(string $value): DateTimeImmutable
{
    return new DateTimeImmutable($value, new DateTimeZone('UTC'));
}

function gc_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function gc_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}


// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------

function gc_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // data/ eredita il "Require all denied" di private/.htaccess.
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . $dir . '/giftcards.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
    ]);

    // status: 'pending' finché Stripe non conferma, poi 'paid'.
    // *_mail_at: quando è partita la mail. NULL = da mandare (il cron riprova).
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS giftcards (
            id                INTEGER PRIMARY KEY,
            ref               TEXT NOT NULL UNIQUE,
            status            TEXT NOT NULL DEFAULT 'pending',
            amount_cents      INTEGER NOT NULL,
            lang              TEXT NOT NULL DEFAULT 'it',
            buyer_name        TEXT NOT NULL,
            buyer_email       TEXT NOT NULL,
            is_gift           INTEGER NOT NULL DEFAULT 0,
            recipient_name    TEXT,
            recipient_email   TEXT,
            message           TEXT,
            send_at           TEXT,
            code              TEXT UNIQUE,
            stripe_session    TEXT,
            stripe_payment    TEXT,
            created_at        TEXT NOT NULL,
            paid_at           TEXT,
            expires_at        TEXT,
            buyer_mail_at     TEXT,
            studio_mail_at    TEXT,
            recipient_mail_at TEXT,
            mail_failures     INTEGER NOT NULL DEFAULT 0,
            used_at           TEXT,
            used_note         TEXT
        )
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS login_failures (
            ip TEXT NOT NULL,
            at TEXT NOT NULL
        )
    SQL);

    return $pdo;
}

function gc_find_by_ref(string $ref): ?array
{
    $stmt = gc_db()->prepare('SELECT * FROM giftcards WHERE ref = ?');
    $stmt->execute([$ref]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function gc_find_by_id(int $id): ?array
{
    $stmt = gc_db()->prepare('SELECT * FROM giftcards WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}


// ---------------------------------------------------------------------------
// Codice
// ---------------------------------------------------------------------------

// BIG-XXXX-XXXX. Alfabeto senza 0/O, 1/I/L: il codice si detta al telefono
// e si ricopia a mano, e quelle coppie si confondono. 31^8 combinazioni,
// quasi mille miliardi: un codice non si indovina a tentativi.
function gc_new_code(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $part = static function () use ($alphabet, $max): string {
        $out = '';
        for ($i = 0; $i < 4; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    };
    return 'BIG-' . $part() . '-' . $part();
}


// ---------------------------------------------------------------------------
// Stripe
// ---------------------------------------------------------------------------

/**
 * Chiamata all'API Stripe. I parametri annidati (line_items[0][…]) li
 * produce già http_build_query nel formato che Stripe si aspetta.
 * Niente booleani nei parametri: http_build_query li scrive 1/0.
 */
function gc_stripe(string $method, string $path, array $params = []): array
{
    $url = 'https://api.stripe.com/v1/' . $path;
    $ch = curl_init();

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($params);
    } elseif ($params) {
        $url .= '?' . http_build_query($params);
    }

    $options[CURLOPT_URL] = $url;
    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);

    if ($body === false) {
        throw new RuntimeException('Stripe non raggiungibile: ' . $error);
    }

    $data = json_decode((string) $body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Risposta Stripe non valida (HTTP ' . $status . ')');
    }
    if ($status >= 400) {
        $message = $data['error']['message'] ?? 'errore sconosciuto';
        throw new RuntimeException('Stripe HTTP ' . $status . ': ' . $message);
    }

    return $data;
}

/**
 * Verifica la firma dell'header Stripe-Signature e restituisce l'evento.
 * null = firma assente, sbagliata o troppo vecchia (oltre 5 minuti: un
 * evento vero intercettato e rispedito più tardi viene scartato).
 */
function gc_stripe_event(string $payload, string $header): ?array
{
    $timestamp = '';
    $signatures = [];

    foreach (explode(',', $header) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key === 't') {
            $timestamp = $value;
        } elseif ($key === 'v1') {
            $signatures[] = $value;
        }
    }

    if (!ctype_digit($timestamp) || !$signatures || abs(time() - (int) $timestamp) > 300) {
        return null;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, STRIPE_WEBHOOK_SECRET);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            $event = json_decode($payload, true);
            return is_array($event) ? $event : null;
        }
    }

    return null;
}

/**
 * Pagamento confermato da Stripe: assegna codice e scadenza.
 *
 * Idempotente: Stripe può mandare lo stesso evento più volte, e la seconda
 * volta la card è già 'paid' e non si tocca. BEGIN IMMEDIATE prende il lock
 * di scrittura subito, così due consegne simultanee non generano due codici.
 */
function gc_mark_paid(array $session): ?array
{
    $ref = (string) ($session['client_reference_id'] ?? '');
    if ($ref === '') {
        return null;
    }

    $pdo = gc_db();
    $pdo->exec('BEGIN IMMEDIATE');

    try {
        $row = gc_find_by_ref($ref);

        if (!$row || $row['status'] === 'paid') {
            $pdo->exec('COMMIT');
            return $row;
        }

        $total = (int) ($session['amount_total'] ?? -1);
        $currency = strtolower((string) ($session['currency'] ?? ''));
        if ($total !== (int) $row['amount_cents'] || $currency !== 'eur') {
            $pdo->exec('COMMIT');
            error_log("giftcard $ref: importo Stripe $total $currency diverso da {$row['amount_cents']} eur");
            return null;
        }

        // La validità parte dalla consegna: per un regalo programmato è il
        // giorno dell'invio, non quello dell'acquisto.
        $paidAt = gc_now();
        $start = ($row['is_gift'] && $row['send_at'] > $paidAt) ? $row['send_at'] : $paidAt;
        $expires = gc_utc($start)
            ->modify('+' . GC_VALIDITY_MONTHS . ' months')
            ->format('Y-m-d H:i:s');

        $update = $pdo->prepare(
            "UPDATE giftcards
                SET status = 'paid', code = :code, paid_at = :paid, expires_at = :expires,
                    stripe_session = :session, stripe_payment = :payment
              WHERE id = :id"
        );

        // Collisione di codice: improbabile, ma il vincolo UNIQUE la
        // trasforma in un'eccezione e si riprova con un altro.
        for ($attempt = 0; ; $attempt++) {
            try {
                $update->execute([
                    'code' => gc_new_code(),
                    'paid' => $paidAt,
                    'expires' => $expires,
                    'session' => (string) ($session['id'] ?? ''),
                    'payment' => (string) ($session['payment_intent'] ?? ''),
                    'id' => $row['id'],
                ]);
                break;
            } catch (PDOException $e) {
                if ($attempt >= 5 || !str_contains($e->getMessage(), 'UNIQUE')) {
                    throw $e;
                }
            }
        }

        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }

    return gc_find_by_id((int) $row['id']);
}


// ---------------------------------------------------------------------------
// Invio mail (webhook + cron)
// ---------------------------------------------------------------------------

/**
 * Manda tutte le mail dovute: all'acquirente, allo studio, e ai destinatari
 * dei regali arrivati all'ora di consegna. Restituisce quante ne sono partite.
 *
 * Ogni invio si "prenota" prima di partire (UPDATE … WHERE col IS NULL):
 * se webhook e cron girano nello stesso momento, solo uno dei due vince e
 * nessuno riceve la mail due volte. Se mail() fallisce la prenotazione si
 * annulla e il cron successivo riprova, fino a 10 fallimenti per card.
 */
function gc_process_mail(): int
{
    $pdo = gc_db();
    $now = gc_now();

    $stmt = $pdo->prepare(
        "SELECT * FROM giftcards
          WHERE status = 'paid' AND mail_failures < 10
            AND (buyer_mail_at IS NULL
                 OR studio_mail_at IS NULL
                 OR (is_gift = 1 AND recipient_mail_at IS NULL AND send_at <= :now))"
    );
    $stmt->execute(['now' => $now]);

    $sent = 0;
    foreach ($stmt->fetchAll() as $card) {
        $jobs = [];
        if ($card['buyer_mail_at'] === null) {
            $jobs['buyer_mail_at'] = $card['is_gift'] ? 'gc_mail_buyer_gift' : 'gc_mail_voucher_buyer';
        }
        if ($card['studio_mail_at'] === null) {
            $jobs['studio_mail_at'] = 'gc_mail_studio';
        }
        if ($card['is_gift'] && $card['recipient_mail_at'] === null && $card['send_at'] <= $now) {
            $jobs['recipient_mail_at'] = 'gc_mail_voucher_recipient';
        }

        foreach ($jobs as $column => $builder) {
            $claim = $pdo->prepare("UPDATE giftcards SET $column = :now WHERE id = :id AND $column IS NULL");
            $claim->execute(['now' => gc_now(), 'id' => $card['id']]);
            if ($claim->rowCount() !== 1) {
                continue;
            }

            if ($builder($card)) {
                $sent++;
                continue;
            }

            $pdo->prepare(
                "UPDATE giftcards SET $column = NULL, mail_failures = mail_failures + 1 WHERE id = ?"
            )->execute([$card['id']]);
            error_log("giftcard {$card['ref']}: invio $column fallito");
        }
    }

    return $sent;
}

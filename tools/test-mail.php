<?php
// Mail di prova della gift card, spedita dal server Aruba con lo stesso
// codice del sito. Va in /www/ accanto a private/ (che deve già esserci, con
// config.php). Aprila nel browser una volta: spedisce e si cancella.
//
// ?tipo=regalo  → la versione che riceve il destinatario di un regalo.

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

// Diagnostica: Aruba nasconde gli errori e mostra un 500 vuoto. Qui si
// stampano, così si vede cosa manca.
ini_set('display_errors', '1');
error_reporting(E_ALL);
register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        echo "\nERRORE FATALE: {$e['message']}\nin {$e['file']}:{$e['line']}\n";
    }
});

echo 'PHP ' . PHP_VERSION . ' — cartella: ' . __DIR__ . "\n";
foreach (['private/lib.php', 'private/mail.php', 'private/config.php'] as $file) {
    echo (is_file(__DIR__ . '/' . $file) ? '[OK] ' : '[MANCA] ') . $file . "\n";
}
echo "\n";

try {
    require __DIR__ . '/private/lib.php';
} catch (Throwable $e) {
    echo 'ERRORE: ' . $e->getMessage() . "\nin " . $e->getFile() . ':' . $e->getLine() . "\n";
    exit;
}

const TEST_TO = 'lore.cip@gmail.com';

$gift = ($_GET['tipo'] ?? '') === 'regalo';
$now = gc_now();

$card = [
    'ref' => 'test',
    'lang' => 'it',
    'amount_cents' => 10000,
    'code' => gc_new_code(),
    'paid_at' => $now,
    'expires_at' => gc_utc($now)->modify('+' . GC_VALIDITY_MONTHS . ' months')->format('Y-m-d H:i:s'),
    'buyer_name' => 'Lorenzo',
    'buyer_email' => TEST_TO,
    'is_gift' => $gift ? 1 : 0,
    'recipient_name' => $gift ? 'Lorenzo' : null,
    'recipient_email' => $gift ? TEST_TO : null,
    'message' => $gift ? "Buon compleanno!\nScegli tu il disegno." : null,
    'send_at' => $gift ? $now : null,
];

$ok = gc_mail_voucher($card, $gift);

echo $ok
    ? 'Mail di prova inviata a ' . TEST_TO . ' (codice ' . $card['code'] . ', non valido).' . "\n"
    : "mail() ha restituito false: controlla che GC_MAIL_FROM sia una casella reale del dominio.\n";

echo @unlink(__FILE__)
    ? "test-mail.php si è cancellato da solo.\n"
    : "ATTENZIONE: cancella test-mail.php dal server via FTP.\n";

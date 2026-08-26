<?php
/**
 * Form di contatto — Big Art Gallery
 *
 * Riceve POST JSON da js/main.js e spedisce una mail con mail().
 * Configura le tre costanti qui sotto PRIMA di caricare il file via FTP.
 * Vedi DEPLOY.md.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// ---------------------------------------------------------------------------
// CONFIGURAZIONE — da compilare prima del go-live
// ---------------------------------------------------------------------------

// Casella che riceve le richieste (di solito info@ o prenotazioni@ del dominio).
const MAIL_TO = 'INFO@TUODOMINIO.IT';

// Mittente: su Aruba DEVE essere una casella dello stesso dominio,
// altrimenti mail() viene scartata o finisce in spam.
const MAIL_FROM = 'noreply@TUODOMINIO.IT';

// Oggetto della mail in arrivo.
const MAIL_SUBJECT = 'Nuova richiesta tattoo — Big Art Gallery';

// ---------------------------------------------------------------------------

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'payload']);
    exit;
}

// Trappola anti-bot: se arriva compilata si finge successo senza spedire.
// Stesso comportamento del JS, così un bot non impara come aggirarla.
if (isset($data['sito']) && trim((string) $data['sito']) !== '') {
    echo json_encode(['ok' => true]);
    exit;
}

$nome      = trim((string) ($data['nome'] ?? ''));
$email     = trim((string) ($data['email'] ?? ''));
$telefono  = trim((string) ($data['telefono'] ?? ''));
$stile     = trim((string) ($data['stile'] ?? ''));
$messaggio = trim((string) ($data['messaggio'] ?? ''));
$lang      = trim((string) ($data['lang'] ?? 'it'));
if ($lang !== 'en') {
    $lang = 'it';
}

$stili_ammessi = [
    'Black & Grey',
    'Realism',
    'Fine Line',
    'Lettering',
    'Altro',
    'Altro / non lo so ancora',
    'Other',
    'Other / not sure yet',
];

function str_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

$errors = [];

if ($nome === '' || str_len($nome) > 120) {
    $errors[] = 'nome';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || str_len($email) > 180) {
    $errors[] = 'email';
}
if ($telefono !== '' && str_len($telefono) > 40) {
    $errors[] = 'telefono';
}
if ($stile === '' || !in_array($stile, $stili_ammessi, true)) {
    $errors[] = 'stile';
}
if ($messaggio === '' || str_len($messaggio) > 4000) {
    $errors[] = 'messaggio';
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'validation', 'fields' => $errors]);
    exit;
}

if (MAIL_TO === 'INFO@TUODOMINIO.IT' || MAIL_FROM === 'noreply@TUODOMINIO.IT') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'unconfigured']);
    exit;
}

// Newline negli header = injection. Si strappano prima di usare i valori.
$safe_email = str_replace(["\r", "\n"], '', $email);
$safe_nome  = str_replace(["\r", "\n"], '', $nome);

$en = $lang === 'en';

$subject = $en
    ? 'New tattoo request — Big Art Gallery'
    : MAIL_SUBJECT;

$lines = $en
    ? [
        'New request from the Big Art Gallery website',
        '',
        'Name:      ' . $nome,
        'Email:     ' . $email,
        'Phone:     ' . ($telefono !== '' ? $telefono : '(not given)'),
        'Style:     ' . $stile,
        '',
        'Project:',
        $messaggio,
        '',
        '---',
        'Sent ' . gmdate('Y-m-d H:i') . ' UTC',
        'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'n/a'),
        'Language: English',
    ]
    : [
        'Nuova richiesta dal sito Big Art Gallery',
        '',
        'Nome:      ' . $nome,
        'Email:     ' . $email,
        'Telefono:  ' . ($telefono !== '' ? $telefono : '(non indicato)'),
        'Stile:     ' . $stile,
        '',
        'Progetto:',
        $messaggio,
        '',
        '---',
        'Inviata il ' . gmdate('Y-m-d H:i') . ' UTC',
        'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'n/d'),
        'Lingua: italiano',
    ];

$body = implode("\n", $lines);

$headers = [
    'From: Big Art Gallery <' . MAIL_FROM . '>',
    'Reply-To: ' . $safe_nome . ' <' . $safe_email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: BigArtGallery-contact',
];

$encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

$sent = @mail(
    MAIL_TO,
    $encoded_subject,
    $body,
    implode("\r\n", $headers)
);

if (!$sent) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'mail']);
    exit;
}

echo json_encode(['ok' => true]);

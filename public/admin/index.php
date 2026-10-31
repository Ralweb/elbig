<?php
/**
 * Gift card — pagina admin dello studio.
 *
 * Cerca un codice, vede importo, scadenza, chi l'ha comprata e a chi è
 * andata; segna una card come usata (o toglie il segno); rimanda la mail
 * a chi dice di non averla ricevuta.
 *
 * Accesso con ADMIN_PASSWORD (private/config.php): è l'unico valore che
 * serve qui, le chiavi Stripe no. Dopo 5 tentativi
 * sbagliati in 15 minuti dallo stesso IP il login si blocca per quel
 * quarto d'ora: abbastanza per rendere inutile andare a tentativi.
 */

declare(strict_types=1);

require __DIR__ . '/../private/lib.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

session_name('bag_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'), '/') . '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

const ADMIN_IDLE_SECONDS = 4 * 3600;

$self = strtok($_SERVER['REQUEST_URI'] ?? './', '?') ?: './';
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$flash = '';

function admin_redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$logged = !empty($_SESSION['admin']) && (time() - (int) ($_SESSION['seen'] ?? 0)) < ADMIN_IDLE_SECONDS;
if (!$logged) {
    unset($_SESSION['admin']);
}

if (!gc_admin_configured()) {
    http_response_code(500);
    $page = 'unconfigured';
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        admin_redirect($self);
    }

    if ($action === 'login') {
        $db = gc_db();
        $since = gmdate('Y-m-d H:i:s', time() - 900);
        $db->prepare('DELETE FROM login_failures WHERE at < ?')->execute([$since]);
        $count = $db->prepare('SELECT COUNT(*) FROM login_failures WHERE ip = ?');
        $count->execute([$ip]);

        if ((int) $count->fetchColumn() >= 5) {
            $_SESSION['flash'] = 'Troppi tentativi. Riprova tra un quarto d’ora.';
        } elseif (hash_equals(ADMIN_PASSWORD, (string) ($_POST['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['seen'] = time();
        } else {
            $db->prepare('INSERT INTO login_failures (ip, at) VALUES (?, ?)')->execute([$ip, gc_now()]);
            sleep(1);
            $_SESSION['flash'] = 'Password sbagliata.';
        }
        admin_redirect($self);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        admin_redirect($self);
    }

    if (!$logged) {
        admin_redirect($self);
    }

    $card = gc_find_by_id((int) ($_POST['id'] ?? 0));
    $back = $self . (isset($_POST['q']) && $_POST['q'] !== '' ? '?q=' . rawurlencode((string) $_POST['q']) : '');

    if ($card && $card['status'] === 'paid') {
        if ($action === 'use') {
            $note = trim((string) ($_POST['note'] ?? ''));
            gc_db()->prepare('UPDATE giftcards SET used_at = ?, used_note = ? WHERE id = ? AND used_at IS NULL')
                ->execute([gc_now(), $note !== '' ? preg_replace('/^(.{0,300}).*$/su', '$1', $note) : null, $card['id']]);
            $_SESSION['flash'] = $card['code'] . ' segnata come usata.';
        } elseif ($action === 'unuse') {
            gc_db()->prepare('UPDATE giftcards SET used_at = NULL, used_note = NULL WHERE id = ?')
                ->execute([$card['id']]);
            $_SESSION['flash'] = $card['code'] . ' di nuovo attiva.';
        } elseif ($action === 'resend') {
            $toRecipient = (bool) $card['is_gift'];
            $ok = gc_mail_voucher($card, $toRecipient);
            $to = $toRecipient ? $card['recipient_email'] : $card['buyer_email'];
            $_SESSION['flash'] = $ok
                ? 'Gift card ' . $card['code'] . ' rimandata a ' . $to . '.'
                : 'Invio a ' . $to . ' non riuscito. Riprova tra qualche minuto.';
            if ($ok && $toRecipient && $card['recipient_mail_at'] === null) {
                gc_db()->prepare('UPDATE giftcards SET recipient_mail_at = ? WHERE id = ?')
                    ->execute([gc_now(), $card['id']]);
            }
        }
    }
    admin_redirect($back);
} else {
    $page = $logged ? 'list' : 'login';
}

if ($logged) {
    $_SESSION['seen'] = time();
}

$flash = (string) ($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);

$q = trim((string) ($_GET['q'] ?? ''));
$cards = [];
if (($page ?? '') === 'list') {
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $stmt = gc_db()->prepare(
            "SELECT * FROM giftcards
              WHERE status = 'paid'
                AND (code LIKE :q ESCAPE '\\' OR buyer_name LIKE :q ESCAPE '\\' OR buyer_email LIKE :q ESCAPE '\\'
                     OR recipient_name LIKE :q ESCAPE '\\' OR recipient_email LIKE :q ESCAPE '\\')
              ORDER BY paid_at DESC LIMIT 200"
        );
        $stmt->execute(['q' => $like]);
    } else {
        $stmt = gc_db()->query("SELECT * FROM giftcards WHERE status = 'paid' ORDER BY paid_at DESC LIMIT 100");
    }
    $cards = $stmt->fetchAll();
}

$now = gc_now();
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Gift card &mdash; Admin Big Art Gallery</title>
  <meta name="theme-color" content="#0a0a0a">
  <link rel="icon" href="../assets/favicon.ico" sizes="any">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="admin.css">
</head>
<body class="admin">

  <header class="admin__head container">
    <a class="logo" href="../" aria-label="Big Art Gallery, vai al sito">
      <img class="logo__img" src="../assets/logo.png" alt="" width="303" height="265">
    </a>
    <p class="admin__title">Gift card</p>
    <?php if ($logged): ?>
      <form method="post" class="admin__logout">
        <input type="hidden" name="csrf" value="<?= gc_h($csrf) ?>">
        <button class="btn btn--outline btn--sm" type="submit" name="action" value="logout">Esci</button>
      </form>
    <?php endif; ?>
  </header>

  <main class="admin__main container">

    <?php if ($flash !== ''): ?>
      <p class="admin__flash" role="status"><?= gc_h($flash) ?></p>
    <?php endif; ?>

    <?php if (($page ?? '') === 'unconfigured'): ?>

      <p class="admin__empty">Manca la password: imposta <code>ADMIN_PASSWORD</code> in <code>private/config.php</code> (vedi DEPLOY.md).</p>

    <?php elseif ($page === 'login'): ?>

      <form method="post" class="admin__login">
        <input type="hidden" name="csrf" value="<?= gc_h($csrf) ?>">
        <div class="field">
          <label class="field__label" for="password">Password</label>
          <input class="field__input" type="password" id="password" name="password" autocomplete="current-password" required autofocus>
        </div>
        <button class="btn btn--primary" type="submit" name="action" value="login">Entra</button>
      </form>

    <?php else: ?>

      <form method="get" class="admin__search" role="search">
        <div class="field">
          <label class="field__label" for="q">Cerca codice, nome o email</label>
          <input class="field__input" type="search" id="q" name="q" value="<?= gc_h($q) ?>" placeholder="BIG-XXXX-XXXX" autocomplete="off" autocapitalize="characters">
        </div>
        <button class="btn btn--primary" type="submit">Cerca</button>
        <?php if ($q !== ''): ?>
          <a class="btn btn--outline" href="<?= gc_h($self) ?>">Tutte</a>
        <?php endif; ?>
      </form>

      <?php if (!$cards): ?>
        <p class="admin__empty"><?= $q !== '' ? 'Nessuna gift card trovata.' : 'Ancora nessuna gift card venduta.' ?></p>
      <?php else: ?>
        <p class="admin__count">
          <?= count($cards) ?> gift card<?= $q === '' ? ', le più recenti' : '' ?>
        </p>

        <ul class="admin__list" role="list">
          <?php foreach ($cards as $card):
              $used = $card['used_at'] !== null;
              $expired = !$used && $card['expires_at'] < $now;
              $state = $used ? 'used' : ($expired ? 'expired' : 'active');
              $stateLabel = $used ? 'Usata il ' . gc_date($card['used_at'], 'it') : ($expired ? 'Scaduta' : 'Attiva');
          ?>
            <li class="admin-card admin-card--<?= $state ?>">

              <div class="admin-card__top">
                <p class="admin-card__code"><?= gc_h($card['code']) ?></p>
                <p class="admin-card__amount"><?= gc_h(gc_money((int) $card['amount_cents'], 'it')) ?></p>
                <p class="admin-card__state"><?= gc_h($stateLabel) ?></p>
              </div>

              <dl class="admin-card__facts">
                <div><dt>Acquistata</dt><dd><?= gc_h(gc_date($card['paid_at'], 'it', true)) ?></dd></div>
                <div><dt>Valida fino al</dt><dd><?= gc_h(gc_date($card['expires_at'], 'it')) ?></dd></div>
                <div><dt>Acquirente</dt><dd><?= gc_h($card['buyer_name']) ?><br><a href="mailto:<?= gc_h($card['buyer_email']) ?>"><?= gc_h($card['buyer_email']) ?></a></dd></div>
                <?php if ($card['is_gift']): ?>
                  <div><dt>Regalo per</dt><dd><?= gc_h($card['recipient_name']) ?><br><a href="mailto:<?= gc_h($card['recipient_email']) ?>"><?= gc_h($card['recipient_email']) ?></a></dd></div>
                  <div><dt>Consegna</dt><dd>
                    <?= gc_h(gc_date($card['send_at'], 'it', true)) ?><br>
                    <?php if ($card['recipient_mail_at'] !== null): ?>
                      inviata
                    <?php elseif ($card['send_at'] > $now): ?>
                      programmata
                    <?php else: ?>
                      <span class="admin-card__warn">in attesa di invio<?= (int) $card['mail_failures'] >= 10 ? ' &mdash; invio fallito, usa “Rimanda”' : '' ?></span>
                    <?php endif; ?>
                  </dd></div>
                <?php endif; ?>
                <?php if ($used && $card['used_note']): ?>
                  <div><dt>Nota</dt><dd><?= gc_h($card['used_note']) ?></dd></div>
                <?php endif; ?>
              </dl>

              <div class="admin-card__actions">
                <?php if (!$used): ?>
                  <form method="post" class="admin-card__use">
                    <input type="hidden" name="csrf" value="<?= gc_h($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $card['id'] ?>">
                    <input type="hidden" name="q" value="<?= gc_h($q) ?>">
                    <label class="visually-hidden" for="note-<?= (int) $card['id'] ?>">Nota</label>
                    <input class="field__input" type="text" id="note-<?= (int) $card['id'] ?>" name="note" placeholder="Nota (facoltativa): artista, lavoro…" maxlength="300">
                    <button class="btn btn--primary btn--sm" type="submit" name="action" value="use">Segna come usata</button>
                  </form>
                <?php else: ?>
                  <form method="post">
                    <input type="hidden" name="csrf" value="<?= gc_h($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $card['id'] ?>">
                    <input type="hidden" name="q" value="<?= gc_h($q) ?>">
                    <button class="btn btn--outline btn--sm" type="submit" name="action" value="unuse">Annulla “usata”</button>
                  </form>
                <?php endif; ?>

                <?php if (!$card['is_gift'] || $card['send_at'] <= $now): ?>
                  <form method="post">
                    <input type="hidden" name="csrf" value="<?= gc_h($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $card['id'] ?>">
                    <input type="hidden" name="q" value="<?= gc_h($q) ?>">
                    <button class="btn btn--outline btn--sm" type="submit" name="action" value="resend">
                      Rimanda a <?= gc_h($card['is_gift'] ? $card['recipient_email'] : $card['buyer_email']) ?>
                    </button>
                  </form>
                <?php endif; ?>
              </div>

            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    <?php endif; ?>

  </main>

</body>
</html>
